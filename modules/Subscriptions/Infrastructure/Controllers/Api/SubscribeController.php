<?php

namespace Modules\Subscriptions\Infrastructure\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Backoffice\BackofficeAlertService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Subscriptions\Infrastructure\Mail\SubscriptionActivatedMail;
use Modules\Subscriptions\Infrastructure\Payment\Resolver;

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
        $clinic->status = 'active';
        $clinic->churned_at = null;
        $clinic->plan = (string) ($clinic->plan ?? 'basic');
        $clinic->max_users = \App\Models\Clinic::PLAN_USER_LIMITS[$clinic->plan] ?? 1;
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

        // Enviar email de activación de plan (solo nueva suscripción)
        if ($previousSubscriptionStatus !== 'active') {
            if (in_array($previousSubscriptionStatus, ['trial', 'trial_warning'], true)) {
                app(BackofficeAlertService::class)->trialConverted($clinic);
            }

            try {
                $invoiceUrl = Resolver::resolve()->resolveInvoiceUrl(
                    ! empty($subscription->latest_invoice) ? (string) $subscription->latest_invoice : null
                );

                Mail::to($request->user()->email)->send(
                    new SubscriptionActivatedMail(
                        clinicName: $clinic->name,
                        plan: (string) ($clinic->plan ?? 'basic'),
                        activatedAt: now()->format('d/m/Y H:i'),
                        invoiceUrl: $invoiceUrl,
                    )
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send activation email from subscribe', [
                    'clinic_id' => $clinic->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status'    => $subscription->stripe_status,
            'stripe_id' => $subscription->stripe_id,
        ], 201);
    }
}