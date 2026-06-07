<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ClinicManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingConnection();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_super_admin_can_view_clinics_index(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_SUPER_ADMIN);
        $this->createClinicWithOwner('Clinic One', 'one@test.local');

        $response = $this->actingAs($admin, 'admin')->get('/backoffice/clinics');

        $response->assertOk()->assertSee('Clinic One');
    }

    public function test_readonly_can_view_but_cannot_edit_clinic(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_READONLY);
        [$clinic] = $this->createClinicWithOwner('Clinic Readonly', 'readonly-clinic@test.local');

        $viewResponse = $this->actingAs($admin, 'admin')->get('/backoffice/clinics/' . $clinic->id);
        $viewResponse->assertOk();

        $editResponse = $this->actingAs($admin, 'admin')->get('/backoffice/clinics/' . $clinic->id . '/edit');
        $editResponse->assertForbidden();
    }

    public function test_support_can_suspend_and_reactivate_clinic(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_SUPPORT);
        [$clinic] = $this->createClinicWithOwner('Clinic Suspend', 'suspend@test.local');

        $this->actingAs($admin, 'admin')
            ->patch('/backoffice/clinics/' . $clinic->id . '/suspend', ['reason' => 'fraud-check'])
            ->assertRedirect();

        $this->assertNotNull($clinic->fresh()->suspended_at);

        $this->actingAs($admin, 'admin')
            ->patch('/backoffice/clinics/' . $clinic->id . '/reactivate', ['reason' => 'resolved'])
            ->assertRedirect();

        $this->assertNull($clinic->fresh()->suspended_at);
    }

    public function test_active_clinic_cannot_be_reactivated(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_SUPPORT);
        [$clinic] = $this->createClinicWithOwner('Clinic Active', 'active@test.local');

        $clinic->update([
            'subscription_status' => 'active',
            'status' => 'active',
            'suspended_at' => null,
        ]);

        $this->actingAs($admin, 'admin')
            ->from('/backoffice/clinics/' . $clinic->id)
            ->patch('/backoffice/clinics/' . $clinic->id . '/reactivate', ['reason' => 'noop'])
            ->assertRedirect('/backoffice/clinics/' . $clinic->id)
            ->assertSessionHasErrors(['action']);
    }

    public function test_canceled_clinic_cannot_be_canceled_again(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_BILLING);
        [$clinic] = $this->createClinicWithOwner('Clinic Canceled', 'canceled@test.local');

        $clinic->update(['subscription_status' => 'canceled']);

        Subscription::query()->create([
            'clinic_id' => $clinic->id,
            'status' => 'canceled',
            'current_period_end' => now()->addDays(5),
        ]);

        $this->actingAs($admin, 'admin')
            ->from('/backoffice/clinics/' . $clinic->id)
            ->post('/backoffice/clinics/' . $clinic->id . '/cancel-subscription', ['reason' => 'noop'])
            ->assertRedirect('/backoffice/clinics/' . $clinic->id)
            ->assertSessionHasErrors(['action']);
    }

    public function test_non_trial_clinic_cannot_extend_trial(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_SUPPORT);
        [$clinic] = $this->createClinicWithOwner('Clinic Paid', 'paid@test.local');

        $clinic->update([
            'subscription_status' => 'active',
            'status' => 'active',
            'trial_ends_at' => null,
        ]);

        $this->actingAs($admin, 'admin')
            ->from('/backoffice/clinics/' . $clinic->id)
            ->patch('/backoffice/clinics/' . $clinic->id . '/extend-trial', ['days' => 7, 'reason' => 'noop'])
            ->assertRedirect('/backoffice/clinics/' . $clinic->id)
            ->assertSessionHasErrors(['action']);
    }

    public function test_suspended_clinic_cannot_be_suspended_again(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_SUPPORT);
        [$clinic] = $this->createClinicWithOwner('Clinic Already Suspended', 'already-suspended@test.local');

        $clinic->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->from('/backoffice/clinics/' . $clinic->id)
            ->patch('/backoffice/clinics/' . $clinic->id . '/suspend', ['reason' => 'noop'])
            ->assertRedirect('/backoffice/clinics/' . $clinic->id)
            ->assertSessionHasErrors(['action']);
    }

    public function test_billing_can_cancel_subscription_and_change_plan(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_BILLING);
        [$clinic] = $this->createClinicWithOwner('Clinic Billing', 'billing@test.local');

        $paidUntil = now()->addMonth();

        $subscription = Subscription::query()->create([
            'clinic_id' => $clinic->id,
            'status' => 'active',
            'current_period_end' => $paidUntil,
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/backoffice/clinics/' . $clinic->id . '/cancel-subscription', ['reason' => 'manual'])
            ->assertRedirect();

        $clinic->refresh();
        $subscription->refresh();
        $this->assertSame('canceled', $clinic->subscription_status);
        $this->assertSame($paidUntil->toDateString(), $subscription->current_period_end?->toDateString());

        $this->actingAs($admin, 'admin')
            ->patch('/backoffice/clinics/' . $clinic->id . '/change-plan', ['plan' => 'enterprise', 'reason' => 'upgrade'])
            ->assertRedirect();

        $this->assertSame('enterprise', (string) $clinic->fresh()->plan);
    }

    public function test_reactivating_canceled_clinic_marks_as_fake_provider_to_avoid_stripe_conflicts(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_SUPER_ADMIN);
        [$clinic] = $this->createClinicWithOwner('Reactivate Fix', 'rev@test.local');
        
        // Simular que era Stripe y fue cancelada
        $clinic->update([
            'subscription_status' => 'canceled',
            'subscription_provider' => 'stripe',
            'subscription_reference' => 'sub_old_stripe_id'
        ]);

        Subscription::query()->create([
            'clinic_id' => $clinic->id,
            'status' => 'canceled',
            'stripe_subscription_id' => 'sub_old_stripe_id'
        ]);

        // Reactivar desde backoffice
        $this->actingAs($admin, 'admin')
            ->patch('/backoffice/clinics/' . $clinic->id . '/reactivate', ['reason' => 'test fix'])
            ->assertRedirect();

        $clinic->refresh();
        $this->assertEquals('active', $clinic->subscription_status);
        $this->assertEquals('fake', $clinic->subscription_provider);
        $this->assertStringContainsString('backoffice-reactivation', $clinic->subscription_reference);
        
        $subscription = $clinic->currentSubscription();
        $this->assertNull($subscription->stripe_subscription_id);
    }

    public function test_super_admin_can_start_impersonation_and_store_context(): void
    {
        $admin = $this->createAdmin(AdminUser::ROLE_SUPER_ADMIN);
        [$clinic, $owner] = $this->createClinicWithOwner('Clinic Impersonate', 'owner-imp@test.local');

        $response = $this->actingAs($admin, 'admin')
            ->post('/backoffice/clinics/' . $clinic->id . '/impersonate');

        $response->assertRedirect();
        $context = (array) session('backoffice_impersonation', []);
        $this->assertSame($admin->id, (int) ($context['admin_user_id'] ?? 0));
        $this->assertSame($clinic->id, (int) ($context['clinic_id'] ?? 0));
        $this->assertSame($owner->id, (int) ($context['target_user_id'] ?? 0));
    }

    private function createAdmin(string $role): AdminUser
    {
        return AdminUser::query()->create([
            'name' => 'Admin ' . $role,
            'email' => $role . '-' . uniqid('', true) . '@backoffice.test',
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createClinicWithOwner(string $name, string $ownerEmail): array
    {
        $clinic = Clinic::query()->create([
            'name' => $name,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(15),
            'plan' => 'basic',
            'status' => 'trial',
        ]);

        $owner = User::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner ' . $clinic->id,
            'email' => $ownerEmail,
            'password' => 'password123',
            'role' => 'owner',
        ]);

        return [$clinic, $owner];
    }

    private function assertSafeTestingConnection(): void
    {
        $defaultConnection = (string) config('database.default');
        $sqliteDatabase = (string) config('database.connections.sqlite.database');

        if (! app()->environment('testing') || $defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException('Los tests de Backoffice solo se permiten en APP_ENV=testing con sqlite :memory:.');
        }
    }
}
