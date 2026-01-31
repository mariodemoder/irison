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
            $email = $event->data->object->customer_email ?? null;

            if ($email) {
                $user = User::where('email', $email)->first();
                if ($user && $user->clinic) {
                    $clinic = $user->clinic;

                    // Crear registro de suscripción activa en la tabla subscriptions
                    \App\Models\Subscription::create([
                        'clinic_id' => $clinic->id,
                        'status' => 'active',
                        'trial_ends_at' => null,
                        'current_period_end' => Carbon::now()->addMonth(),
                        'stripe_customer_id' => $event->data->object->customer ?? null,
                        'stripe_subscription_id' => $event->data->object->subscription ?? null,
                    ]);

                    // Eliminar registros de trial previos
                    \App\Models\Subscription::where('clinic_id', $clinic->id)
                        ->where('status', 'trial')
                        ->delete();

                    // Limpiar columnas antiguas en clinics si existieran
                    $clinic->subscribed_at = null;
                    $clinic->subscription_provider = null;
                    $clinic->subscription_reference = null;
                    $clinic->save();
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
