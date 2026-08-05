<?php

namespace App\Http\Controllers;

use App\Mail\SubscriptionActivatedMail;
use App\Mail\SubscriptionCanceledInternalMail;
use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Services\Backoffice\BackofficeAlertService;
use App\Support\ActivityLogger;
use App\Services\PaymentProvider\Resolver;
use App\Services\PaymentProvider\StripePaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Stripe\Stripe;
use Stripe\StripeClient;

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
            if (method_exists($provider, 'getName') && $provider->getName() === 'fake') {
                $payment->status = 'paid';
                $payment->save();

                $current = $clinic->saasSubscriptions()->orderByDesc('id')->first();
                $fakeSubId = 'fake-' . uniqid();

                if (! $current) {
                    $clinic->saasSubscriptions()->create([
                        'status' => 'active',
                        'trial_ends_at' => null,
                        'current_period_end' => now()->addMonth(),
                        'stripe_subscription_id' => $fakeSubId,
                    ]);
                } else {
                    $current->status = 'active';
                    $current->trial_ends_at = null;
                    $current->current_period_end = now()->addMonth();
                    $current->stripe_subscription_id = $current->stripe_subscription_id ?? $fakeSubId;
                    $current->save();
                }

                $clinic->subscribed_at = now();
                $clinic->trial_ends_at = null;
                $clinic->subscription_status = 'active';
                $clinic->subscription_provider = 'fake';
                $clinic->subscription_reference = $fakeSubId;
                $clinic->save();

                ActivityLogger::log(
                    tenantId: (int) $clinic->id,
                    userId: (int) $request->user()->id,
                    event: $previousSubscriptionStatus === 'active' ? 'subscription_renewed' : 'subscription_created',
                    description: $previousSubscriptionStatus === 'active'
                        ? 'Suscripcion renovada en checkout fake'
                        : 'Suscripcion creada en checkout fake',
                    metadata: [
                        'provider' => 'fake',
                        'payment_id' => (int) $payment->id,
                    ],
                    ip: $request->ip(),
                );

                if (in_array($previousSubscriptionStatus, ['trial', 'trial_warning'], true)) {
                    app(BackofficeAlertService::class)->trialConverted($clinic);
                }

                // Enviar email de activación de plan (solo nueva suscripción)
                if ($previousSubscriptionStatus !== 'active') {
                    try {
                        $recipient = $clinic->ownerUser()->first()
                            ?? $clinic->users()->orderBy('id')->first();

                        if ($recipient && filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                            Mail::to($recipient->email)->queue(
                                new SubscriptionActivatedMail(
                                    clinicName: $clinic->name,
                                    plan: (string) ($clinic->plan ?? 'basic'),
                                    activatedAt: now()->format('d/m/Y H:i'),
                                )
                            );
                        }
                    } catch (\Throwable $e) {
                        Log::error('Failed to send activation email from fake checkout', [
                            'clinic_id' => $clinic->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
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
        $provider->handleWebhook($request->all());

        return response('ok');
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

        $caBundlePath = config('services.stripe.ca_bundle')
            ?: ini_get('curl.cainfo')
            ?: base_path('vendor/stripe/stripe-php/data/ca-certificates.crt');

        if (is_string($caBundlePath) && $caBundlePath !== '' && is_file($caBundlePath)) {
            $normalizedPath = str_replace('\\', '/', $caBundlePath);
            Stripe::setCABundlePath($normalizedPath);
            putenv('SSL_CERT_FILE=' . $normalizedPath);
            putenv('CURL_CA_BUNDLE=' . $normalizedPath);
        }

        if (! config('services.stripe.verify_ssl', true)) {
            Stripe::setVerifySslCerts(false);
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            $session = $stripe->checkout->sessions->retrieve($sessionId, []);
        } catch (\Throwable $e) {
            Log::warning('subscription.failed', [
                'event' => 'subscription.failed',
                'result' => 'failed',
                'stage' => 'checkout_confirm',
                'clinic_id' => $clinic->id,
                'user_id' => (int) $user->id,
                'session_id_hash' => substr(sha1($sessionId), 0, 12),
                'error_code' => $this->extractBillingErrorCode($e),
                'error_category' => $this->extractBillingErrorCategory($e),
            ]);

            return response()->json([
                'message' => 'No se pudo validar la sesion de Stripe: ' . $e->getMessage(),
            ], 422);
        }

        $isPaid = (($session->payment_status ?? null) === 'paid')
            || (($session->status ?? null) === 'complete');

        $resolvedMethod = 'transaction';
        $methodTypes = $session->payment_method_types ?? [];
        if (is_array($methodTypes) && ! empty($methodTypes[0])) {
            $resolvedMethod = strtolower((string) $methodTypes[0]);
        }

        if (! $isPaid) {
            return response()->json([
                'status' => 'pending',
                'message' => 'El pago todavia no figura como completado en Stripe',
            ]);
        }

        $updatePayload = ['status' => 'paid'];
        if ($this->hasBillingMethodColumn()) {
            $updatePayload['method'] = $resolvedMethod;
        }

        $paymentId = $session->metadata->payment_id ?? null;
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

        $current = $clinic->saasSubscriptions()->orderByDesc('id')->first();
        if (! $current) {
            $clinic->saasSubscriptions()->create([
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_end' => now()->addMonth(),
                'stripe_customer_id' => $session->customer ?? null,
                'stripe_subscription_id' => $session->subscription ?? null,
            ]);
        } else {
            $current->status = 'active';
            $current->trial_ends_at = null;
            $current->current_period_end = now()->addMonth();
            $current->stripe_customer_id = $session->customer ?? $current->stripe_customer_id;
            $current->stripe_subscription_id = $session->subscription ?? $current->stripe_subscription_id;
            $current->save();
        }

        $clinic->subscribed_at = now();
        $clinic->trial_ends_at = null;
        $clinic->subscription_status = 'active';
        $clinic->subscription_provider = 'stripe';
        $clinic->subscription_reference = $sessionId;
        $clinic->stripe_id = $session->customer ?? $clinic->stripe_id;
        $clinic->stripe_customer_id = $session->customer ?? $clinic->stripe_customer_id;
        $clinic->save();

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: (int) ($user?->id ?? 0),
            event: $previousSubscriptionStatus === 'active' ? 'subscription_renewed' : 'subscription_created',
            description: $previousSubscriptionStatus === 'active'
                ? 'Suscripcion renovada tras confirmacion de checkout'
                : 'Suscripcion creada tras confirmacion de checkout',
            metadata: [
                'provider' => 'stripe',
                'session_id' => (string) $sessionId,
            ],
            ip: $request->ip(),
        );

        // Enviar email de activación de plan (solo nueva suscripción, no upgrades)
        if ($previousSubscriptionStatus !== 'active') {
            try {
                $invoiceUrl = null;
                if (! empty($session->invoice)) {
                    $invoiceUrl = SubscriptionActivatedMail::resolveInvoiceUrl((string) $session->invoice);
                }

                $recipient = $clinic->ownerUser()->first()
                    ?? $clinic->users()->orderBy('id')->first();

                if ($recipient && filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($recipient->email)->send(
                        new SubscriptionActivatedMail(
                            clinicName: $clinic->name,
                            plan: (string) ($clinic->plan ?? 'basic'),
                            activatedAt: now()->format('d/m/Y H:i'),
                            invoiceUrl: $invoiceUrl,
                        )
                    );
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send activation email from confirmCheckout', [
                    'clinic_id' => $clinic->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
            $provider = $this->resolveCancellationProvider($providerName, $stripeSubId);

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
            $current = $clinic->saasSubscriptions()->orderByDesc('id')->first();
            if (! $current) {
                $clinic->saasSubscriptions()->create([
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_end' => now()->addMonth(),
                ]);
            } else {
                $current->status = 'active';
                $current->trial_ends_at = null;
                $current->current_period_end = now()->addMonth();
                $current->save();
            }

            $clinic->subscribed_at = now();
            $clinic->trial_ends_at = null;
            $clinic->subscription_status = 'active';
            $clinic->save();

            ActivityLogger::log(
                tenantId: (int) $clinic->id,
                userId: $user->id,
                event: $previousSubscriptionStatus === 'active' ? 'subscription_renewed' : 'subscription_created',
                description: $previousSubscriptionStatus === 'active'
                    ? 'Suscripcion renovada por fake success local'
                    : 'Suscripcion creada por fake success local',
                metadata: [
                    'provider' => 'fake',
                    'source' => 'billing.fake_success',
                ],
                ip: $request->ip(),
            );

            // Enviar email de activación de plan (solo nueva suscripción)
            if ($previousSubscriptionStatus !== 'active') {
                try {
                    $recipient = $clinic->ownerUser()->first()
                        ?? $clinic->users()->orderBy('id')->first();

                    if ($recipient && filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                        Mail::to($recipient->email)->send(
                            new SubscriptionActivatedMail(
                                clinicName: $clinic->name,
                                plan: (string) ($clinic->plan ?? 'basic'),
                                activatedAt: now()->format('d/m/Y H:i'),
                            )
                        );
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send activation email from fakeSuccess', [
                        'clinic_id' => $clinic->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
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

    private function resolveCancellationProvider(?string $subscriptionProvider, ?string $stripeSubscriptionId)
    {
        $providerName = strtolower(trim((string) ($subscriptionProvider ?? '')));
        $stripeSubscriptionId = trim((string) ($stripeSubscriptionId ?? ''));

        $looksLikeStripeSubscription = $stripeSubscriptionId !== '' && str_starts_with($stripeSubscriptionId, 'sub_');
        if ($providerName === 'stripe' || $looksLikeStripeSubscription) {
            return new StripePaymentProvider();
        }

        return Resolver::resolve();
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
