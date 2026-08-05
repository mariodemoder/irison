<?php

declare(strict_types=1);

namespace Tests\Feature\Backoffice;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Notifications\Backoffice\Notifications\SubscriptionRejectedNotification;
use Tests\TestCase;

class SubscriptionRejectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reject_pending_upgrade_request_and_notify_owner(): void
    {
        Mail::fake();

        $admin = AdminUser::create([
            'name' => 'Backoffice Admin',
            'email' => 'admin-reject@irison.test',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $clinic = Clinic::create([
            'name' => 'Clinica Reject',
            'email' => 'reject@irison.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'basic',
        ]);

        $owner = User::create([
            'name' => 'Owner Reject',
            'email' => 'owner-reject@irison.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
            'role' => 'owner',
        ]);

        $request = SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'pending',
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->patch(route('backoffice.subscription-requests.reject', $request), [
            'reviewer_comments' => 'Datos insuficientes',
        ]);

        $response->assertRedirect(route('backoffice.subscription-requests.index'));

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame('Datos insuficientes', $request->reviewer_comments);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
            'type' => SubscriptionRejectedNotification::class,
        ]);
    }
}
