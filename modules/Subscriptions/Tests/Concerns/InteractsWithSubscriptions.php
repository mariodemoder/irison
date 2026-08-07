<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Tests\Concerns;

use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Carbon\Carbon;

trait InteractsWithSubscriptions
{
    protected function useFakeProvider(): void
    {
        config()->set('billing.provider', 'fake');
    }

    protected function freezeCarbon(string $at = '2026-05-18 10:00:00'): void
    {
        Carbon::setTestNow(Carbon::parse($at));
    }

    protected function unfreezeCarbon(): void
    {
        Carbon::setTestNow();
    }

    protected function makeClinic(array $overrides = []): Clinic
    {
        $clinic = new Clinic();
        $clinic->forceFill(array_merge([
            'name' => 'Clinica Subscriptions',
            'email' => 'clinic@test.local',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(7),
            'subscribed_at' => null,
            'subscription_provider' => 'fake',
            'status' => 'active',
            'plan' => 'basic',
            'max_users' => 1,
        ], $overrides));
        $clinic->save();

        return $clinic->refresh();
    }

    protected function makeOwner(Clinic $clinic, array $overrides = []): User
    {
        return User::create(array_merge([
            'clinic_id' => $clinic->id,
            'name' => 'Owner ' . $clinic->id,
            'email' => 'owner-' . $clinic->id . '@test.local',
            'password' => 'password',
            'role' => 'owner',
        ], $overrides));
    }

    protected function makeSubscription(Clinic $clinic, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'clinic_id' => $clinic->id,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(7),
            'current_period_end' => now()->addDays(30),
        ], $overrides));
    }

    protected function makeUpgradeRequest(Clinic $clinic, User $owner, array $overrides = []): SubscriptionRequest
    {
        return SubscriptionRequest::create(array_merge([
            'clinic_id' => $clinic->id,
            'current_plan' => (string) $clinic->plan,
            'requested_plan' => 'pro',
            'status' => 'pending',
            'requested_by' => $owner->id,
        ], $overrides));
    }

    protected function postStripeWebhook(array $payload, string $secret = 'whsec_test_secret')
    {
        config()->set('billing.provider', 'stripe');
        config()->set('services.stripe.secret', 'sk_test_webhook_placeholder');

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $json;
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = 't=' . $timestamp . ',v1=' . $signature;

        return $this->call(
            'POST',
            '/api/billing/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $header,
                'CONTENT_TYPE' => 'application/json',
            ],
            $json
        );
    }
}
