<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class MeController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        $status = 'blocked';
        if ($clinic) {
            $status = match (true) {
                $clinic->isSubscribed() => 'active',
                $clinic->isTrialActive() => 'trial',
                default => 'blocked',
            };
        }

        $trialEnds = null;
        if ($clinic) {
            $sub = $clinic->currentSubscription();
            $trialEnds = $sub ? $sub->trial_ends_at : null;
        }

        if ($clinic) {
            $clinic->load('subscriptions');
        }

        $payload = [
            'user' => $user,
            'clinic' => $clinic,
            'status' => $status,
            'trial_ends_at' => $trialEnds,
        ];

        if ($status === 'blocked') {
            $payload['code'] = 'SUBSCRIPTION_REQUIRED';
            $payload['message'] = 'Tu periodo de prueba ha finalizado';
        }

        return response()->json($payload);

    }
}
