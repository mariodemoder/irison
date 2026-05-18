<?php

namespace App\Http\Controllers;

use App\Mail\SubscriptionCanceledInternalMail;
use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Services\PaymentProvider\Resolver;
use App\Services\PaymentProvider\StripePaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Stripe\Stripe;
use Stripe\StripeClient;

class BillingController extends Controller
{
    public function createCheckout(Request $request)
    {
        $clinic = $request->user()->clinic;
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
        $clinic->save();

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

        $provider = $this->resolveCancellationProvider($clinic->subscription_provider, $subscription->stripe_subscription_id);

        try {
            $provider->cancelSubscription([
                'clinic_id' => (int) $clinic->id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                'subscription_reference' => $clinic->subscription_reference,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo cancelar la suscripcion: ' . $e->getMessage(),
            ], 422);
        }

        $subscription->status = 'canceled';
        $subscription->current_period_end = now()->addDays(7);
        $subscription->save();

        $clinic->subscribed_at = null;
        $clinic->subscription_status = 'canceled';
        $clinic->save();

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
            Mail::to($recipient)->send(new SubscriptionCanceledInternalMail(
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
}
