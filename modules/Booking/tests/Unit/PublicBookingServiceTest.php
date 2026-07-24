<?php

namespace Modules\Booking\Tests\Unit;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Patient;
use App\Models\Appointment;
use Modules\Booking\Models\BookingPage;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\ProfessionalSchedule;
use Modules\Booking\Services\PublicBookingService;
use Modules\Booking\Services\AvailabilityEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class PublicBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $user;
    private BookingPage $page;
    private BookingService $service;
    private BookingProfessional $bp;
    private PublicBookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create(['name' => 'Test Clinic']);

        $this->user = User::create([
            'name' => 'Dr. Test',
            'email' => 'dr@test.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
        ]);

        $this->page = BookingPage::create([
            'clinic_id' => $this->clinic->id,
            'slug' => 'test-clinic',
            'title' => 'Reserva',
            'is_active' => true,
            'max_horizon_days' => 60,
        ]);

        $this->service = BookingService::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Fisioterapia',
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
            'end_time' => '17:00',
        ]);

        app()->instance('activeClinic', $this->clinic);

        $this->bookingService = app(PublicBookingService::class);
    }

    public function test_resolves_booking_page_by_slug(): void
    {
        $page = $this->bookingService->resolveBookingPage('test-clinic');

        $this->assertInstanceOf(BookingPage::class, $page);
        $this->assertEquals('test-clinic', $page->slug);
    }

    public function test_throws_when_page_not_found(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Página de reserva no encontrada o desactivada.');

        $this->bookingService->resolveBookingPage('non-existent');
    }

    public function test_throws_when_page_inactive(): void
    {
        $this->page->update(['is_active' => false]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Página de reserva no encontrada o desactivada.');

        $this->bookingService->resolveBookingPage('test-clinic');
    }

    public function test_creates_patient_if_not_exists(): void
    {
        $futureDate = Carbon::now()->addDay()->toDateString();

        $this->assertDatabaseMissing('patients', ['email' => 'nuevo@example.com']);

        $appointment = $this->bookingService->createAppointment(
            'test-clinic',
            $this->service->id,
            $this->user->id,
            $futureDate,
            '09:00',
            [
                'first_name' => 'Nuevo',
                'last_name' => 'Paciente',
                'email' => 'nuevo@example.com',
                'phone' => '600000001',
                'notes' => 'Nota test',
            ]
        );

        $this->assertDatabaseHas('patients', [
            'email' => 'nuevo@example.com',
            'first_name' => 'Nuevo',
            'last_name' => 'Paciente',
            'phone' => '600000001',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'booking_source' => 'online',
            'booking_notes' => 'Nota test',
        ]);
    }

    public function test_uses_existing_patient_by_email(): void
    {
        $existingPatient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Existente',
            'last_name' => 'Paciente',
            'email' => 'existente@example.com',
        ]);

        $futureDate = Carbon::now()->addDay()->toDateString();

        $this->bookingService->createAppointment(
            'test-clinic',
            $this->service->id,
            $this->user->id,
            $futureDate,
            '09:00',
            [
                'first_name' => 'Existente',
                'last_name' => 'Paciente',
                'email' => 'existente@example.com',
            ]
        );

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseHas('patients', [
            'id' => $existingPatient->id,
            'email' => 'existente@example.com',
        ]);
    }

    public function test_throws_on_inactive_service(): void
    {
        $this->service->update(['is_active' => false]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('El servicio seleccionado no está disponible.');

        $this->bookingService->createAppointment(
            'test-clinic',
            $this->service->id,
            $this->user->id,
            Carbon::now()->addDay()->toDateString(),
            '09:00',
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ]
        );
    }

    public function test_throws_on_professional_not_accepting_online(): void
    {
        $this->bp->update(['allow_online_booking' => false]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('El profesional no está disponible para reserva online.');

        $this->bookingService->createAppointment(
            'test-clinic',
            $this->service->id,
            $this->user->id,
            Carbon::now()->addDay()->toDateString(),
            '09:00',
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ]
        );
    }

    public function test_throws_on_past_date(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No se puede reservar en una fecha pasada.');

        $this->bookingService->createAppointment(
            'test-clinic',
            $this->service->id,
            $this->user->id,
            Carbon::now()->subDay()->toDateString(),
            '09:00',
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ]
        );
    }

    public function test_throws_on_date_beyond_horizon(): void
    {
        $this->page->update(['max_horizon_days' => 7]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('La fecha excede el horizonte máximo de reserva.');

        $this->bookingService->createAppointment(
            'test-clinic',
            $this->service->id,
            $this->user->id,
            Carbon::now()->addDays(10)->toDateString(),
            '09:00',
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ]
        );
    }

    public function test_cancel_past_appointment_throws(): void
    {
        $pastDate = Carbon::now()->subDay();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Test', 'last_name' => 'User']);

        $appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->user->id,
            'start_time' => $pastDate->copy()->setTime(9, 0),
            'end_time' => $pastDate->copy()->setTime(10, 0),
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 40,
            'payment_type' => 'single',
            'booking_source' => 'online',
            'confirmation_token' => 'past-token-789',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No se puede cancelar una cita que ya ha pasado.');

        $this->bookingService->cancelByToken('past-token-789');
    }

    public function test_find_by_token_returns_appointment(): void
    {
        $futureDate = Carbon::now()->addDay();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Test', 'last_name' => 'User']);

        $appointment = Appointment::create([
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
            'confirmation_token' => 'find-token-abc',
        ]);

        $result = $this->bookingService->findByToken('find-token-abc');

        $this->assertInstanceOf(Appointment::class, $result);
        $this->assertEquals($appointment->id, $result->id);
        $this->assertNotNull($result->patient);
        $this->assertNotNull($result->professional);
    }

    public function test_find_by_token_throws_on_invalid_token(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cita no encontrada.');

        $this->bookingService->findByToken('invalid-token');
    }
}
