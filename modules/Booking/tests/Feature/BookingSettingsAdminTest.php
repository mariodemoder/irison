<?php

namespace Modules\Booking\Tests\Feature;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\User;
use App\Models\UserSchedule;
use Modules\Booking\Models\BookingPage;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\ProfessionalSchedule;
use Modules\Booking\Models\ScheduleException;
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

    public function test_update_and_delete_professional(): void
    {
        $bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
            'allow_online_booking' => false,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/booking/professionals/{$bp->id}", [
                'allow_online_booking' => true,
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.allow_online_booking'));
        $this->assertDatabaseHas('booking_professionals', [
            'id' => $bp->id,
            'allow_online_booking' => true,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/booking/professionals/{$bp->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('booking_professionals', ['id' => $bp->id]);
    }

    public function test_update_and_delete_schedule(): void
    {
        $bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
        ]);

        $schedule = ProfessionalSchedule::create([
            'professional_id' => $bp->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '14:00',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/booking/professionals/{$bp->id}/schedules/{$schedule->id}", [
                'start_time' => '10:00',
                'end_time' => '16:00',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('professional_schedules', [
            'id' => $schedule->id,
            'start_time' => '10:00',
            'end_time' => '16:00',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/booking/professionals/{$bp->id}/schedules/{$schedule->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('professional_schedules', ['id' => $schedule->id]);
    }

    public function test_crud_exceptions_full_lifecycle(): void
    {
        $bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
        ]);

        $exceptionDate = Carbon::now()->addDays(10)->toDateString();

        $response = $this->withToken($this->token)
            ->postJson("/api/booking/professionals/{$bp->id}/exceptions", [
                'date' => $exceptionDate,
                'start_time' => null,
                'end_time' => null,
                'reason' => 'Vacaciones',
            ]);

        $response->assertStatus(201);
        $exceptionId = $response->json('data.id');

        $response = $this->withToken($this->token)
            ->getJson("/api/booking/professionals/{$bp->id}/exceptions");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($exceptionDate, $response->json('data.0.date'));

        $response = $this->withToken($this->token)
            ->putJson("/api/booking/professionals/{$bp->id}/exceptions/{$exceptionId}", [
                'reason' => 'Vacaciones extendidas',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('schedule_exceptions', [
            'id' => $exceptionId,
            'reason' => 'Vacaciones extendidas',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/booking/professionals/{$bp->id}/exceptions/{$exceptionId}");

        $response->assertOk();
        $this->assertDatabaseMissing('schedule_exceptions', ['id' => $exceptionId]);
    }

    public function test_list_booking_appointments(): void
    {
        $tomorrow = Carbon::now()->addDay();
        $patient = \App\Models\Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Test', 'last_name' => 'Patient']);

        \App\Models\Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->user->id,
            'start_time' => $tomorrow->copy()->setTime(9, 0),
            'end_time' => $tomorrow->copy()->setTime(10, 0),
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 40,
            'payment_type' => 'single',
            'booking_source' => 'online',
            'confirmation_token' => 'list-test-token',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/booking/appointments');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'start_time', 'end_time', 'status', 'patient']]]);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_slug_check_endpoint(): void
    {
        $otherClinic = Clinic::create(['name' => 'Other Clinic']);
        BookingPage::withoutGlobalScopes()->create([
            'clinic_id' => $otherClinic->id,
            'slug' => 'taken-by-other',
            'title' => 'Other',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/booking/slug-check?slug=available-slug');

        $response->assertOk();
        $response->assertJsonPath('available', true);

        $response = $this->withToken($this->token)
            ->getJson('/api/booking/slug-check?slug=taken-by-other');

        $response->assertOk();
        $response->assertJsonPath('available', false);
    }

    public function test_schedule_index_returns_professional_schedules(): void
    {
        $bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
        ]);

        ProfessionalSchedule::create([
            'professional_id' => $bp->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '14:00',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/booking/professionals/{$bp->id}/schedules");

        $response->assertOk();
        $response->assertJsonPath('from_user', false);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('09:00', $response->json('data.0.start_time'));
    }

    public function test_schedule_index_falls_back_to_user_schedules(): void
    {
        $bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
        ]);

        UserSchedule::create([
            'user_id' => $this->user->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '16:00',
            'enabled' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/booking/professionals/{$bp->id}/schedules");

        $response->assertOk();
        $response->assertJsonPath('from_user', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('data.0.day_of_week'));
        $this->assertEquals('10:00', $response->json('data.0.start_time'));
    }

    public function test_bulk_update_replaces_all_schedules(): void
    {
        $bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
        ]);

        ProfessionalSchedule::create([
            'professional_id' => $bp->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '14:00',
        ]);

        $schedules = [];
        for ($dow = 1; $dow <= 7; $dow++) {
            $schedules[] = [
                'day_of_week' => $dow,
                'start_time' => $dow <= 5 ? '09:00' : null,
                'end_time' => $dow <= 5 ? '18:00' : null,
            ];
        }

        $response = $this->withToken($this->token)
            ->postJson("/api/booking/professionals/{$bp->id}/schedules/bulk", [
                'schedules' => $schedules,
            ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Horarios guardados.');

        $this->assertDatabaseCount('professional_schedules', 5);
        $this->assertDatabaseHas('professional_schedules', [
            'professional_id' => $bp->id,
            'day_of_week' => 3,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->assertDatabaseMissing('professional_schedules', [
            'professional_id' => $bp->id,
            'day_of_week' => 6,
        ]);
    }

    public function test_bulk_update_returns_updated_schedules(): void
    {
        $bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
        ]);

        $schedules = [];
        for ($dow = 1; $dow <= 7; $dow++) {
            $schedules[] = [
                'day_of_week' => $dow,
                'start_time' => '08:00',
                'end_time' => '15:00',
            ];
        }

        $response = $this->withToken($this->token)
            ->postJson("/api/booking/professionals/{$bp->id}/schedules/bulk", [
                'schedules' => $schedules,
            ]);

        $response->assertOk();
        $this->assertCount(7, $response->json('data'));
        $this->assertEquals('08:00', $response->json('data.0.start_time'));
    }
}
