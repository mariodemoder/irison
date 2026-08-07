<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Application\Services;

use App\Models\Clinic;
use App\Models\Subscription;
use App\Services\Backoffice\BackofficeAlertService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Subscriptions\Infrastructure\Mail\SubscriptionActivatedMail;

/**
 * Centraliza los efectos de activación de una suscripción una vez confirmado
 * el pago, independientemente del proveedor: crea/actualiza la suscripción,
 * marca la clínica como activa, registra actividad, dispara la conversión de
 * trial y envía el email de activación de plan.
 */
class SubscriptionActivationService
{
    /**
     * @param  array  $options  {
     *                          user_id?: ?int
     *                          provider?: string
     *                          subscription_reference?: ?string
     *                          stripe_customer_id?: ?string
     *                          stripe_subscription_id?: ?string
     *                          invoice_url?: ?string
     *                          source?: string
     *                          previous_status?: ?string
     *                          metadata?: array
     *                          ip?: ?string
     *                          plan?: ?string
     *                          }
     */
    public function activateClinic(Clinic $clinic, array $options = []): void
    {
        $provider = (string) ($options['provider'] ?? 'fake');
        $subscriptionReference = $options['subscription_reference'] ?? null;
        $stripeCustomerId = $options['stripe_customer_id'] ?? null;
        $stripeSubscriptionId = $options['stripe_subscription_id'] ?? null;
        $invoiceUrl = $options['invoice_url'] ?? null;
        $source = (string) ($options['source'] ?? 'checkout');
        $userId = (int) ($options['user_id'] ?? 0);
        $ip = $options['ip'] ?? null;
        $previousStatus = strtolower(trim((string) ($options['previous_status'] ?? ($clinic->subscription_status ?? 'inactive'))));
        $metadata = (array) ($options['metadata'] ?? []);
        $plan = (string) ($options['plan'] ?? $clinic->plan ?? 'basic');
        if (! in_array($plan, ['basic', 'pro', 'enterprise'], true)) {
            $plan = 'basic';
        }

        $subscription = $this->updateOrCreateSubscription(
            $clinic,
            $stripeCustomerId !== '' ? (string) $stripeCustomerId : null,
            $stripeSubscriptionId !== '' ? (string) $stripeSubscriptionId : null,
        );

        $clinic->subscribed_at = now();
        $clinic->trial_ends_at = null;
        $clinic->subscription_status = 'active';
        $clinic->subscription_provider = $provider;
        $clinic->subscription_reference = $subscriptionReference !== '' && $subscriptionReference !== null
            ? (string) $subscriptionReference
            : ($subscription->stripe_subscription_id ?? (string) $subscription->id);

        $clinic->status = 'active';
        $clinic->churned_at = null;
        $clinic->plan = $plan;
        $clinic->max_users = Clinic::PLAN_USER_LIMITS[$plan] ?? 1;

        if ($stripeCustomerId !== '' && $stripeCustomerId !== null) {
            $clinic->stripe_id = (string) $stripeCustomerId;
            $clinic->stripe_customer_id = (string) $stripeCustomerId;
        }
        $clinic->save();

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: $userId > 0 ? $userId : null,
            event: $previousStatus === 'active' ? 'subscription_renewed' : 'subscription_created',
            description: $previousStatus === 'active'
                ? 'Suscripcion renovada ('.$source.')'
                : 'Suscripcion creada ('.$source.')',
            metadata: array_merge(['provider' => $provider], $metadata),
            ip: $ip,
        );

        if (in_array($previousStatus, ['trial', 'trial_warning'], true)) {
            app(BackofficeAlertService::class)->trialConverted($clinic);
        }

        if ($previousStatus !== 'active') {
            $this->sendActivationEmail($clinic, $invoiceUrl);
        }
    }

    private function updateOrCreateSubscription(Clinic $clinic, ?string $stripeCustomerId, ?string $stripeSubscriptionId): Subscription
    {
        $subscription = $clinic->saasSubscriptions()
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (! $subscription) {
            $subscription = $clinic->saasSubscriptions()->orderByDesc('id')->first();
        }

        if (! $subscription) {
            return Subscription::create([
                'clinic_id' => $clinic->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_end' => now()->addMonth(),
                'stripe_customer_id' => $stripeCustomerId,
                'stripe_subscription_id' => $stripeSubscriptionId ?: 'fake-'.uniqid(),
            ]);
        }

        $subscription->status = 'active';
        $subscription->trial_ends_at = null;
        $subscription->current_period_end = now()->addMonth();
        if ($stripeCustomerId !== null) {
            $subscription->stripe_customer_id = $stripeCustomerId;
        }
        if ($stripeSubscriptionId !== null) {
            $subscription->stripe_subscription_id = $stripeSubscriptionId;
        }
        $subscription->save();

        return $subscription;
    }

    private function sendActivationEmail(Clinic $clinic, ?string $invoiceUrl): void
    {
        try {
            $recipient = $clinic->ownerUser()->first()
                ?? $clinic->users()->orderBy('id')->first();

            if ($recipient && filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($recipient->email)->queue(
                    new SubscriptionActivatedMail(
                        clinicName: $clinic->name,
                        plan: (string) ($clinic->plan ?? 'basic'),
                        activatedAt: now()->format('d/m/Y H:i'),
                        invoiceUrl: $invoiceUrl,
                    )
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send activation email', [
                'clinic_id' => $clinic->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
