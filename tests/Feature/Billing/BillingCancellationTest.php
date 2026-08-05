<?php

namespace Tests\Feature\Billing;

use App\Mail\SubscriptionCanceledInternalMail;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cancel_subscription_keeps_paid_access_then_enforces_read_only_and_sends_internal_email(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));
        Mail::fake();

        config()->set('billing.provider', 'fake');
        config()->set('billing.cancellation_notification_to', 'qa-billing@test.local');

        $clinic = Clinic::create([
            'name' => 'Clinica QA',
            'email' => 'clinic@test.local',
            'subscription_provider' => 'fake',
            'subscription_status' => 'active',
            'subscribed_at' => now(),
        ]);

        $subscription = Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'active',
            'stripe_subscription_id' => 'fake-sub-001',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner QA',
            'email' => 'owner@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user);

        $cancelResponse = $this->postJson('/api/billing/cancel');

        $cancelResponse
            ->assertOk()
            ->assertJson([
                'status' => 'canceled',
            ]);

        $clinic->refresh();
        $subscription->refresh();

        $this->assertSame('canceled', $clinic->subscription_status);
        $this->assertNull($clinic->subscribed_at);
        $this->assertSame('canceled', $subscription->status);
        $this->assertTrue($subscription->current_period_end?->equalTo(now()->addDays(7)));

        Mail::assertQueued(SubscriptionCanceledInternalMail::class, function (SubscriptionCanceledInternalMail $mail) {
            return $mail->hasTo('qa-billing@test.local');
        });

        // Tras cancelar, la clínica conserva acceso completo durante el periodo pagado (7 días de gracia)
        $meResponse = $this->getJson('/api/me');
        $meResponse
            ->assertOk()
            ->assertJsonPath('status', 'canceled')
            ->assertJsonPath('read_only_no_transactions', false)
            ->assertJsonPath('can_transact', true)
            ->assertJsonPath('cancellation_days_left', 7);

        // Al expirar el periodo pagado entra en modo solo lectura
        Carbon::setTestNow(now()->addDays(8));

        $readOnlyResponse = $this->getJson('/api/me');
        $readOnlyResponse
            ->assertOk()
            ->assertJsonPath('status', 'canceled')
            ->assertJsonPath('read_only_no_transactions', true)
            ->assertJsonPath('can_transact', false);

        $updateResponse = $this->putJson('/api/me', [
            'name' => 'Cambio bloqueado',
            'email' => 'owner-updated@test.local',
            'clinic_name' => 'Clinica QA',
            'timezone' => 'Europe/Madrid',
        ]);

        $updateResponse
            ->assertStatus(403)
            ->assertJsonPath('code', 'CLINIC_READ_ONLY_NO_TRANSACTIONS');
    }
}