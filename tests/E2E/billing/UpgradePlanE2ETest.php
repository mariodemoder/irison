<?php

namespace Tests\E2E\Billing;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpgradePlanE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));
        config()->set('billing.provider', 'fake');
    }

    public function test_complete_basic_to_pro_upgrade_flow(): void
    {
        $this->prepareTestingEnvironment();

        $this->createClinicWithBasicPlan();
        $this->createBackofficeAdmin();
        $this->createOwnerUser();

        $this->createSubscriptionRequest();
        $this->approveSubscriptionRequestAsBackoffice();
        $this->completeCheckoutPayment();
        $this->verifyPlanUpgradeSuccess();

        $this->markTestAsPassed();
    }

    private function prepareTestingEnvironment(): void
    {
        config()->set('billing.provider', 'fake');
    }

    private function createClinicWithBasicPlan(): void
    {
        $this->clinic = Clinic::create([
            'name' => 'Clinica Test Basic',
            'email' => 'clinica-basic@test.local',
            'subscription_status' => 'active',
            'plan' => 'basic',
            'subscribed_at' => now(),
            'subscription_provider' => 'fake',
            'max_users' => 1,
        ]);

        $this->createOwnerUser();

        $this->subscription = Subscription::create([
            'clinic_id' => $this->clinic->id,
            'status' => 'active',
            'current_period_end' => now()->addDays(30),
            'stripe_subscription_id' => 'sub_basic_test',
        ]);

        Sanctum::actingAs($this->user);
    }

    private function createBackofficeAdmin(): void
    {
        $this->backofficeAdmin = AdminUser::create([
            'name' => 'Backoffice Admin',
            'email' => 'admin@test.local',
            'password' => 'password123',
            'role' => AdminUser::ROLE_BILLING,
            'is_active' => true,
        ]);
    }

    private function createOwnerUser(): void
    {
        $this->user = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Owner Test',
            'email' => 'owner@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);
    }

    private function createSubscriptionRequest(): SubscriptionRequest
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/settings/subscription/request', [
                'requested_plan' => 'pro',
                'comments' => 'Necesitamos 5 usuarios para nuestra clínica.',
            ]);

        $response->assertCreated();

        $this->subscriptionRequest = SubscriptionRequest::find($response->json('id'));

        return $this->subscriptionRequest;
    }

    private function approveSubscriptionRequestAsBackoffice(): void
    {
        $this->actingAs($this->backofficeAdmin, 'admin')
            ->patch('/backoffice/subscription-requests/' . $this->subscriptionRequest->id . '/approve', [
                'reviewer_comments' => 'Solicitud aprobada automáticamente.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Solicitud aprobada. Se ha generado el enlace de pago para la clínica.');
    }

    private function completeCheckoutPayment(): array
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/billing/checkout');

        $response->assertOk()
            ->assertJsonPath('checkout.checkout_url', route('billing.fake.success'))
            ->assertJsonPath('payment.status', 'paid');

        return $response->json();
    }

    private function verifyPlanUpgradeSuccess(): void
    {
        $this->clinic->refresh();
        $this->user->refresh();
        $this->subscriptionRequest->refresh();

        $this->assertSame('pro', (string) $this->clinic->plan);
        $this->assertSame(5, $this->clinic->max_users);
        $this->assertSame('active', $this->clinic->subscription_status);
        $this->assertSame('waiting_payment', $this->subscriptionRequest->status);
        $this->assertNotNull($this->subscriptionRequest->checkout_url);

        $meResponse = $this->actingAs($this->user)
            ->getJson('/api/me');
        $meResponse->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('can_transact', true);

        $this->assertSame('paid', $this->subscriptionRequest->status);
        $this->assertNotNull($this->subscriptionRequest->completed_at);
    }

    private function markTestAsPassed(): void
    {
        $this->assertTrue(true);
    }
}