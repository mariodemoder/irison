<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionAccess
{
    public function handle(Request $request, Closure $next)
    {
        $clinic = currentClinic();
        $tenantStatus = strtolower(trim((string) ($clinic?->status ?? '')));

        // 1. Sin clínica
        if (! $clinic) {
            return response()->json(['message' => 'No clinic'], 403);
        }

        // 2. Clínica suspendida
        if ($clinic->isSuspended()) {
            return response()->json([
                'message' => 'Por el momento tu cuenta está suspendida. Contacta con Irison para más información.',
                'code' => 'CLINIC_SUSPENDED',
            ], 403);
        }

        // 3. Suscripción activa, trial vigente o cancelada con periodo pagado vigente -> OK
        if ($clinic->isSubscribed() || $clinic->isTrialActive() || $clinic->isInCancellationPaidWindow()) {
            return $next($request);
        }

        if ($tenantStatus === 'churned') {
            return response()->json([
                'message' => 'El trial finalizó sin conversión y la clínica fue marcada como churned',
                'code' => 'TRIAL_CHURNED',
            ], 403);
        }

        // 5. Semana de gracia en solo lectura -> permitir consultar datos existentes
        if ($clinic->isInReadOnlyNoTransactionsWindow() || $tenantStatus === 'trial_read_only') {
            if ($request->isMethodSafe() || $this->canStartPaidPlanWhileReadOnly($request)) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Modo solo lectura: durante esta semana no se permiten transacciones',
                'code' => 'CLINIC_READ_ONLY_NO_TRANSACTIONS',
            ], 403);
        }

        // 6. Pago fallido
        $status = strtolower(trim((string) ($clinic->subscription_status ?? 'inactive')));
        if ($status === 'past_due') {
            return response()->json([
                'message' => 'Payment required',
                'code' => 'SUBSCRIPTION_REQUIRED',
            ], 402);
        }

        // 7. Trial expirado, cancelada o inactiva fuera de gracia
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
