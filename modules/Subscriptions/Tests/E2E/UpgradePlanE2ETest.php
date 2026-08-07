<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Tests\E2E;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Subscriptions\Domain\Events\PaymentCompleted;
use Modules\Subscriptions\Domain\Events\SubscriptionUpgraded;
use Modules\Subscriptions\Tests\TestCase;

class UpgradePlanE2ETest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $owner;
    private SubscriptionRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useFakeProvider();
        $this->freezeCarbon('2026-05-18 10:00:00');
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Event::fake([PaymentCompleted::class, SubscriptionUpgraded::class]);
    }

    public function test_complete_trial_to_pro_upgrade_flow(): void
    {
        $this->clinic = $this->makeClinic([
            'name' => 'Clinica E2E Trial Basic',
            'email' => 'clinica-e2e@test.local',
            'subscription_status' => 'trial',
            'plan' => 'basic',
            'trial_ends_at' => now()->addDays(7),
            'max_users' => 1,
        ]);

        $this->makeSubscription($this->clinic, ['status' => 'trial']);
        $this->owner = $this->makeOwner($this->clinic, ['email' => 'owner-e2e@test.local']);

        // Las clínicas en trial no pueden solicitar el upgrade por la API (422),
        // el flujo real entra desde backoffice o directamente.
        $this->request = $this->makeUpgradeRequest($this->clinic, $this->owner);
        $this->assertSame('pending', $this->request->status);

        $this->approveFromBackoffice();

        $this->request->refresh();
        $this->assertSame('waiting_payment', $this->request->status);
        $this->assertNotNull($this->request->checkout_url);

        $this->completePaymentViaWebhook();

        $this->clinic->refresh();
        $this->request->refresh();

        $this->assertSame('completed', $this->request->status);
        $this->assertNotNull($this->request->completed_at);
        $this->assertSame('pro', (string) $this->clinic->plan);
        $this->assertSame(5, $this->clinic->max_users);
        $this->assertSame('active', $this->clinic->subscription_status);

        Event::assertDispatched(PaymentCompleted::class, function (PaymentCompleted $event) {
            return $event->request->id === $this->request->id;
        });
        Event::assertDispatched(SubscriptionUpgraded::class, function (SubscriptionUpgraded $event) {
            return $event->request->id === $this->request->id;
        });
    }

    private function approveFromBackoffice(): void
    {
        $admin = AdminUser::query()->create([
            'name' => 'Backoffice Billing',
            'email' => 'billing-admin@test.local',
            'password' => 'password123',
            'role' => AdminUser::ROLE_BILLING,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch('/backoffice/subscription-requests/' . $this->request->id . '/approve', [
                'reviewer_comments' => 'Solicitud aprobada para pago de upgrade.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Solicitud aprobada. Se ha generado el enlace de pago para la clínica.');
    }

    private function completePaymentViaWebhook(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

        $response = $this->postStripeWebhook([
            'id' => 'evt_e2e_upgrade_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $this->request->stripe_checkout_session_id,
                    'customer' => 'cus_e2e_123',
                    'subscription' => 'sub_e2e_123',
                    'customer_email' => 'owner-e2e@test.local',
                    'amount_total' => 8900,
                    'currency' => 'eur',
                    'metadata' => [
                        'clinic_id' => (string) $this->clinic->id,
                    ],
                ],
            ],
        ]);

        $response->assertOk();
    }
}
