<?php

namespace Tests\Feature\Billing;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Notifications\Backoffice\Notifications\BackofficeAlertNotification;
use Modules\Notifications\Backoffice\Notifications\ReactivationApprovedNotification;
use Modules\Notifications\Backoffice\Notifications\SubscriptionRejectedNotification;
use Tests\TestCase;

class ReactivationRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_canceled_clinic_can_request_reactivation_and_backoffice_is_alerted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $owner] = $this->createCanceledClinicAndOwner();
        $admin = $this->createAdmin();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/settings/subscription/reactivate', [
            'comments' => 'Quiero volver a usar Irison con mis pacientes',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Solicitud de reactivación enviada correctamente.');

        $this->assertDatabaseHas('subscription_requests', [
            'clinic_id' => $clinic->id,
            'type' => SubscriptionRequest::TYPE_REACTIVATION,
            'requested_plan' => '',
            'status' => 'pending',
            'requested_by' => $owner->id,
            'comments' => 'Quiero volver a usar Irison con mis pacientes',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => AdminUser::class,
            'notifiable_id' => $admin->id,
            'type' => BackofficeAlertNotification::class,
        ]);

        $notification = $admin->fresh()->notifications()->latest()->first();
        $this->assertSame('backoffice_reactivation_requested', $notification->data['type'] ?? null);
    }

    public function test_reactivation_request_requires_motive(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [, $owner] = $this->createCanceledClinicAndOwner();

        Sanctum::actingAs($owner);

        $this->postJson('/api/settings/subscription/reactivate', [])
            ->assertStatus(422);
    }

    public function test_only_canceled_clinics_can_request_reactivation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $owner] = $this->createClinicAndOwner([
            'subscription_status' => 'active',
            'subscribed_at' => now(),
            'plan' => 'basic',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/settings/subscription/reactivate', [
            'comments' => 'Motivo de prueba',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Solo las clínicas con suscripción cancelada pueden solicitar la reactivación.');

        $this->assertDatabaseMissing('subscription_requests', ['clinic_id' => $clinic->id]);
    }

    public function test_read_only_canceled_clinic_can_request_reactivation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $owner] = $this->createCanceledClinicAndOwner();

        // Periodo pagado ya vencido -> modo solo lectura por cancelación (7 días extra)
        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'canceled',
            'current_period_end' => now()->subDay(),
        ]);

        $this->assertTrue($clinic->refresh()->isReadOnlyNoTransactionsMode());

        Sanctum::actingAs($owner);

        $this->postJson('/api/settings/subscription/reactivate', [
            'comments' => 'Quiero recuperar mi cuenta',
        ])->assertCreated();
    }

    public function test_backoffice_approve_reactivation_request_notifies_owner(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));
        Notification::fake();

        [$clinic, $owner] = $this->createCanceledClinicAndOwner();
        $admin = $this->createAdmin();

        $request = SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'type' => SubscriptionRequest::TYPE_REACTIVATION,
            'current_plan' => 'basic',
            'requested_plan' => '',
            'status' => 'pending',
            'comments' => 'Motivo de reactivación',
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($admin, 'admin');

        $this->patch(route('backoffice.subscription-requests.approve', $request), [
            'reviewer_comments' => 'Revisada, procedemos',
        ])->assertRedirect();

        $this->assertDatabaseHas('subscription_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'reviewer_comments' => 'Revisada, procedemos',
        ]);

        Notification::assertSentTo($owner, ReactivationApprovedNotification::class);
    }

    public function test_backoffice_reject_reactivation_request_notifies_owner(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));
        Notification::fake();

        [$clinic, $owner] = $this->createCanceledClinicAndOwner();
        $admin = $this->createAdmin();

        $request = SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'type' => SubscriptionRequest::TYPE_REACTIVATION,
            'current_plan' => 'basic',
            'requested_plan' => '',
            'status' => 'pending',
            'comments' => 'Motivo de reactivación',
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($admin, 'admin');

        $this->patch(route('backoffice.subscription-requests.reject', $request), [
            'reviewer_comments' => 'No procede',
        ])->assertRedirect();

        $this->assertDatabaseHas('subscription_requests', [
            'id' => $request->id,
            'status' => 'rejected',
        ]);

        Notification::assertSentTo($owner, SubscriptionRejectedNotification::class);
    }

    public function test_clinics_index_marks_pending_reactivation_without_upgrade(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $owner] = $this->createCanceledClinicAndOwner();

        SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'type' => SubscriptionRequest::TYPE_REACTIVATION,
            'current_plan' => 'basic',
            'requested_plan' => '',
            'status' => 'pending',
            'comments' => 'Quiero volver',
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($this->createAdmin(), 'admin');

        $response = $this->get(route('backoffice.clinics.index'));

        $response->assertOk();
        $response->assertSee('Reactivación pendiente');

        $clinics = $response->viewData('clinics');
        $this->assertTrue($clinics->first()->has_pending_reactivation);
        $this->assertFalse($clinics->first()->has_pending_upgrade);
    }

    private function createCanceledClinicAndOwner(): array
    {
        [$clinic, $owner] = $this->createClinicAndOwner([
            'subscription_status' => 'canceled',
            'subscribed_at' => null,
            'plan' => 'basic',
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'canceled',
            'current_period_end' => now()->addDays(10),
        ]);

        return [$clinic->refresh(), $owner];
    }

    private function createClinicAndOwner(array $overrides = []): array
    {
        $clinic = Clinic::create(array_merge([
            'name' => 'Clinica Reactivacion',
            'email' => 'clinic-reactivation@test.local',
            'subscription_status' => 'canceled',
            'plan' => 'basic',
            'subscription_provider' => 'fake',
        ], $overrides));

        $owner = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Reactivacion',
            'email' => 'owner-reactivation@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        return [$clinic->refresh(), $owner];
    }

    private function createAdmin(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Backoffice Admin',
            'email' => 'admin-reactivation@irison.test',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }
}
