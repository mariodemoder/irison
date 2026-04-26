<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionAccess
{
    public function handle(Request $request, Closure $next)
    {
        $clinic = currentClinic();

        // 1. Sin clínica
        if (! $clinic) {
            return response()->json(['message' => 'No clinic'], 403);
        }

        // 2. Suscripción activa o trial vigente -> OK
        if ($clinic->isSubscribed() || $clinic->isTrialActive()) {
            return $next($request);
        }

        // 3. Semana de gracia en solo lectura -> permitir consultar datos existentes
        if ($clinic->isInReadOnlyNoTransactionsWindow()) {
            if ($request->isMethodSafe() || $this->canStartPaidPlanWhileReadOnly($request)) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Modo solo lectura: durante esta semana no se permiten transacciones',
                'code' => 'CLINIC_READ_ONLY_NO_TRANSACTIONS',
            ], 403);
        }

        // 4. Pago fallido
        $status = strtolower(trim((string) ($clinic->subscription_status ?? 'inactive')));
        if ($status === 'past_due') {
            return response()->json([
                'message' => 'Payment required',
                'code' => 'SUBSCRIPTION_REQUIRED',
            ], 402);
        }

        // 5. Trial expirado, cancelada o inactiva fuera de gracia
        return response()->json([
            'message' => 'Tu periodo de prueba ha finalizado',
            'code' => 'SUBSCRIPTION_REQUIRED',
        ], 403);
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
