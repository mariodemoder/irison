<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Notifications\Backoffice\Notifications\BackofficeAlertNotification;
use Tests\TestCase;

class ClinicAlertsReconcileTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_creates_trial_expired_notification_and_shows_badge(): void
    {
        $admin = $this->createAdmin();
        $clinic = $this->createClinic([
            'name' => 'Trial Vencido Reconcile',
            'subscription_status' => 'trial',
            'status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backoffice.clinics.index'));

        $response->assertOk();
        $response->assertSee('Trial vencido');

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => AdminUser::class,
            'notifiable_id' => $admin->id,
            'type' => BackofficeAlertNotification::class,
        ]);

        $notification = DatabaseNotification::query()->where('notifiable_id', $admin->id)->firstOrFail();
        $this->assertSame('trial_expired', $notification->data['type']);
        $this->assertSame($clinic->id, (int) $notification->data['clinic_id']);

        $clinics = $response->viewData('clinics');
        $this->assertContains('trial_expired', $clinics->first()->backoffice_alerts);
    }

    public function test_index_creates_subscription_cancelled_notification(): void
    {
        $admin = $this->createAdmin();
        $this->createClinic([
            'name' => 'Cancelada Reconcile',
            'subscription_status' => 'canceled',
            'status' => 'canceled',
            'trial_ends_at' => null,
        ]);

        $this->actingAs($admin, 'admin');

        $this->get(route('backoffice.clinics.index'))->assertOk();

        $notification = DatabaseNotification::query()->where('notifiable_id', $admin->id)->firstOrFail();
        $this->assertSame('subscription_cancelled', $notification->data['type']);
    }

    public function test_index_creates_trial_converted_notification_for_active_clinic_with_ended_trial(): void
    {
        $admin = $this->createAdmin();
        $this->createClinic([
            'name' => 'Convertida Reconcile',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'pro',
            'trial_ends_at' => now()->subDays(3),
        ]);

        $this->actingAs($admin, 'admin');

        $this->get(route('backoffice.clinics.index'))->assertOk();

        $notification = DatabaseNotification::query()->where('notifiable_id', $admin->id)->firstOrFail();
        $this->assertSame('trial_converted', $notification->data['type']);
        $this->assertSame('pro', $notification->data['plan']);
    }

    public function test_index_creates_upgrade_requested_notification_for_pending_request(): void
    {
        $admin = $this->createAdmin();
        $clinic = $this->createClinic();
        $owner = $this->createOwner($clinic);

        SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'pending',
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backoffice.clinics.index'));

        $response->assertOk();
        $response->assertSee('Upgrade pendiente');

        $notification = DatabaseNotification::query()->where('notifiable_id', $admin->id)->firstOrFail();
        $this->assertSame('backoffice_upgrade_requested', $notification->data['type']);
        $this->assertSame('pro', $notification->data['requested_plan']);
    }

    public function test_reconcile_is_idempotent_on_repeated_index_visits(): void
    {
        $admin = $this->createAdmin();
        $this->createClinic([
            'name' => 'Idempotente',
            'subscription_status' => 'trial',
            'status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($admin, 'admin');

        $this->get(route('backoffice.clinics.index'))->assertOk();
        $this->get(route('backoffice.clinics.index'))->assertOk();

        $this->assertSame(1, DatabaseNotification::query()->where('notifiable_id', $admin->id)->count());
    }

    public function test_index_does_not_create_notifications_for_clinic_without_condition(): void
    {
        $admin = $this->createAdmin();
        $this->createClinic([
            'name' => 'Sin Condicion',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'basic',
            'trial_ends_at' => now()->addDays(20),
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backoffice.clinics.index'));

        $response->assertOk();
        $this->assertSame(0, DatabaseNotification::query()->where('notifiable_id', $admin->id)->count());

        $clinics = $response->viewData('clinics');
        $this->assertEmpty($clinics->first()->backoffice_alerts ?? []);
    }

    public function test_active_clinic_with_stale_trial_status_renders_green_badge(): void
    {
        $admin = $this->createAdmin();
        $this->createClinic([
            'name' => 'Activa Stale',
            'subscription_status' => 'active',
            'status' => 'trial_read_only',
            'plan' => 'basic',
            'trial_ends_at' => now()->subDays(3),
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backoffice.clinics.index'));

        $response->assertOk();
        $response->assertSee('text-emerald-700');
    }

    public function test_genuine_trial_read_only_clinic_renders_red_badge(): void
    {
        $admin = $this->createAdmin();
        $this->createClinic([
            'name' => 'Trial Vencido Rojo',
            'subscription_status' => 'trial',
            'status' => 'trial_read_only',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get(route('backoffice.clinics.index'));

        $response->assertOk();
        $response->assertSee('text-rose-700');
    }

    private function createAdmin(array $overrides = []): AdminUser
    {
        return AdminUser::create(array_merge([
            'name' => 'Backoffice Admin',
            'email' => 'admin-reconcile-'.Str::uuid().'@irison.test',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ], $overrides));
    }

    private function createClinic(array $overrides = []): Clinic
    {
        return Clinic::create(array_merge([
            'name' => 'Clinica Reconcile',
            'email' => 'reconcile-'.Str::uuid().'@irison.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'basic',
            'trial_ends_at' => Carbon::now()->addDays(30),
        ], $overrides));
    }

    private function createOwner(Clinic $clinic): User
    {
        return User::create([
            'name' => 'Owner Reconcile',
            'email' => 'owner-reconcile-'.Str::uuid().'@irison.test',
            'password' => 'password',
            'clinic_id' => $clinic->id,
            'role' => 'owner',
        ]);
    }
}
