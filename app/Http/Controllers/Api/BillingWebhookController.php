<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use Carbon\Carbon;

class BillingWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->all();

        // Simple: esperar provider_ref y estado
        $providerRef = $payload['provider_ref'] ?? null;
        $event = $payload['event'] ?? null;

        if (! $providerRef) {
            return response()->json(['message' => 'Missing provider_ref'], 400);
        }

        $payment = Payment::where('provider_ref', $providerRef)->first();
        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Simular: event = payment_success
        if ($event === 'payment_success' || ($payload['status'] ?? '') === 'paid') {
            $payment->status = 'paid';
            $payment->save();

            // Activar suscripción: actualizar/crear registro Subscription similar a FakeSubscribeController
            $clinic = $payment->clinic;

            $sub = \App\Models\Subscription::where('clinic_id', $clinic->id)
                ->where('status', 'trial')
                ->latest()
                ->first();

            if ($sub) {
                $sub->status = 'active';
                $sub->trial_ends_at = null;
                $sub->current_period_end = Carbon::now()->addMonth();
                $sub->save();
            } else {
                $sub = \App\Models\Subscription::create([
                    'clinic_id' => $clinic->id,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_end' => Carbon::now()->addMonth(),
                    'stripe_customer_id' => null,
                    'stripe_subscription_id' => $payment->provider . '-' . $payment->id,
                ]);
            }

            // Opcional: actualizar clinic subscribed_at
            $clinic->subscribed_at = Carbon::now();
            $clinic->save();

            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'ignored']);
    }

    // Ruta helper para fake complete (GET desde navegador)
    public function fakeComplete(Request $request)
    {
        $ref = $request->query('ref');
        if (! $ref) {
            abort(400, 'missing ref');
        }

        // Buscar payment o crear uno temporal
        $payment = Payment::where('provider_ref', $ref)->first();
        if ($payment) {
            // marcar pago como pagado
            $payment->status = 'paid';
            $payment->save();

            // disparar la lógica de activación (reusar __invoke)
            $this->__invoke(new Request(array_merge($request->all(), ['provider_ref' => $ref, 'status' => 'paid'])));
        }

        // Redirigir al frontend
        return redirect('/dashboard');
    }
}
