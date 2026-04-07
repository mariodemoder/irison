<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FakeSubscribeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->clinic) {
            return response()->json(['message' => 'No clinic assigned'], 403);
        }

        $clinic = $user->clinic;
        $amount = (int) $request->input('amount', 2900);

        $result = DB::transaction(function () use ($clinic, $amount) {
            $payment = BillingPayment::create([
                'clinic_id' => $clinic->id,
                'amount' => $amount,
                'currency' => 'EUR',
                'status' => 'paid',
                'provider' => 'fake',
                'provider_ref' => 'fake_' . uniqid(),
            ]);

            // Cerrar cualquier suscripción activa previa para esta clínica
            $activeSubs = \App\Models\Subscription::where('clinic_id', $clinic->id)
                ->where('status', 'active')
                ->get();

            foreach ($activeSubs as $s) {
                $s->status = 'canceled';
                $s->current_period_end = Carbon::now();
                $s->save();
            }

            // Intentar reutilizar una suscripción de trial existente (UPDATE)
            $subscription = \App\Models\Subscription::where('clinic_id', $clinic->id)
                ->where('status', 'trial')
                ->latest()
                ->first();

            if ($subscription) {
                $subscription->status = 'active';
                $subscription->trial_ends_at = null;
                $subscription->current_period_end = Carbon::now()->addMonth();
                $subscription->stripe_customer_id = $subscription->stripe_customer_id ?? null;
                $subscription->stripe_subscription_id = $subscription->stripe_subscription_id ?? 'fake-' . uniqid();
                $subscription->save();
            } else {
                // Si no existe trial previo, crear nueva suscripción
                $subscription = \App\Models\Subscription::create([
                    'clinic_id' => $clinic->id,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_end' => Carbon::now()->addMonth(),
                    'stripe_customer_id' => null,
                    'stripe_subscription_id' => 'fake-' . uniqid(),
                ]);
            }

            // Marcar clínica como suscrita y guardar referencias
            $clinic->subscribed_at = Carbon::now();
            $clinic->subscription_provider = 'fake';
            $clinic->subscription_reference = $subscription->stripe_subscription_id;
            $clinic->save();

            $clinic->load('saasSubscriptions');

            $status = match (true) {
                $clinic->isSubscribed() => 'active',
                $clinic->isTrialActive() => 'trial',
                default => 'blocked',
            };

            return [
                'payment' => $payment,
                'subscription' => $subscription,
                'clinic' => $clinic,
                'status' => $status,
            ];
        });

        $payment = $result['payment'];
        $subscription = $result['subscription'];
        $clinic = $result['clinic'];
        $status = $result['status'];

        return response()->json([
            'status' => 'ok',
            'payment' => $payment,
            'clinic' => $clinic,
            'status_clinic' => $status,
            'subscribed_at' => $clinic->subscribed_at,
            'current_period_end' => $subscription->current_period_end,
        ]);
    }
}
