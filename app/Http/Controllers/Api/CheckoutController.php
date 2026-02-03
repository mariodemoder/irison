<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Payment\ProviderResolver;
use App\Models\Payment;
use Carbon\Carbon;

class CheckoutController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->clinic) {
            return response()->json(['message' => 'No clinic assigned'], 403);
        }

        $clinic = $user->clinic;

        // por ahora plan professional
        $plan = config('billing.plan.professional');
        $amountCents = intval(($plan['amount'] ?? 0) * 100);

        $provider = ProviderResolver::resolve();

        // crear payment pending
        $payment = Payment::create([
            'clinic_id' => $clinic->id,
            'amount' => $amountCents,
            'currency' => $plan['currency'] ?? 'EUR',
            'status' => 'pending',
            'provider' => $provider->getName(),
            'provider_ref' => null,
        ]);

        // pedir checkout al provider
        $checkoutData = $provider->createCheckout([
            'payment_id' => $payment->id,
            'amount' => $amountCents,
            'currency' => $plan['currency'] ?? 'EUR',
            'clinic_id' => $clinic->id,
        ]);

        // guardar provider_ref si viene
        if (! empty($checkoutData['provider_ref'])) {
            $payment->provider_ref = $checkoutData['provider_ref'];
            $payment->save();
        }

        return response()->json([
            'status' => 'ok',
            'checkout' => $checkoutData,
            'payment' => $payment,
        ]);
    }
}
