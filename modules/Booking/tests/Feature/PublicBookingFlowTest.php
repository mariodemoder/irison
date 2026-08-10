<?php

namespace Modules\Booking\Tests\Feature;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Patient;
use Modules\Booking\Models\BookingPage;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\ProfessionalSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PublicBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $user;
    private BookingService $service;
    private BookingProfessional $bp;
    private BookingPage $page;
    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create(['name' => 'Fisio Center', 'address' => 'Calle Mayor 1']);
        $this->user = User::create([
            'name' => 'Dra. Ana',
            'email' => 'ana@fisio.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
        ]);

        $this->slug = 'fisio-center';

        $this->page = BookingPage::create([
            'clinic_id' => $this->clinic->id,
            'slug' => $this->slug,
            'title' => 'Reserva tu cita',
            'is_active' => true,
            'max_horizon_days' => 60,
            'cancellation_hours' => 24,
        ]);

        $this->service = BookingService::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Sesión de fisioterapia',
            'duration_minutes' => 60,
            'price' => 40,
            'is_active' => true,
        ]);

        $this->bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
            'allow_online_booking' => true,
        ]);

        $futureDayOfWeek = Carbon::now()->addDay()->dayOfWeekIso;
        ProfessionalSchedule::create([
            'professional_id' => $this->bp->id,
            'day_of_week' => $futureDayOfWeek,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        app()->instance('activeClinic', $this->clinic);
    }

    public function test_returns_booking_page(): void
    {
        $response = $this->getJson("/api/booking/{$this->slug}");

        $response->assertOk();
        $response->assertJsonPath('clinic.name', 'Fisio Center');
        $response->assertJsonPath('services.0.name', 'Sesión de fisioterapia');
        $response->assertJsonPath('professionals.0.name', 'Dra. Ana');
    }

    public function test_booking_page_returns_logo_for_pro_clinic(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('clinic-logos/fisio-logo.png', 'fake-binary');
        $this->clinic->plan = 'pro';
        $this->clinic->logo_path = 'clinic-logos/fisio-logo.png';
        $this->clinic->save();

        $response = $this->getJson("/api/booking/{$this->slug}");

        $response->assertOk();
        $this->assertStringContainsString('storage/clinic-logos/fisio-logo.png', (string) $response->json('clinic.logo_url'));
    }

    public function test_booking_page_returns_null_logo_for_basic_clinic(): void
    {
        $this->clinic->plan = 'basic';
        $this->clinic->save();

        $response = $this->getJson("/api/booking/{$this->slug}");

        $response->assertOk()
            ->assertJsonPath('clinic.logo_url', null);
    }

    public function test_returns_404_for_invalid_slug(): void
    {
        $response = $this->getJson('/api/booking/no-existe');
        $response->assertStatus(404);
    }

    public function test_returns_availability_for_a_month(): void
    {
        $nextMonth = Carbon::now()->addMonth()->format('Y-m');

        $response = $this->getJson("/api/booking/availability?slug={$this->slug}&service_id={$this->service->id}&month={$nextMonth}");

        $response->assertOk();
        $response->assertJsonStructure(['dates' => [['date', 'has_availability']]]);
    }

    public function test_returns_slots_for_a_date(): void
    {
        $futureDate = Carbon::now()->addDay()->toDateString();

        $response = $this->getJson("/api/booking/slots?slug={$this->slug}&service_id={$this->service->id}&date={$futureDate}");

        $response->assertOk();
        $response->assertJsonStructure(['slots' => [['start', 'end', 'professional_id', 'professional_name']]]);
    }

    public function test_returns_availability_filtered_by_professional(): void
    {
        $nextMonth = Carbon::now()->addMonth()->format('Y-m');

        $response = $this->getJson("/api/booking/availability?slug={$this->slug}&service_id={$this->service->id}&professional_id={$this->user->id}&month={$nextMonth}");

        $response->assertOk();
        $response->assertJsonStructure(['dates' => [['date', 'has_availability']]]);
    }

    public function test_returns_slots_filtered_by_professional(): void
    {
        $futureDate = Carbon::now()->addDay()->toDateString();

        $response = $this->getJson("/api/booking/slots?slug={$this->slug}&service_id={$this->service->id}&professional_id={$this->user->id}&date={$futureDate}");

        $response->assertOk();
        $response->assertJsonStructure(['slots' => [['start', 'end', 'professional_id', 'professional_name']]]);

        foreach ($response->json('slots') as $slot) {
            $this->assertEquals($this->user->id, $slot['professional_id']);
        }
    }

    public function test_creates_online_appointment(): void
    {
        $futureDate = Carbon::now()->addDay()->toDateString();

        $response = $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'patient' => [
                'first_name' => 'Carlos',
                'last_name' => 'Lopez',
                'email' => 'carlos@example.com',
                'phone' => '600000000',
                'notes' => 'Dolor lumbar',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('appointment.patient.email', 'carlos@example.com');
        $response->assertJsonStructure(['confirmation_token']);

        $this->assertDatabaseHas('appointments', [
            'booking_source' => 'online',
            'patient_id' => Patient::where('email', 'carlos@example.com')->first()->id,
        ]);

        $this->assertDatabaseHas('patients', [
            'email' => 'carlos@example.com',
            'first_name' => 'Carlos',
            'last_name' => 'Lopez',
        ]);
    }

    public function test_rejects_overlapping_online_booking(): void
    {
        $futureDate = Carbon::now()->addDay();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Existing', 'last_name' => 'Patient']);

        \App\Models\Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->user->id,
            'start_time' => $futureDate->copy()->setTime(9, 0),
            'end_time' => $futureDate->copy()->setTime(10, 0),
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 40,
            'payment_type' => 'single',
        ]);

        $response = $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $futureDate->toDateString(),
            'start_time' => '09:00',
            'patient' => [
                'first_name' => 'Otro',
                'last_name' => 'Paciente',
                'email' => 'otro@example.com',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'La franja horaria seleccionada ya no está disponible.');
    }

    public function test_cancels_appointment_by_token(): void
    {
        $futureDate = Carbon::now()->addDay();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@test.com']);

        $appointment = \App\Models\Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->user->id,
            'start_time' => $futureDate->copy()->setTime(9, 0),
            'end_time' => $futureDate->copy()->setTime(10, 0),
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 40,
            'payment_type' => 'single',
            'booking_source' => 'online',
            'confirmation_token' => 'test-token-123',
        ]);

        $response = $this->postJson('/api/booking/cancel/test-token-123');

        $response->assertOk();
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'canceled',
        ]);
    }

    public function test_rejects_invalid_cancel_token(): void
    {
        $response = $this->postJson('/api/booking/cancel/invalid-token');
        $response->assertStatus(422);
    }

    public function test_cannot_book_inactive_page(): void
    {
        $this->page->update(['is_active' => false]);

        $futureDate = Carbon::now()->addDay()->toDateString();
        $response = $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'patient' => [
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'juan@example.com',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Página de reserva no encontrada o desactivada.');
    }

    public function test_cannot_book_inactive_service(): void
    {
        $this->service->update(['is_active' => false]);

        $futureDate = Carbon::now()->addDay()->toDateString();
        $response = $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'patient' => [
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'juan@example.com',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'El servicio seleccionado no está disponible.');
    }

    public function test_cannot_book_without_online_professional(): void
    {
        $this->bp->update(['allow_online_booking' => false]);

        $futureDate = Carbon::now()->addDay()->toDateString();
        $response = $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'patient' => [
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'juan@example.com',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'El profesional no está disponible para reserva online.');
    }

    public function test_cannot_book_past_date(): void
    {
        $pastDate = Carbon::now()->subDay()->toDateString();

        $response = $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $pastDate,
            'start_time' => '09:00',
            'patient' => [
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'juan@example.com',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'No se puede reservar en una fecha pasada.');
    }

    public function test_cannot_book_beyond_horizon(): void
    {
        $this->page->update(['max_horizon_days' => 7]);
        $farDate = Carbon::now()->addDays(10)->toDateString();

        $response = $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $farDate,
            'start_time' => '09:00',
            'patient' => [
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'juan@example.com',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'La fecha excede el horizonte máximo de reserva.');
    }

    public function test_returns_appointment_by_token(): void
    {
        $futureDate = Carbon::now()->addDay();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@show.com']);

        $appointment = \App\Models\Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->user->id,
            'start_time' => $futureDate->copy()->setTime(9, 0),
            'end_time' => $futureDate->copy()->setTime(10, 0),
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 40,
            'payment_type' => 'single',
            'booking_source' => 'online',
            'confirmation_token' => 'show-token-456',
        ]);

        $response = $this->getJson('/api/booking/confirm/show-token-456');

        $response->assertOk();
        $response->assertJsonPath('appointment.id', $appointment->id);
        $response->assertJsonPath('appointment.status', 'scheduled');
        $response->assertJsonStructure(['appointment' => ['id', 'start_time', 'end_time', 'status', 'patient', 'professional', 'clinic']]);
    }

    public function test_existing_patient_gets_linked_not_duplicated(): void
    {
        $futureDate = Carbon::now()->addDay()->toDateString();

        $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $futureDate,
            'start_time' => '09:00',
            'patient' => [
                'first_name' => 'Carlos',
                'last_name' => 'Lopez',
                'email' => 'carlos@example.com',
            ],
        ]);

        $this->postJson('/api/booking', [
            'slug' => $this->slug,
            'service_id' => $this->service->id,
            'professional_id' => $this->user->id,
            'date' => $futureDate,
            'start_time' => '10:00',
            'patient' => [
                'first_name' => 'Carlos',
                'last_name' => 'Lopez',
                'email' => 'carlos@example.com',
            ],
        ]);

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseHas('patients', [
            'email' => 'carlos@example.com',
            'first_name' => 'Carlos',
            'last_name' => 'Lopez',
        ]);
    }
}
