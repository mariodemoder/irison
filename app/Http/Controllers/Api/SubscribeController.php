<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

class SubscribeController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $clinic = $request->user()->clinic;
        $previousSubscriptionStatus = strtolower(trim((string) ($clinic->subscription_status ?? 'inactive')));
        $priceId = config('services.stripe.price_id');

        if (! $priceId || str_contains($priceId, 'xxx')) {
            return response()->json(['message' => 'STRIPE_PRICE_ID no configurado'], 500);
        }

        // Verificar antes de llamar a Stripe: evita cobros dobles
            if ($clinic->subscribed('default')) {
                return response()->json(['message' => 'Ya tienes una suscripcion activa'], 409);
        }

        // Crear o actualizar customer en Stripe y guardar stripe_id en clinics
        if (! $clinic->stripe_id) {
            $clinic->createAsStripeCustomer([
                'email' => $request->user()->email,
                'name'  => $clinic->name,
            ]);
        }

        // Asociar método de pago como default
        $clinic->updateDefaultPaymentMethod($request->payment_method);

        $subscription = $clinic
            ->newSubscription('default', $priceId)
            ->create($request->payment_method);

        $clinic->subscription_status = 'active';
        $clinic->subscribed_at = now();
        $clinic->save();

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: (int) $request->user()->id,
            event: $previousSubscriptionStatus === 'active' ? 'subscription_renewed' : 'subscription_created',
            description: $previousSubscriptionStatus === 'active'
                ? 'Suscripcion renovada desde endpoint subscribe'
                : 'Suscripcion creada desde endpoint subscribe',
            metadata: [
                'provider' => 'stripe',
                'stripe_id' => (string) ($subscription->stripe_id ?? ''),
            ],
            ip: $request->ip(),
        );

        return response()->json([
            'status'    => $subscription->stripe_status,
            'stripe_id' => $subscription->stripe_id,
        ], 201);
    }
}