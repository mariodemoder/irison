<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Services\PaymentProvider\Resolver;
use Illuminate\Http\Request;

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
            'amount' => $amount,
            'currency' => 'EUR',
            'clinic_id' => $clinic->id,
        ]);

        $payment->provider_ref = $checkout['provider_ref'] ?? null;
        $payment->save();

        // For fake provider, consider payment completed immediately and
        // create/activate subscription for the clinic.
        try {
            if (method_exists($provider, 'getName') && $provider->getName() === 'fake') {
                $payment->status = 'paid';
                $payment->save();

                $current = $clinic->subscriptions()->orderByDesc('id')->first();
                $fakeSubId = 'fake-' . uniqid();

                if (! $current || $current->status !== 'active') {
                    $clinic->subscriptions()->create([
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
            $current = $clinic->subscriptions()->orderByDesc('id')->first();
            if (! $current || $current->status !== 'active') {
                $clinic->subscriptions()->create([
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
