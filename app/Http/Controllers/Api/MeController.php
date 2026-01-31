<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class MeController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        $active = false;
        if ($clinic) {
            $active = $clinic->isTrialActive() || $clinic->isSubscribed();
        }

        return response()->json([
            'user' => $user,
            'clinic' => $clinic,
            'trial_active' => $active,
        ]);
    }
}
