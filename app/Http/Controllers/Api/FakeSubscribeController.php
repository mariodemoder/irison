<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $result = DB::transaction(function () use ($clinic) {
            // Cerrar cualquier suscripción activa previa para esta clínica
            $activeSubs = \App\Models\Subscription::where('clinic_id', $clinic->id)
                ->where('status', 'active')
                ->get();

            foreach ($activeSubs as $s) {
                $s->status = 'canceled';
                $s->current_period_end = Carbon::now();
                $s->save();
            }

            // Eliminar cualquier registro de trial existente para esta clínica
            \App\Models\Subscription::where('clinic_id', $clinic->id)
                ->where('status', 'trial')
                ->delete();

            $subscription = \App\Models\Subscription::create([
                'clinic_id' => $clinic->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_end' => Carbon::now()->addMonth(),
                'stripe_customer_id' => null,
                'stripe_subscription_id' => 'fake-' . uniqid(),
            ]);

            // Limpiar campos en clinics para que subscriptions sea la fuente de verdad
            $clinic->subscribed_at = null;
            $clinic->subscription_provider = null;
            $clinic->subscription_reference = null;
            $clinic->save();

            $clinic->load('subscriptions');

            $status = match (true) {
                $clinic->isSubscribed() => 'active',
                $clinic->isTrialActive() => 'trial',
                default => 'blocked',
            };

            return [
                'subscription' => $subscription,
                'clinic' => $clinic,
                'status' => $status,
            ];
        });

        $subscription = $result['subscription'];
        $clinic = $result['clinic'];
        $status = $result['status'];

        return response()->json([
            'status' => 'ok',
            'clinic' => $clinic,
            'status_clinic' => $status,
            'trial_ends_at' => $subscription->trial_ends_at,
        ]);
    }
}
