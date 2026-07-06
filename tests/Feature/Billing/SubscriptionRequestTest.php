<?php

namespace Tests\Feature\Billing;

use App\Mail\SubscriptionUpgradedNotificationMail;
use App\Mail\SubscriptionRequestMail;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_upgrade_request_from_basic_to_pro(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Basic',
            'email' => 'clinic-basic@test.local',
            'subscription_status' => 'active',
            'plan' => 'basic',
            'subscribed_at' => now(),
            'subscription_provider' => 'fake',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Basic',
            'email' => 'owner-basic@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings/subscription/request', [
            'requested_plan' => 'pro',
            'comments' => 'Necesito más usuarios para mi clínica',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Solicitud de suscripción creada correctamente.')
            ->assertJsonPath('id', $clinic->id);

        $this->assertDatabaseHas('subscription_requests', [
            'clinic_id' => $clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        Mail::assertSent(SubscriptionRequestMail::class, function (SubscriptionRequestMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_create_upgrade_request_from_pro_to_enterprise(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Pro',
            'email' => 'clinic-pro@test.local',
            'subscription_status' => 'active',
            'plan' => 'pro',
            'subscribed_at' => now(),
            'subscription_provider' => 'fake',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Pro',
            'email' => 'owner-pro@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings/subscription/request', [
            'requested_plan' => 'enterprise',
            'comments' => 'Necesito características avanzadas',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Solicitud de suscripción creada correctamente.');

        $this->assertDatabaseHas('subscription_requests', [
            'clinic_id' => $clinic->id,
            'current_plan' => 'pro',
            'requested_plan' => 'enterprise',
            'status' => 'pending',
        ]);

        Mail::assertSent(SubscriptionRequestMail::class, function (SubscriptionRequestMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_cannot_create_self_downgrade_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        $clinic = Clinic::create([
            'name' => 'Clinica Pro',
            'email' => 'clinic-pro@test.local',
            'subscription_status' => 'active',
            'plan' => 'pro',
            'subscribed_at' => now(),
            'subscription_provider' => 'fake',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Pro',
            'email' => 'owner-pro@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings/subscription/request', [
            'requested_plan' => 'basic',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'El plan solicitado debe ser superior al plan actual.');
    }

    public function test_cannot_create_self_self_upgrade_basic_to_basic(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        $clinic = Clinic::create([
            'name' => 'Clinica Basic',
            'email' => 'clinic-basic@test.local',
            'subscription_status' => 'active',
            'plan' => 'basic',
            'subscribed_at' => now(),
            'subscription_provider' => 'fake',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Basic',
            'email' => 'owner-basic@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings/subscription/request', [
            'requested_plan' => 'basic',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'El plan solicitado debe ser superior al plan actual.');
    }

    public function test_auto_detects_current_plan_when_not_provided(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Pro',
            'email' => 'clinic-pro@test.local',
            'subscription_status' => 'active',
            'plan' => 'pro',
            'subscribed_at' => now(),
            'subscription_provider' => 'fake',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Pro',
            'email' => 'owner-pro@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings/subscription/request', [
            'requested_plan' => 'enterprise',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('subscription_requests', [
            'clinic_id' => $clinic->id,
            'current_plan' => 'pro',
            'requested_plan' => 'enterprise',
        ]);
    }

    private function createClinicWithOwner(string $name, string $ownerEmail, string $plan = 'basic'): array
    {
        $clinic = Clinic::create([
            'name' => $name,
            'email' => 'clinic-' . $plan . '@test.local',
            'subscription_status' => 'active',
            'plan' => $plan,
            'subscribed_at' => now(),
            'subscription_provider' => 'fake',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner ' . ucfirst($plan),
            'email' => $ownerEmail,
            'password' => 'password',
            'role' => 'owner',
        ]);

        return [$clinic->refresh(), $user];
    }
}