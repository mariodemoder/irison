<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Subscription;
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
                        $existing = Subscription::where('clinic_id', $clinic->id)
                        ->where('status', 'active')
                        ->orderByDesc('id')
                        ->first();

                    if ($existing) {
                        $existing->current_period_end    = Carbon::now()->addMonth();
                        $existing->stripe_customer_id    = $session->customer ?? $existing->stripe_customer_id;
                        $existing->stripe_subscription_id = $session->subscription ?? $existing->stripe_subscription_id;
                        $existing->save();
                    } else {
                            Subscription::create([
                            'clinic_id'              => $clinic->id,
                            'status'                 => 'active',
                            'trial_ends_at'          => null,
                            'current_period_end'     => Carbon::now()->addMonth(),
                            'stripe_customer_id'     => $session->customer ?? null,
                            'stripe_subscription_id' => $session->subscription ?? null,
                        ]);
                    }

                    // Eliminar registros de trial previos
                        Subscription::where('clinic_id', $clinic->id)
                        ->where('status', 'trial')
                        ->delete();

                    $clinic->subscribed_at         = Carbon::now();
                    $clinic->subscription_status   = 'active';
                    $clinic->subscription_provider = 'stripe';
                    $clinic->save();
                }
            }
            } elseif ($event->type === 'invoice.payment_succeeded') {
                $invoice = $event->data->object;
                $customerId = (string) ($invoice->customer ?? '');
                if ($customerId !== '') {
                    $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
                    if ($clinic) {
                        $clinic->subscription_status = 'active';
                        $clinic->subscribed_at = $clinic->subscribed_at ?? Carbon::now();
                        $clinic->save();
                    }
                }
            } elseif ($event->type === 'invoice.payment_failed') {
                $invoice = $event->data->object;
                $customerId = (string) ($invoice->customer ?? '');
                if ($customerId !== '') {
                    $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
                    if ($clinic) {
                        $clinic->subscription_status = 'past_due';
                        $clinic->save();
                    }
                }
            } elseif ($event->type === 'customer.subscription.deleted') {
                $subscription = $event->data->object;
                $customerId = (string) ($subscription->customer ?? '');
                if ($customerId !== '') {
                    $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
                    if ($clinic) {
                        $clinic->subscription_status = 'canceled';
                        $clinic->subscribed_at = null;
                        $clinic->save();
                    }
                }
            } elseif ($event->type === 'customer.subscription.updated') {
                $subscription = $event->data->object;
                $customerId = (string) ($subscription->customer ?? '');
                if ($customerId !== '') {
                    $clinic = Clinic::query()->where('stripe_id', $customerId)->first();
                    if ($clinic) {
                        $stripeStatus = strtolower((string) ($subscription->status ?? 'inactive'));
                        $clinic->subscription_status = match ($stripeStatus) {
                            'active', 'trialing' => 'active',
                            'past_due' => 'past_due',
                            'canceled', 'cancelled', 'unpaid', 'incomplete_expired' => 'canceled',
                            default => 'inactive',
                        };
                        if ($clinic->subscription_status !== 'active') {
                            $clinic->subscribed_at = null;
                        }
                        $clinic->save();
                    }
                }
        }

        return response()->json(['status' => 'ok']);
    }
}
