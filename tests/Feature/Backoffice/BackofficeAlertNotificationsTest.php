<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Trials\TrialLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Modules\Notifications\Backoffice\Notifications\BackofficeAlertNotification;
use Tests\TestCase;

class BackofficeAlertNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_expiry_sends_backoffice_alert(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();

        $clinic = Clinic::create([
            'name' => 'Clinica Trial Vencido',
            'legal_name' => 'Clinica Trial Vencido',
            'email' => 'trial-expired@irison.test',
            'trial_ends_at' => Carbon::now(),
            'subscription_status' => 'trial_warning',
            'status' => 'trial_warning',
        ]);
        $clinic->created_at = Carbon::now()->subDays(30);
        $clinic->updated_at = Carbon::now()->subDays(30);
        $clinic->save();

        app(TrialLifecycleService::class)->process(now());

        $clinic->refresh();
        $this->assertSame('trial_read_only', $clinic->status);

        $notification = DatabaseNotification::query()->firstOrFail();
        $this->assertSame(BackofficeAlertNotification::class, $notification->type);
        $this->assertSame($admin->id, $notification->notifiable_id);
        $this->assertSame('trial_expired', $notification->data['type']);
        $this->assertSame($clinic->id, (int) $notification->data['clinic_id']);
    }

    public function test_trial_to_paid_via_fake_checkout_sends_backoffice_alert(): void
    {
        config()->set('billing.provider', 'fake');
        $admin = $this->createAdmin();

        [$clinic, $user] = $this->createClinicAndOwner([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(2),
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(2),
            'current_period_end' => now()->addDays(2),
            'stripe_subscription_id' => 'trial_sub_alert_001',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/checkout')->assertOk();

        $this->assertSame('active', $clinic->refresh()->subscription_status);

        $notification = DatabaseNotification::query()->where('data', 'like', '%trial_converted%')->firstOrFail();
        $this->assertSame($admin->id, $notification->notifiable_id);
        $this->assertSame('trial_converted', $notification->data['type']);
        $this->assertSame($clinic->id, (int) $notification->data['clinic_id']);
    }

    public function test_subscription_cancelled_via_webhook_sends_backoffice_alert(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');
        $admin = $this->createAdmin();

        $clinic = Clinic::create([
            'name' => 'Clinica Webhook Cancel',
            'email' => 'webhook-cancel@irison.test',
            'subscription_status' => 'active',
            'stripe_id' => 'cus_webhook_cancel_001',
        ]);

        $payload = json_encode([
            'id' => 'evt_cancel_alert_1',
            'object' => 'event',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_webhook_cancel_001',
                    'customer' => 'cus_webhook_cancel_001',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->postStripeWebhook($payload, 'whsec_test_secret');
        $response->assertOk();

        $clinic->refresh();
        $this->assertSame('canceled', $clinic->subscription_status);

        $notification = DatabaseNotification::query()->where('data', 'like', '%subscription_cancelled%')->firstOrFail();
        $this->assertSame($admin->id, $notification->notifiable_id);
        $this->assertSame('subscription_cancelled', $notification->data['type']);
        $this->assertSame($clinic->id, (int) $notification->data['clinic_id']);
    }

    public function test_subscription_cancelled_by_tenant_sends_backoffice_alert(): void
    {
        Mail::fake();
        config()->set('billing.provider', 'fake');
        config()->set('billing.cancellation_notification_to', 'qa-billing@irison.test');
        $admin = $this->createAdmin();

        [$clinic, $user] = $this->createClinicAndOwner([
            'subscription_status' => 'active',
            'subscribed_at' => now(),
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'active',
            'stripe_subscription_id' => 'fake-sub-alert-001',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/cancel')->assertOk();

        $this->assertSame('canceled', $clinic->refresh()->subscription_status);

        $notification = DatabaseNotification::query()->where('data', 'like', '%subscription_cancelled%')->firstOrFail();
        $this->assertSame($admin->id, $notification->notifiable_id);
        $this->assertSame('subscription_cancelled', $notification->data['type']);
        $this->assertSame($clinic->id, (int) $notification->data['clinic_id']);
    }

    private function createAdmin(array $overrides = []): AdminUser
    {
        return AdminUser::create(array_merge([
            'name' => 'Backoffice Admin',
            'email' => 'admin-alerts@irison.test',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ], $overrides));
    }

    private function createClinicAndOwner(array $clinicOverrides = []): array
    {
        $clinic = new Clinic();
        $clinic->forceFill(array_merge([
            'name' => 'Clinica Alertas',
            'email' => 'alerts@irison.test',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(2),
            'subscribed_at' => null,
            'subscription_provider' => 'fake',
        ], $clinicOverrides));
        $clinic->save();

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Alertas',
            'email' => 'owner-alerts-' . $clinic->id . '@irison.test',
            'password' => 'password',
            'role' => 'owner',
        ]);

        return [$clinic->refresh(), $user];
    }

    private function postStripeWebhook(string $payload, string $secret)
    {
        config()->set('billing.provider', 'stripe');
        config()->set('services.stripe.secret', 'sk_test_webhook_placeholder');

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
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
            $payload
        );
    }
}
