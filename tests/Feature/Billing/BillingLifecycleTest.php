<?php

namespace Tests\Feature\Billing;

use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_trial_can_be_activated_to_paid_with_fake_checkout(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));
        config()->set('billing.provider', 'fake');

        [$clinic, $user] = $this->createClinicAndOwner([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(2),
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(2),
            'current_period_end' => now()->addDays(2),
            'stripe_subscription_id' => 'trial_sub_001',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/billing/checkout');

        $response
            ->assertOk()
            ->assertJsonPath('checkout.checkout_url', route('billing.fake.success'))
            ->assertJsonPath('payment.status', 'paid');

        $this->assertSame('active', $clinic->refresh()->subscription_status);

        $meResponse = $this->getJson('/api/me');

        $meResponse
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('read_only_no_transactions', false)
            ->assertJsonPath('can_transact', true);
    }

    public function test_expired_trial_enters_read_only_state_before_the_grace_window_expires(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createClinicAndOwner([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'trial',
            'trial_ends_at' => now()->subDay(),
            'current_period_end' => now()->addDays(6),
            'stripe_subscription_id' => 'trial_sub_002',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'trial_read_only')
            ->assertJsonPath('read_only_no_transactions', true)
            ->assertJsonPath('can_transact', false)
            ->assertJsonPath('code', 'TRIAL_READ_ONLY_NO_TRANSACTIONS');
    }

    public function test_expired_trial_becomes_blocked_after_the_grace_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createClinicAndOwner([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->subDays(8),
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'trial',
            'trial_ends_at' => now()->subDays(8),
            'current_period_end' => now()->subDays(1),
            'stripe_subscription_id' => 'trial_sub_003',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'blocked')
            ->assertJsonPath('read_only_no_transactions', false)
            ->assertJsonPath('can_transact', false)
            ->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');
    }

    public function test_canceled_clinic_keeps_full_access_while_paid_period_is_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createClinicAndOwner([
            'subscription_status' => 'canceled',
            'trial_ends_at' => null,
            'subscribed_at' => null,
            'subscription_provider' => 'fake',
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'canceled',
            'trial_ends_at' => null,
            'current_period_end' => now()->addDays(30),
            'stripe_subscription_id' => 'sub_canceled_001',
        ]);

        Sanctum::actingAs($user);

        $meResponse = $this->getJson('/api/me');

        $meResponse
            ->assertOk()
            ->assertJsonPath('status', 'canceled')
            ->assertJsonPath('read_only_no_transactions', false)
            ->assertJsonPath('can_transact', true)
            ->assertJsonPath('code', 'SUBSCRIPTION_CANCELED');

        $checkoutResponse = $this->postJson('/api/billing/checkout');

        $checkoutResponse
            ->assertOk()
            ->assertJsonPath('checkout.checkout_url', route('billing.fake.success'))
            ->assertJsonPath('payment.status', 'paid');

        $meAfterReactivation = $this->getJson('/api/me');

        $meAfterReactivation
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('read_only_no_transactions', false)
            ->assertJsonPath('can_transact', true);
    }

    public function test_canceled_clinic_enters_read_only_after_paid_period_ends(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createClinicAndOwner([
            'subscription_status' => 'canceled',
            'trial_ends_at' => null,
            'subscribed_at' => null,
            'subscription_provider' => 'fake',
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'canceled',
            'trial_ends_at' => null,
            'current_period_end' => now()->subDays(2),
            'stripe_subscription_id' => 'sub_canceled_002',
        ]);

        Sanctum::actingAs($user);

        $meResponse = $this->getJson('/api/me');

        $meResponse
            ->assertOk()
            ->assertJsonPath('status', 'canceled')
            ->assertJsonPath('read_only_no_transactions', true)
            ->assertJsonPath('can_transact', false)
            ->assertJsonPath('code', 'SUBSCRIPTION_CANCELED');
    }

    private function createClinicAndOwner(array $clinicOverrides = []): array
    {
        $clinic = new Clinic();
        $clinic->forceFill(array_merge([
            'name' => 'Clinica QA',
            'email' => 'clinic@test.local',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(2),
            'subscribed_at' => null,
            'subscription_provider' => 'fake',
        ], $clinicOverrides));
        $clinic->save();

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner QA',
            'email' => 'owner-' . $clinic->id . '@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        return [$clinic->refresh(), $user];
    }
}