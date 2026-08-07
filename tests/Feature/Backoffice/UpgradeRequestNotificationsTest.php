<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Notifications\Backoffice\Notifications\BackofficeAlertNotification;
use Modules\Subscriptions\Application\Services\SubscriptionRequestService;
use Tests\TestCase;

class UpgradeRequestNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function createClinic(array $overrides = []): Clinic
    {
        return Clinic::create(array_merge([
            'name' => 'Clinica Notifications Test',
            'email' => 'clinica-notifications@irison.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'basic',
            'trial_ends_at' => now()->addDays(30),
        ], $overrides));
    }

    private function createOwner(Clinic $clinic, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Owner Test',
            'email' => 'owner-notifications@irison.test',
            'password' => 'password',
            'clinic_id' => $clinic->id,
            'role' => 'owner',
        ], $overrides));
    }

    private function createAdmin(array $overrides = []): AdminUser
    {
        return AdminUser::create(array_merge([
            'name' => 'Backoffice Admin',
            'email' => 'admin-backoffice@irison.test',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ], $overrides));
    }

    public function test_upgrade_request_notifies_active_backoffice_admins(): void
    {
        $clinic = $this->createClinic();
        $owner = $this->createOwner($clinic);
        $admin = $this->createAdmin();
        $inactiveAdmin = $this->createAdmin([
            'name' => 'Inactive Admin',
            'email' => 'inactive@irison.test',
            'is_active' => false,
        ]);

        app(SubscriptionRequestService::class)->createRequest(
            ['current_plan' => 'basic', 'requested_plan' => 'pro'],
            $clinic->id,
            $owner->id,
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => AdminUser::class,
            'notifiable_id' => $admin->id,
            'type' => BackofficeAlertNotification::class,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => AdminUser::class,
            'notifiable_id' => $inactiveAdmin->id,
            'type' => BackofficeAlertNotification::class,
        ]);
    }

    public function test_clinics_index_marks_clinics_with_pending_upgrade(): void
    {
        $clinic = $this->createClinic();
        $owner = $this->createOwner($clinic);

        SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'pending',
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($this->createAdmin(), 'admin');

        $response = $this->get(route('backoffice.clinics.index'));

        $response->assertOk();
        $response->assertSee('Upgrade pendiente');

        $clinics = $response->viewData('clinics');
        $this->assertTrue($clinics->first()->has_pending_upgrade);
    }

    public function test_clinics_index_does_not_mark_clinic_without_pending_upgrade(): void
    {
        $this->createClinic();

        $this->actingAs($this->createAdmin(), 'admin');

        $response = $this->get(route('backoffice.clinics.index'));

        $response->assertOk();
        $response->assertDontSee('Upgrade pendiente');

        $clinics = $response->viewData('clinics');
        $this->assertFalse($clinics->first()->has_pending_upgrade);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $admin = $this->createAdmin();
        $notification = $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => BackofficeAlertNotification::class,
            'data' => [
                'type' => 'backoffice_upgrade_requested',
                'request_id' => 1,
                'clinic_name' => 'Clinica Test',
                'requested_plan' => 'pro',
                'requester_name' => 'Owner',
                'message' => 'La clínica Clinica Test solicita un upgrade al plan pro.',
            ],
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('backoffice.notifications.read', $notification))->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => $notification->fresh()->read_at,
        ]);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $admin = $this->createAdmin();
        $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => BackofficeAlertNotification::class,
            'data' => ['type' => 'backoffice_upgrade_requested', 'request_id' => 1, 'clinic_name' => 'A', 'requested_plan' => 'pro', 'requester_name' => 'Owner', 'message' => 'msg'],
        ]);
        $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => BackofficeAlertNotification::class,
            'data' => ['type' => 'backoffice_upgrade_requested', 'request_id' => 2, 'clinic_name' => 'B', 'requested_plan' => 'enterprise', 'requester_name' => 'Owner', 'message' => 'msg'],
        ]);

        $this->actingAs($admin, 'admin');

        $this->post(route('backoffice.notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
    }
}
