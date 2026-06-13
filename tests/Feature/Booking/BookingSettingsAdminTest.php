<?php

namespace Tests\Feature\Booking;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Booking\BookingPage;
use App\Models\Booking\BookingService;
use App\Models\Booking\BookingProfessional;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class BookingSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create(['name' => 'Test Clinic']);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
        ]);

        $this->token = $this->user->createToken('test')->plainTextToken;

        app()->instance('activeClinic', $this->clinic);

        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);
    }

    public function test_get_settings_when_none_exist(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/booking/settings');

        $response->assertOk();
        $response->assertJsonPath('data.slug', null);
        $response->assertJsonPath('data.title', 'Reserva tu cita');
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.max_horizon_days', 60);
        $response->assertJsonPath('data.cancellation_hours', 24);
    }

    public function test_create_and_update_settings(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/booking/settings', [
                'slug' => 'my-clinic',
                'title' => 'Reserva online',
                'is_active' => true,
                'max_horizon_days' => 30,
                'cancellation_hours' => 48,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('booking_pages', [
            'clinic_id' => $this->clinic->id,
            'slug' => 'my-clinic',
            'max_horizon_days' => 30,
        ]);

        $response = $this->withToken($this->token)
            ->putJson('/api/booking/settings', [
                'max_horizon_days' => 90,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('booking_pages', [
            'clinic_id' => $this->clinic->id,
            'max_horizon_days' => 90,
        ]);
    }

    public function test_crud_services(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/booking/services', [
                'name' => 'Masaje deportivo',
                'duration_minutes' => 45,
                'price' => 35,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $serviceId = $response->json('data.id');

        $this->assertDatabaseHas('booking_services', ['name' => 'Masaje deportivo']);

        $response = $this->withToken($this->token)
            ->putJson("/api/booking/services/{$serviceId}", [
                'name' => 'Masaje deportivo avanzado',
            ]);

        $response->assertOk();

        $response = $this->withToken($this->token)
            ->getJson('/api/booking/services');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));

        $response = $this->withToken($this->token)
            ->deleteJson("/api/booking/services/{$serviceId}");

        $response->assertOk();
        $this->assertDatabaseMissing('booking_services', ['id' => $serviceId]);
    }

    public function test_crud_professionals(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/booking/professionals');

        $response->assertOk();
        $initialCount = count($response->json('data'));

        $response = $this->withToken($this->token)
            ->postJson('/api/booking/professionals', [
                'user_id' => $this->user->id,
            ]);

        $response->assertStatus(201);

        $response = $this->withToken($this->token)
            ->getJson('/api/booking/professionals');

        $this->assertCount($initialCount + 1, $response->json('data'));

        $bpId = $response->json('data')[0]['id'];

        $response = $this->withToken($this->token)
            ->putJson("/api/booking/professionals/{$bpId}", [
                'allow_online_booking' => true,
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.allow_online_booking'));
    }

    public function test_crud_schedules(): void
    {
        $bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/booking/professionals/{$bp->id}/schedules", [
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '14:00',
            ]);

        $response->assertStatus(201);

        $response = $this->withToken($this->token)
            ->getJson("/api/booking/professionals/{$bp->id}/schedules");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_requires_auth_for_admin_routes(): void
    {
        $response = $this->getJson('/api/booking/settings');
        $response->assertStatus(401);

        $response = $this->postJson('/api/booking/services', []);
        $response->assertStatus(401);

        $response = $this->getJson('/api/booking/professionals');
        $response->assertStatus(401);
    }
}
