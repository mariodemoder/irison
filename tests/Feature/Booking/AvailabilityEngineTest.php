<?php

namespace Tests\Feature\Booking;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Booking\BookingPage;
use App\Models\Booking\BookingService;
use App\Models\Booking\BookingProfessional;
use App\Models\Booking\ProfessionalSchedule;
use App\Models\Booking\ScheduleException;
use App\Services\Booking\AvailabilityEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AvailabilityEngineTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $user;
    private BookingService $service;
    private BookingProfessional $bp;

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

        ProfessionalSchedule::create([
            'professional_id' => $this->bp->id,
            'day_of_week' => Carbon::now()->addDay()->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        app()->instance('activeClinic', $this->clinic);
    }

    public function test_returns_available_slots_for_a_day(): void
    {
        $engine = app(AvailabilityEngine::class);
        $futureDate = Carbon::now()->addDay()->toDateString();

        $slots = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate
        );

        $this->assertNotEmpty($slots);
        $this->assertEquals('09:00', $slots[0]['start']);
        // 4h window (09-13) with 60min duration and 15min granularity = 13 possible start times
        $this->assertCount(13, $slots);
    }

    public function test_excludes_booked_slots(): void
    {
        $futureDate = Carbon::now()->addDay();
        $start = $futureDate->copy()->setTime(10, 0);
        $end = $start->copy()->addHour();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Test', 'last_name' => 'Patient']);

        Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->user->id,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 40,
            'payment_type' => 'single',
        ]);

        $engine = app(AvailabilityEngine::class);
        $slots = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate->toDateString()
        );

        $slotStarts = array_column($slots, 'start');
        $this->assertNotContains('10:00', $slotStarts);
        $this->assertContains('09:00', $slotStarts);
        $this->assertContains('11:00', $slotStarts);
    }

    public function test_excludes_blocked_dates(): void
    {
        $futureDate = Carbon::now()->addDay();

        ScheduleException::create([
            'professional_id' => $this->bp->id,
            'date' => $futureDate->toDateString(),
            'reason' => 'Vacaciones',
        ]);

        $engine = app(AvailabilityEngine::class);
        $dates = $engine->getAvailableDates(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate->format('Y-m')
        );

        $dateMap = collect($dates)->keyBy('date');
        $this->assertFalse($dateMap[$futureDate->toDateString()]['has_availability']);
    }

    public function test_returns_empty_when_no_schedule(): void
    {
        // Use a day that is NOT the one we scheduled
        $scheduledDay = Carbon::now()->addDay()->dayOfWeekIso;

        // Find a different day
        $otherDay = Carbon::now()->addDays(2);
        if ($otherDay->dayOfWeekIso === $scheduledDay) {
            $otherDay->addDay();
        }

        $engine = app(AvailabilityEngine::class);
        $slots = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $otherDay->toDateString()
        );

        $this->assertEmpty($slots);
    }

    public function test_respects_max_horizon(): void
    {
        BookingPage::create([
            'clinic_id' => $this->clinic->id,
            'slug' => 'test-clinic',
            'title' => 'Test',
            'is_active' => true,
            'max_horizon_days' => 30,
        ]);

        $farDate = Carbon::now()->addDays(60);

        $engine = app(AvailabilityEngine::class);
        $dates = $engine->getAvailableDates(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $farDate->format('Y-m')
        );

        $dateMap = collect($dates)->keyBy('date');
        $farStr = $farDate->toDateString();
        if (isset($dateMap[$farStr])) {
            $this->assertFalse($dateMap[$farStr]['has_availability']);
        }
    }
}
