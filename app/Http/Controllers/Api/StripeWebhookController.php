<?php

namespace App\Http\Controllers\Api;

use App\Mail\InvoicePaymentFailedMail;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Webhook;
use Carbon\Carbon;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
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
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Log general para todos los eventos Stripe
        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $sessionId = (string) ($session->id ?? '');
            $email   = $session->customer_email ?? null;
            $customerId = (string) ($session->customer ?? '');
            $metadataClinicId = (int) ($session->metadata->clinic_id ?? 0);

            // Marcar BillingPayment como pagado si viene en metadata
            $paymentId = $session->metadata->payment_id ?? null;
            if ($paymentId) {
                \App\Models\BillingPayment::where('id', $paymentId)
                    ->whereIn('status', ['pending'])
                    ->update(['status' => 'paid']);
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
                // Crear o actualizar suscripción activa
                $existing = Subscription::where('clinic_id', $clinic->id)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->first();

                if ($existing) {
                    $existing->current_period_end = Carbon::now()->addMonth();
                    $existing->stripe_customer_id = $customerId !== '' ? $customerId : $existing->stripe_customer_id;
                    $existing->stripe_subscription_id = $session->subscription ?? $existing->stripe_subscription_id;
                    $existing->save();
                } else {
                    Subscription::create([
                        'clinic_id' => $clinic->id,
                        'status' => 'active',
                        'trial_ends_at' => null,
                        'current_period_end' => Carbon::now()->addMonth(),
                        'stripe_customer_id' => $customerId !== '' ? $customerId : null,
                        'stripe_subscription_id' => $session->subscription ?? null,
                    ]);
                }

                // Eliminar registros de trial previos
                Subscription::where('clinic_id', $clinic->id)
                    ->where('status', 'trial')
                    ->delete();

                $clinic->subscribed_at = Carbon::now();
                $clinic->subscription_status = 'active';
                $clinic->subscription_provider = 'stripe';
                $clinic->stripe_id = $customerId !== '' ? $customerId : $clinic->stripe_id;
                $clinic->stripe_customer_id = $customerId !== '' ? $customerId : $clinic->stripe_customer_id;
                $clinic->save();

                ActivityLogger::log(
                    tenantId: (int) $clinic->id,
                    userId: null,
                    event: $previousSubscriptionStatus === 'active' ? 'subscription_renewed' : 'subscription_created',
                    description: $previousSubscriptionStatus === 'active'
                        ? 'Suscripcion renovada por webhook checkout.session.completed'
                        : 'Suscripcion creada por webhook checkout.session.completed',
                    metadata: [
                        'provider' => 'stripe',
                        'webhook_event' => 'checkout.session.completed',
                        'session_id' => $sessionId,
                    ],
                );
            }

            // ✅ NUEVO: Manejar el flujo de upgrade de suscripción
            if ($sessionId !== '') {
                $subscriptionRequest = \App\Models\SubscriptionRequest::where('stripe_checkout_session_id', $sessionId)
                    ->first();

                if (! $subscriptionRequest) {
                    $metadataRequestId = (int) ($session->metadata->subscription_request_id ?? 0);
                    if ($metadataRequestId > 0) {
                        $subscriptionRequest = \App\Models\SubscriptionRequest::find($metadataRequestId);
                    }
                }

                if ($subscriptionRequest && $subscriptionRequest->status === 'waiting_payment') {
                    // ✅ Log el webhook del upgrade
                    \Illuminate\Support\Facades\Log::info('Procesando webhook de checkout.session.completed para solicitud de upgrade', [
                        'request_id' => $subscriptionRequest->id,
                        'clinic_id' => $subscriptionRequest->clinic_id,
                        'session_id' => $sessionId,
                        'plan' => $subscriptionRequest->requested_plan,
                    ]);

                    // ✅ Servicios para procesar el payment
                    $upgradeService = app(\App\Services\Subscription\SubscriptionUpgradeService::class);

                    try {
                        // ✅ Marcar request como pagado y completar upgrade
                        $upgradeService->handlePaymentCompleted($subscriptionRequest, [
                            'provider' => 'stripe',
                            'session_id' => $sessionId,
                            'amount' => $session->amount_total,
                            'currency' => $session->currency,
                        ]);

                        // ✅ Log el éxito
                        \Illuminate\Support\Facades\Log::info('Upgrade de suscripción completado', [
                            'request_id' => $subscriptionRequest->id,
                            'clinic_id' => $subscriptionRequest->clinic_id,
                            'plan' => $subscriptionRequest->requested_plan,
                        ]);
                    } catch (\Throwable $e) {
                        // ❌ Log el error pero no fallar el webhook
                        \Illuminate\Support\Facades\Log::error('Error en webhook de checkout.session.completed para upgrade', [
                            'request_id' => $subscriptionRequest->id,
                            'clinic_id' => $subscriptionRequest->clinic_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            } elseif ($event->type === 'invoice.payment_succeeded') {
                $invoice = $event->data->object;
                $customerId = (string) ($invoice->customer ?? '');
                if ($customerId !== '') {
                    $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
                    if ($clinic) {
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
                }
            } elseif ($event->type === 'invoice.payment_failed') {
                $invoice = $event->data->object;
                $customerId = (string) ($invoice->customer ?? '');
                if ($customerId !== '') {
                    $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
                    if ($clinic) {
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
                }
            } elseif ($event->type === 'customer.subscription.deleted') {
                $subscription = $event->data->object;
                $customerId = (string) ($subscription->customer ?? '');
                if ($customerId !== '') {
                    $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
                    if ($clinic) {
                        $clinic->subscription_status = 'canceled';
                        $clinic->subscribed_at = null;
                        $clinic->save();

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
                }
            } elseif ($event->type === 'customer.subscription.updated') {
                $subscription = $event->data->object;
                $customerId = (string) ($subscription->customer ?? '');
                if ($customerId !== '') {
                    $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
                    if ($clinic) {
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
                }
        }

        return response()->json(['status' => 'ok']);
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
            Mail::to($emails)->send(new InvoicePaymentFailedMail($clinic, $invoice));
        } catch (\Throwable $e) {
            Log::warning('Stripe invoice.payment_failed mail send failed', [
                'clinic_id' => $clinic->id,
                'invoice_id' => (string) ($invoice->id ?? ''),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
