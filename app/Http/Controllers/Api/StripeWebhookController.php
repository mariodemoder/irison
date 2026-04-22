<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Carbon\Carbon;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sig,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            Log::warning('Stripe webhook verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $email   = $session->customer_email ?? null;

            // Marcar BillingPayment como pagado si viene en metadata
            $paymentId = $session->metadata->payment_id ?? null;
            if ($paymentId) {
                \App\Models\BillingPayment::where('id', $paymentId)
                    ->whereIn('status', ['pending'])
                    ->update(['status' => 'paid']);
            }

            if ($email) {
                $user = User::where('email', $email)->first();
                if ($user && $user->clinic) {
                    $clinic = $user->clinic;

                    // Crear o actualizar suscripción activa
                    $existing = \App\Models\Subscription::where('clinic_id', $clinic->id)
                        ->where('status', 'active')
                        ->orderByDesc('id')
                        ->first();

                    if ($existing) {
                        $existing->current_period_end    = Carbon::now()->addMonth();
                        $existing->stripe_customer_id    = $session->customer ?? $existing->stripe_customer_id;
                        $existing->stripe_subscription_id = $session->subscription ?? $existing->stripe_subscription_id;
                        $existing->save();
                    } else {
                        \App\Models\Subscription::create([
                            'clinic_id'              => $clinic->id,
                            'status'                 => 'active',
                            'trial_ends_at'          => null,
                            'current_period_end'     => Carbon::now()->addMonth(),
                            'stripe_customer_id'     => $session->customer ?? null,
                            'stripe_subscription_id' => $session->subscription ?? null,
                        ]);
                    }

                    // Eliminar registros de trial previos
                    \App\Models\Subscription::where('clinic_id', $clinic->id)
                        ->where('status', 'trial')
                        ->delete();

                    $clinic->subscribed_at         = Carbon::now();
                    $clinic->subscription_provider = 'stripe';
                    $clinic->save();
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
