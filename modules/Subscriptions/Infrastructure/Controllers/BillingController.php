<?php

namespace Modules\Subscriptions\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Services\Backoffice\BackofficeAlertService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\Subscriptions\Application\Services\SubscriptionActivationService;
use Modules\Subscriptions\Infrastructure\Mail\SubscriptionCanceledInternalMail;
use Modules\Subscriptions\Infrastructure\Payment\InvalidWebhookSignatureException;
use Modules\Subscriptions\Infrastructure\Payment\Resolver;

class BillingController extends Controller
{
    public function createCheckout(Request $request)
    {
        $clinic = $request->user()->clinic;
        $previousSubscriptionStatus = strtolower(trim((string) ($clinic->subscription_status ?? 'inactive')));
        $amount = (int) ($request->input('amount', 2900)); // cents default 29.00

        $paymentPayload = [
            'clinic_id' => $clinic->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => 'pending',
            'provider' => config('billing.provider', 'fake'),
        ];
        if ($this->hasBillingMethodColumn()) {
            $paymentPayload['method'] = 'transaction';
        }

        $payment = BillingPayment::create($paymentPayload);

        Log::info('payment.created', [
            'event' => 'payment.created',
            'domain' => 'billing',
            'result' => 'created',
            'billing_payment_id' => $payment->id,
            'clinic_id' => $clinic->id,
            'amount' => (int) $amount,
            'currency' => 'EUR',
            'provider' => (string) ($payment->provider ?? config('billing.provider', 'fake')),
            'status' => (string) ($payment->status ?? 'pending'),
            'user_id' => (int) $request->user()->id,
        ]);

        $provider = Resolver::resolve();

        try {
            $checkout = $provider->createCheckout([
                'payment_id' => $payment->id,
                'amount'     => $amount,
                'currency'   => 'EUR',
                'clinic_id'  => $clinic->id,
                'email'      => $request->user()->email,
            ]);
        } catch (\Throwable $e) {
            $payment->status = 'failed';
            $payment->save();

            Log::warning('subscription.failed', [
                'event' => 'subscription.failed',
                'result' => 'failed',
                'stage' => 'checkout_create',
                'clinic_id' => $clinic->id,
                'user_id' => (int) $request->user()->id,
                'billing_payment_id' => $payment->id,
                'provider' => (string) ($payment->provider ?? config('billing.provider', 'fake')),
                'error_code' => $this->extractBillingErrorCode($e),
                'error_category' => $this->extractBillingErrorCategory($e),
            ]);

            if ($this->isStripeConnectivityIssue($e)) {
                return response()->json([
                    'message' => 'No se pudo conectar con Stripe. Revisa internet/TLS o usa el modo local para continuar.',
                    'code' => 'STRIPE_UNREACHABLE',
                ], 503);
            }

            return response()->json([
                'message' => 'No se pudo iniciar el checkout: ' . $e->getMessage(),
                'code' => 'CHECKOUT_CREATE_FAILED',
            ], 422);
        }

        $payment->provider_ref = $checkout['provider_ref'] ?? null;
        $payment->save();

        // For fake provider, consider payment completed immediately and
        // create/activate subscription for the clinic.
        try {
            if ($provider->getName() === 'fake') {
                $payment->status = 'paid';
                $payment->save();

                $fakeSubId = 'fake-'.uniqid();

                $this->activationService()->activateClinic($clinic, [
                    'user_id' => (int) $request->user()->id,
                    'provider' => 'fake',
                    'subscription_reference' => $fakeSubId,
                    'source' => 'checkout fake',
                    'plan' => 'basic',
                    'previous_status' => $previousSubscriptionStatus,
                    'metadata' => [
                        'payment_id' => (int) $payment->id,
                    ],
                    'ip' => $request->ip(),
                ]);
            }
        } catch (\Exception $e) {
            // ignore failures here; payment created but subscription update non-fatal
        }

        return response()->json([
            'checkout' => $checkout,
            'payment' => $payment,
        ]);
    }

    public function webhook(Request $request)
    {
        $provider = Resolver::resolve();

        try {
            $provider->handleWebhook($request);
        } catch (InvalidWebhookSignatureException $e) {
            Log::warning('stripe.webhook.invalid_signature', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        return response()->json(['status' => 'ok']);
    }

    public function confirmCheckout(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;
        $previousSubscriptionStatus = strtolower(trim((string) ($clinic?->subscription_status ?? 'inactive')));

        if (! $clinic) {
            return response()->json(['message' => 'Clinica no disponible'], 403);
        }

        $sessionId = (string) $request->input('session_id', '');
        if ($sessionId === '') {
            $sessionId = (string) BillingPayment::query()
                ->where('clinic_id', $clinic->id)
                ->where('provider', 'stripe')
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->value('provider_ref');
        }

        if ($sessionId === '') {
            return response()->json([
                'message' => 'No se encontro una sesion pendiente para validar',
            ], 422);
        }

        $provider = Resolver::resolve();

        try {
            $result = $provider->confirmCheckout(['session_id' => $sessionId]);
        } catch (\Throwable $e) {
            Log::warning('subscription.failed', [
                'event' => 'subscription.failed',
                'result' => 'failed',
                'stage' => 'checkout_confirm',
                'clinic_id' => $clinic->id,
                'user_id' => (int) ($user?->id ?? 0),
                'session_id_hash' => substr(sha1($sessionId), 0, 12),
                'error_code' => $this->extractBillingErrorCode($e),
                'error_category' => $this->extractBillingErrorCategory($e),
            ]);

            return response()->json([
                'message' => 'No se pudo validar la sesion de pago: '.$e->getMessage(),
            ], 422);
        }

        if (($result['status'] ?? 'pending') !== 'paid') {
            return response()->json([
                'status' => 'pending',
                'message' => $result['message'] ?? 'El pago todavia no figura como completado en el proveedor',
            ]);
        }

        $updatePayload = ['status' => 'paid'];
        if ($this->hasBillingMethodColumn()) {
            $updatePayload['method'] = $result['payment_method'] ?? 'transaction';
        }

        $paymentId = $result['payment_id'] ?? null;
        if ($paymentId) {
            BillingPayment::query()
                ->where('id', (int) $paymentId)
                ->where('clinic_id', $clinic->id)
                ->update($updatePayload);
        } else {
            BillingPayment::query()
                ->where('clinic_id', $clinic->id)
                ->where('provider', 'stripe')
                ->where('provider_ref', $sessionId)
                ->update($updatePayload);
        }

        $this->activationService()->activateClinic($clinic, [
            'user_id' => (int) ($user?->id ?? 0),
            'provider' => 'stripe',
            'subscription_reference' => $sessionId,
            'stripe_customer_id' => $result['customer'] ?? null,
            'stripe_subscription_id' => $result['subscription'] ?? null,
            'invoice_url' => $result['invoice_url'] ?? null,
            'source' => 'confirmacion de checkout',
            'plan' => 'basic',
            'previous_status' => $previousSubscriptionStatus,
            'metadata' => [
                'session_id' => (string) $sessionId,
            ],
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'active',
            'session_id' => $sessionId,
        ]);
    }

    public function cancelSubscription(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        if (! $clinic) {
            return response()->json(['message' => 'Clinica no disponible'], 403);
        }

        $subscription = $clinic->currentSubscription();

        if (! $subscription || ! in_array((string) $subscription->status, ['active', 'trial'], true)) {
            return response()->json([
                'message' => 'No hay una suscripcion activa para cancelar',
            ], 422);
        }

        $stripeSubId = trim((string) ($subscription->stripe_subscription_id ?? ''));
        $providerName = strtolower(trim((string) ($clinic->subscription_provider ?? '')));

        // Solo intentamos cancelar en el proveedor externo si parece ser una suscripción real (Stripe)
        // y no una reactivación manual o fake.
        $isStripe = ($providerName === 'stripe' || str_starts_with($stripeSubId, 'sub_')) && $stripeSubId !== '';

        if ($isStripe) {
            $provider = Resolver::resolveForCancellation($providerName, $stripeSubId);

            try {
                $provider->cancelSubscription([
                    'clinic_id' => (int) $clinic->id,
                    'stripe_subscription_id' => $stripeSubId,
                    'subscription_reference' => $clinic->subscription_reference,
                ]);
            } catch (\Throwable $e) {
                // Si el error es que ya está cancelada o no existe en Stripe, ignoramos y procedemos con la local
                $msg = strtolower($e->getMessage());
                $isAlreadyDone = str_contains($msg, 'already canceled') || str_contains($msg, 'no such subscription');

                if (! $isAlreadyDone) {
                    Log::warning('subscription.failed', [
                        'event' => 'subscription.failed',
                        'result' => 'failed',
                        'stage' => 'cancel_subscription',
                        'clinic_id' => (int) $clinic->id,
                        'user_id' => (int) $user->id,
                        'subscription_id' => $stripeSubId,
                        'provider' => $providerName,
                        'error_code' => $this->extractBillingErrorCode($e),
                        'error_category' => $this->extractBillingErrorCategory($e),
                        'exception' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'message' => 'No se pudo cancelar la suscripcion en el proveedor: ' . $e->getMessage(),
                    ], 422);
                }

                Log::info('subscription.cancel.stripe_already_done', [
                    'clinic_id' => $clinic->id,
                    'stripe_subscription_id' => $stripeSubId,
                ]);
            }
        }

        $subscription->status = 'canceled';
        $subscription->current_period_end = now()->addDays(7);
        $subscription->save();

        $clinic->subscribed_at = null;
        $clinic->subscription_status = 'canceled';
        $clinic->save();

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: (int) ($user?->id ?? 0),
            event: 'subscription_cancelled',
            description: 'Suscripcion cancelada desde la app',
            metadata: [
                'provider' => $providerName,
                'stripe_subscription_id' => $stripeSubId !== '' ? $stripeSubId : null,
            ],
            ip: $request->ip(),
        );

        app(BackofficeAlertService::class)->subscriptionCancelled($clinic);

        $this->notifyCancellationMail($clinic, $subscription->stripe_subscription_id);

        return response()->json([
            'status' => 'canceled',
            'message' => 'Suscripcion cancelada correctamente',
        ]);
    }

    // fake success route for local testing
    public function fakeSuccess(Request $request)
    {
        $user = $request->user();
        if ($user && $clinic = $user->clinic) {
            $previousSubscriptionStatus = strtolower(trim((string) ($clinic->subscription_status ?? 'inactive')));
            // marcar ultimo payment pendiente como pagado
            try {
                $pending = BillingPayment::query()->where('clinic_id', $clinic->id)
                    ->where('status', 'pending')
                    ->orderByDesc('id')
                    ->first();
                if ($pending) {
                    $pending->status = 'paid';
                    $pending->save();
                }
            } catch (\Exception $e) {
                // ignore
            }

            // crear o actualizar suscripcion activa
            $this->activationService()->activateClinic($clinic, [
                'user_id' => (int) $user->id,
                'provider' => 'fake',
                'source' => 'fake success local',
                'plan' => 'basic',
                'previous_status' => $previousSubscriptionStatus,
                'metadata' => [
                    'source' => 'billing.fake_success',
                ],
                'ip' => $request->ip(),
            ]);
        }

        // Redirigir al dashboard de la SPA - la SPA debe consultar /api/me
        return redirect('/dashboard');
    }

    public function thankyou()
    {
        return view('billing.thankyou');
    }

    private function hasBillingMethodColumn(): bool
    {
        return Schema::hasColumn('billing_payments', 'method');
    }

    private function activationService(): SubscriptionActivationService
    {
        return app(SubscriptionActivationService::class);
    }

    private function notifyCancellationMail($clinic, ?string $stripeSubscriptionId): void
    {
        $recipient = trim((string) config('billing.cancellation_notification_to', ''));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($recipient)->queue(new SubscriptionCanceledInternalMail(
                clinicName: (string) ($clinic->name ?? '-'),
                clinicId: (int) ($clinic->id ?? 0),
                clinicEmail: (string) ($clinic->email ?? ''),
                stripeCustomerId: (string) ($clinic->stripe_id ?? ''),
                stripeSubscriptionId: (string) ($stripeSubscriptionId ?? ''),
            ));
        } catch (\Throwable $e) {
            // La cancelación ya fue aplicada; el aviso por mail no debe revertirla.
        }
    }

    private function isStripeConnectivityIssue(\Throwable $e): bool
    {
        $message = strtolower((string) $e->getMessage());

        return str_contains($message, 'could not connect to stripe')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout was reached')
            || str_contains($message, 'errno 28');
    }

    private function extractBillingErrorCode(\Throwable $e): string
    {
        $message = strtolower((string) $e->getMessage());

        if ($this->isStripeConnectivityIssue($e)) {
            return 'STRIPE_UNREACHABLE';
        }

        if (str_contains($message, 'rate limit')) {
            return 'STRIPE_RATE_LIMITED';
        }

        if (str_contains($message, 'authentication') || str_contains($message, 'invalid api key')) {
            return 'STRIPE_AUTH_ERROR';
        }

        return 'BILLING_PROVIDER_ERROR';
    }

    private function extractBillingErrorCategory(\Throwable $e): string
    {
        $message = strtolower((string) $e->getMessage());

        if ($this->isStripeConnectivityIssue($e)) {
            return 'network';
        }

        if (str_contains($message, 'rate limit')) {
            return 'rate_limit';
        }

        if (str_contains($message, 'authentication') || str_contains($message, 'invalid api key')) {
            return 'authentication';
        }

        return 'provider';
    }
}
