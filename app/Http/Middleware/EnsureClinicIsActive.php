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

        if ($clinic->isTrialActive() || $clinic->isSubscribed()) {
            return $next($request);
        }

        if ($clinic->isInReadOnlyNoTransactionsWindow()) {
            if ($request->isMethodSafe() || $this->canStartPaidPlanWhileReadOnly($request)) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Modo solo lectura: durante esta semana no se permiten transacciones',
                'code' => 'CLINIC_READ_ONLY_NO_TRANSACTIONS',
            ], 403);
        }

        if (! $clinic->isTrialActive() && ! $clinic->isSubscribed()) {
            return response()->json([
                'message' => 'Tu periodo de prueba ha finalizado',
                'code' => 'SUBSCRIPTION_REQUIRED',
            ], 403);
        }

        return $next($request);
    }

    private function canStartPaidPlanWhileReadOnly(Request $request): bool
    {
        if ($request->method() !== 'POST') {
            return false;
        }

        return $request->is('api/billing/checkout')
            || $request->is('billing/checkout')
            || $request->is('api/stripe/checkout')
            || $request->is('stripe/checkout')
            || $request->is('api/subscribe/fake')
            || $request->is('subscribe/fake');
    }
}
