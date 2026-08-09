<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Infrastructure\Payment;

use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Backoffice\BackofficeAlertService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Subscriptions\Application\Services\SubscriptionActivationService;
use Modules\Subscriptions\Infrastructure\Mail\InvoicePaymentFailedMail;
use Stripe\Event;
use Stripe\Webhook;

class StripeWebhookHandler
{
    public function __construct(
        private readonly SubscriptionActivationService $activationService,
    ) {}

    public function handleRequest(Request $request): void
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sig,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            Log::warning('stripe.webhook.verification_failed', [
                'event' => 'stripe.webhook.verification_failed',
                'result' => 'failed',
                'error_code' => 'INVALID_SIGNATURE',
                'error_category' => 'validation',
            ]);

            throw new InvalidWebhookSignatureException($e->getMessage());
        }

        $this->handle($event);
    }

    public function handle(Event $event): void
    {
        // Log general para todos los eventos Stripe
        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            default => null,
        };
    }

    private function handleCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;
        $sessionId = (string) ($session->id ?? '');
        $email = $session->customer_email ?? null;
        $customerId = (string) ($session->customer ?? '');
        $metadataClinicId = (int) ($session->metadata->clinic_id ?? 0);

        $invoiceUrl = null;
        if (! empty($session->invoice)) {
            $invoiceUrl = app(StripePaymentProvider::class)->resolveInvoiceUrl((string) $session->invoice);
        }

        $receiptUrl = app(StripePaymentProvider::class)->resolveReceiptUrl(
            ! empty($session->payment_intent) ? (string) $session->payment_intent : null
        );

        // Marcar BillingPayment como pagado si viene en metadata y persistir enlaces
        $paymentId = $session->metadata->payment_id ?? null;
        if ($paymentId) {
            $paymentUpdate = ['status' => 'paid'];
            if (! empty($invoiceUrl)) {
                $paymentUpdate['invoice_url'] = $invoiceUrl;
            }
            if (! empty($receiptUrl)) {
                $paymentUpdate['receipt_url'] = $receiptUrl;
            }

            \App\Models\BillingPayment::where('id', $paymentId)
                ->whereIn('status', ['pending'])
                ->update($paymentUpdate);
        }

        $clinic = null;

        if ($email) {
            $user = User::where('email', $email)->first();
            $clinic = $user?->clinic;
        }

        if (! $clinic && $metadataClinicId > 0) {
            $clinic = Clinic::query()->find($metadataClinicId);
        }

        if (! $clinic && $customerId !== '') {
            $clinic = Clinic::query()
                ->where('stripe_id', $customerId)
                ->orWhere('stripe_customer_id', $customerId)
                ->first();
        }

        if ($clinic) {
            $previousSubscriptionStatus = strtolower(trim((string) ($clinic->subscription_status ?? 'inactive')));

            $this->activationService->activateClinic($clinic, [
                'user_id' => null,
                'provider' => 'stripe',
                'subscription_reference' => $sessionId,
                'stripe_customer_id' => $customerId !== '' ? $customerId : null,
                'stripe_subscription_id' => $session->subscription ?? null,
                'invoice_url' => $invoiceUrl,
                'receipt_url' => $receiptUrl,
                'source' => 'webhook',
                'plan' => (string) ($session->metadata->plan ?? $clinic->plan ?? 'basic'),
                'previous_status' => $previousSubscriptionStatus,
                'metadata' => [
                    'webhook_event' => 'checkout.session.completed',
                    'session_id' => $sessionId,
                ],
                'ip' => null,
            ]);
        }

        // Flujo de upgrade de suscripción
        if ($sessionId !== '') {
            $subscriptionRequest = \App\Models\SubscriptionRequest::where('stripe_checkout_session_id', $sessionId)
                ->first();

            if (! $subscriptionRequest) {
                $metadataRequestId = (int) ($session->metadata->subscription_request_id ?? 0);
                if ($metadataRequestId > 0) {
                    $subscriptionRequest = \App\Models\SubscriptionRequest::find($metadataRequestId);
                }
            }

            // Fallback: buscar por clinic_id + estado waiting_payment (más reciente)
            if (! $subscriptionRequest && $clinic) {
                $metadataPlan = (string) ($session->metadata->plan ?? '');
                $query = \App\Models\SubscriptionRequest::where('clinic_id', $clinic->id)
                    ->where('status', 'waiting_payment');
                if ($metadataPlan !== '') {
                    $query->where('requested_plan', $metadataPlan);
                }
                $subscriptionRequest = $query->latest()->first();
            }

            if ($subscriptionRequest && $subscriptionRequest->status === 'waiting_payment') {
                Log::info('Procesando webhook de checkout.session.completed para solicitud de upgrade', [
                    'request_id' => $subscriptionRequest->id,
                    'clinic_id' => $subscriptionRequest->clinic_id,
                    'session_id' => $sessionId,
                    'plan' => $subscriptionRequest->requested_plan,
                ]);

                $upgradeService = app(\Modules\Subscriptions\Application\Services\SubscriptionUpgradeService::class);

                try {
                    $upgradeService->handlePaymentCompleted($subscriptionRequest, [
                        'provider' => 'stripe',
                        'session_id' => $sessionId,
                        'amount' => $session->amount_total,
                        'currency' => $session->currency,
                        'invoice_url' => $invoiceUrl,
                        'receipt_url' => $receiptUrl,
                    ]);

                    Log::info('Upgrade de suscripción completado', [
                        'request_id' => $subscriptionRequest->id,
                        'clinic_id' => $subscriptionRequest->clinic_id,
                        'plan' => $subscriptionRequest->requested_plan,
                    ]);
                } catch (\Throwable $e) {
                    // El error no debe fallar el webhook
                    Log::error('Error en webhook de checkout.session.completed para upgrade', [
                        'request_id' => $subscriptionRequest->id,
                        'clinic_id' => $subscriptionRequest->clinic_id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Registrar el pago del checkout de upgrade (guardado contra duplicados)
                if (! \App\Models\BillingPayment::where('subscription_request_id', $subscriptionRequest->id)->exists()) {
                    \App\Models\BillingPayment::create([
                        'clinic_id' => $subscriptionRequest->clinic_id,
                        'amount' => (int) ($session->amount_total ?? 0),
                        'currency' => strtoupper((string) ($session->currency ?? 'EUR')),
                        'status' => 'paid',
                        'provider' => 'stripe',
                        'provider_ref' => $sessionId,
                        'method' => 'upgrade_checkout',
                        'subscription_request_id' => $subscriptionRequest->id,
                        'invoice_url' => $invoiceUrl,
                        'receipt_url' => $receiptUrl,
                    ]);
                }
            }

            // Safety net: si el clinic sigue con plan básico pero hay una solicitud completed, actualizar plan
            if ($clinic && $clinic->plan === 'basic') {
                $completedRequest = \App\Models\SubscriptionRequest::where('clinic_id', $clinic->id)
                    ->where('status', 'completed')
                    ->latest()
                    ->first();
                if ($completedRequest && $completedRequest->requested_plan !== 'basic') {
                    $clinic->plan = $completedRequest->requested_plan;
                    $clinic->max_users = Clinic::PLAN_USER_LIMITS[$completedRequest->requested_plan] ?? $clinic->max_users;
                    $clinic->save();
                    Log::info('Safety net: plan actualizado via webhook', [
                        'clinic_id' => $clinic->id,
                        'new_plan' => $completedRequest->requested_plan,
                        'request_id' => $completedRequest->id,
                    ]);
                }
            }
        }
    }

    private function handleInvoicePaymentSucceeded(Event $event): void
    {
        $invoice = $event->data->object;
        $customerId = (string) ($invoice->customer ?? '');
        if ($customerId === '') {
            return;
        }

        $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
        if (! $clinic) {
            return;
        }

        $clinic->subscription_status = 'active';
        $clinic->subscribed_at = $clinic->subscribed_at ?? Carbon::now();
        $clinic->save();

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: null,
            event: 'subscription_renewed',
            description: 'Suscripcion renovada por webhook invoice.payment_succeeded',
            metadata: [
                'provider' => 'stripe',
                'webhook_event' => 'invoice.payment_succeeded',
                'invoice_id' => (string) ($invoice->id ?? ''),
            ],
        );
    }

    private function handleInvoicePaymentFailed(Event $event): void
    {
        $invoice = $event->data->object;
        $customerId = (string) ($invoice->customer ?? '');
        if ($customerId === '') {
            return;
        }

        $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
        if (! $clinic) {
            return;
        }

        $clinic->subscription_status = 'past_due';
        $clinic->save();

        $subscription = Subscription::query()
            ->where('clinic_id', $clinic->id)
            ->orderByDesc('id')
            ->first();

        if ($subscription && $subscription->status !== 'past_due') {
            $subscription->status = 'past_due';
            $subscription->save();
        }

        Log::warning('subscription.failed', [
            'event' => 'subscription.failed',
            'result' => 'failed',
            'stage' => 'invoice_payment_failed',
            'clinic_id' => $clinic->id,
            'invoice_id' => (string) ($invoice->id ?? ''),
            'provider' => 'stripe',
            'error_code' => 'INVOICE_PAYMENT_FAILED',
            'error_category' => 'payment',
        ]);

        $this->sendInvoicePaymentFailedMailIfEnabled($clinic, $invoice);
    }

    private function handleSubscriptionDeleted(Event $event): void
    {
        $subscription = $event->data->object;
        $customerId = (string) ($subscription->customer ?? '');
        if ($customerId === '') {
            return;
        }

        $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
        if (! $clinic) {
            return;
        }

        $clinic->subscription_status = 'canceled';
        $clinic->subscribed_at = null;
        $clinic->save();

        app(BackofficeAlertService::class)->subscriptionCancelled($clinic);

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: null,
            event: 'subscription_cancelled',
            description: 'Suscripcion cancelada por webhook customer.subscription.deleted',
            metadata: [
                'provider' => 'stripe',
                'webhook_event' => 'customer.subscription.deleted',
                'stripe_customer_id' => $customerId,
            ],
        );
    }

    private function handleSubscriptionUpdated(Event $event): void
    {
        $subscription = $event->data->object;
        $customerId = (string) ($subscription->customer ?? '');
        if ($customerId === '') {
            return;
        }

        $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
        if (! $clinic) {
            return;
        }

        $stripeStatus = strtolower((string) ($subscription->status ?? 'inactive'));
        $clinic->subscription_status = match ($stripeStatus) {
            'active', 'trialing' => 'active',
            'past_due' => 'past_due',
            'canceled', 'cancelled', 'unpaid', 'incomplete_expired' => 'canceled',
            default => 'inactive',
        };
        if ($clinic->subscription_status !== 'active') {
            $clinic->subscribed_at = null;
        }
        $clinic->save();
    }

    private function sendInvoicePaymentFailedMailIfEnabled(Clinic $clinic, object $invoice): void
    {
        if (! config('billing.notify_on_invoice_payment_failed', false)) {
            return;
        }

        $emails = [];

        $clinicEmail = trim((string) ($clinic->email ?? ''));
        if (filter_var($clinicEmail, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $clinicEmail;
        }

        $ownerEmail = (string) User::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', 'owner')
            ->orderBy('id')
            ->value('email');

        if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $ownerEmail;
        }

        $emails = array_values(array_unique($emails));
        if (empty($emails)) {
            Log::warning('Stripe invoice.payment_failed mail skipped: no recipients', [
                'clinic_id' => $clinic->id,
                'invoice_id' => (string) ($invoice->id ?? ''),
            ]);
            return;
        }

        try {
            Mail::to($emails)->queue(new InvoicePaymentFailedMail($clinic, $invoice));
        } catch (\Throwable $e) {
            Log::warning('Stripe invoice.payment_failed mail send failed', [
                'clinic_id' => $clinic->id,
                'invoice_id' => (string) ($invoice->id ?? ''),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
