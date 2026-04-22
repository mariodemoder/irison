<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Services\PaymentProvider\Resolver;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\StripeClient;

class BillingController extends Controller
{
    public function createCheckout(Request $request)
    {
        $clinic = $request->user()->clinic;
        $amount = (int) ($request->input('amount', 2900)); // cents default 29.00

        $payment = BillingPayment::create([
            'clinic_id' => $clinic->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => 'pending',
            'provider' => config('billing.provider', 'fake'),
        ]);

        $provider = Resolver::resolve();
        $checkout = $provider->createCheckout([
            'payment_id' => $payment->id,
            'amount'     => $amount,
            'currency'   => 'EUR',
            'clinic_id'  => $clinic->id,
            'email'      => $request->user()->email,
        ]);

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

                if (! $current || $current->status !== 'active') {
                    $clinic->saasSubscriptions()->create([
                        'status' => 'active',
                        'trial_ends_at' => null,
                        'current_period_end' => now()->addMonth(),
                        'stripe_subscription_id' => $fakeSubId,
                    ]);
                } else {
                    $current->status = 'active';
                    $current->current_period_end = now()->addMonth();
                    $current->stripe_subscription_id = $current->stripe_subscription_id ?? $fakeSubId;
                    $current->save();
                }

                $clinic->subscribed_at = now();
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
            return response()->json(['message' => 'Clínica no disponible'], 403);
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
                'message' => 'No se encontró una sesión pendiente para validar',
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
                'message' => 'No se pudo validar la sesión de Stripe: ' . $e->getMessage(),
            ], 422);
        }

        $isPaid = (($session->payment_status ?? null) === 'paid')
            || (($session->status ?? null) === 'complete');

        if (! $isPaid) {
            return response()->json([
                'status' => 'pending',
                'message' => 'El pago todavía no figura como completado en Stripe',
            ]);
        }

        $paymentId = $session->metadata->payment_id ?? null;
        if ($paymentId) {
            BillingPayment::query()
                ->where('id', (int) $paymentId)
                ->where('clinic_id', $clinic->id)
                ->update(['status' => 'paid']);
        } else {
            BillingPayment::query()
                ->where('clinic_id', $clinic->id)
                ->where('provider', 'stripe')
                ->where('provider_ref', $sessionId)
                ->update(['status' => 'paid']);
        }

        $current = $clinic->saasSubscriptions()->where('status', 'active')->orderByDesc('id')->first();
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
            $current->current_period_end = now()->addMonth();
            $current->stripe_customer_id = $session->customer ?? $current->stripe_customer_id;
            $current->stripe_subscription_id = $session->subscription ?? $current->stripe_subscription_id;
            $current->save();
        }

        $clinic->subscribed_at = now();
        $clinic->subscription_provider = 'stripe';
        $clinic->subscription_reference = $sessionId;
        $clinic->save();

        return response()->json([
            'status' => 'active',
            'session_id' => $sessionId,
        ]);
    }

    // fake success route for local testing
    public function fakeSuccess(Request $request)
    {
        $user = $request->user();
        if ($user && $clinic = $user->clinic) {
            // marcar último payment pendiente como pagado
            try {
                $pending = \App\Models\BillingPayment::where('clinic_id', $clinic->id)
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

            // crear o actualizar suscripción activa
            $current = $clinic->saasSubscriptions()->orderByDesc('id')->first();
            if (! $current || $current->status !== 'active') {
                $clinic->saasSubscriptions()->create([
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_end' => now()->addMonth(),
                ]);
            } else {
                $current->status = 'active';
                $current->current_period_end = now()->addMonth();
                $current->save();
            }

            $clinic->subscribed_at = now();
            $clinic->save();
        }

        // Redirigir al dashboard de la SPA — la SPA debe consultar /api/me
        return redirect('/dashboard');
    }

    public function thankyou()
    {
        return view('billing.thankyou');
    }
}
