<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureClinicIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->clinic) {
            return response()->json(['message' => 'No clinic assigned'], 403);
        }

        $clinic = $user->clinic;

        if (! $clinic->isTrialActive() && ! $clinic->isSubscribed()) {
            return response()->json([
                'message' => 'Tu periodo de prueba ha finalizado',
                'code' => 'SUBSCRIPTION_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
