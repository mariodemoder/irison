<?php

namespace Modules\Booking\Tests\Feature;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\UserSchedule;
use App\Models\UserScheduleException;
use Modules\Booking\Models\BookingPage;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\ProfessionalSchedule;
use Modules\Booking\Models\ScheduleException;
use Modules\Booking\Services\AvailabilityEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AvailabilityEngineTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $user;
    private User $user2;
    private BookingService $service;
    private BookingProfessional $bp;
    private BookingProfessional $bp2;

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

        $this->user2 = User::create([
            'name' => 'Dr. Segundo',
            'email' => 'dr2@test.com',
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

        $this->bp2 = BookingProfessional::create([
            'user_id' => $this->user2->id,
            'clinic_id' => $this->clinic->id,
            'allow_online_booking' => true,
        ]);

        ProfessionalSchedule::create([
            'professional_id' => $this->bp->id,
            'day_of_week' => Carbon::now()->addDay()->dayOfWeekIso,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        ProfessionalSchedule::create([
            'professional_id' => $this->bp2->id,
            'day_of_week' => Carbon::now()->addDay()->dayOfWeekIso,
            'start_time' => '10:00',
            'end_time' => '14:00',
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
        $scheduledDay = Carbon::now()->addDay()->dayOfWeekIso;

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

    public function test_excludes_partial_blocked_range(): void
    {
        $futureDate = Carbon::now()->addDay();

        ScheduleException::create([
            'professional_id' => $this->bp->id,
            'date' => $futureDate->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'reason' => 'Mantenimiento',
        ]);

        $engine = app(AvailabilityEngine::class);
        $slots = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate->toDateString()
        );

        $slotStarts = array_column($slots, 'start');
        $this->assertContains('09:00', $slotStarts);
        $this->assertNotContains('10:00', $slotStarts);
        $this->assertContains('11:00', $slotStarts);
    }

    public function test_multi_professional_slots_are_distinct(): void
    {
        $futureDate = Carbon::now()->addDay()->toDateString();

        $engine = app(AvailabilityEngine::class);
        $slots = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            null,
            $futureDate
        );

        $professionalIds = array_unique(array_column($slots, 'professional_id'));
        $this->assertContains($this->user->id, $professionalIds);
        $this->assertContains($this->user2->id, $professionalIds);

        $names = array_unique(array_column($slots, 'professional_name'));
        $this->assertContains('Dr. Test', $names);
        $this->assertContains('Dr. Segundo', $names);
    }

    public function test_ignores_canceled_appointments_in_overlap(): void
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
            'status' => 'canceled',
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
        $this->assertContains('10:00', $slotStarts);
    }

    public function test_get_available_dates_filters_by_professional(): void
    {
        $futureDate = Carbon::now()->addDay();

        // b2 has schedule set up in setUp, bp does too
        // Only bp2 (user2) has schedule for this dayOfWeek
        // Both b2 and bp2 share the same day, so both should be available

        $engine = app(AvailabilityEngine::class);

        $datesAll = $engine->getAvailableDates(
            $this->clinic->id,
            $this->service->id,
            null,
            $futureDate->format('Y-m')
        );

        $dateMap = collect($datesAll)->keyBy('date');
        $futureDateStr = $futureDate->toDateString();
        $this->assertTrue($dateMap[$futureDateStr]['has_availability']);

        // Filter by specific professional
        $datesBp1 = $engine->getAvailableDates(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate->format('Y-m')
        );

        $dateMap1 = collect($datesBp1)->keyBy('date');
        $this->assertTrue($dateMap1[$futureDateStr]['has_availability']);

        // Filter by professional with no schedule on that day (different day of week)
        $otherDay = Carbon::now()->addDays(3);
        // Ensure it's a different dayOfWeek from the one in setUp
        $scheduledDayOfWeek = Carbon::now()->addDay()->dayOfWeekIso;
        while ($otherDay->dayOfWeekIso === $scheduledDayOfWeek) {
            $otherDay->addDay();
        }

        $datesBp1Other = $engine->getAvailableDates(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $otherDay->format('Y-m')
        );

        $dateMapOther = collect($datesBp1Other)->keyBy('date');
        $otherStr = $otherDay->toDateString();
        if (isset($dateMapOther[$otherStr])) {
            $this->assertFalse($dateMapOther[$otherStr]['has_availability']);
        }
    }

    public function test_slot_granularity_is_15_minutes(): void
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
        $this->assertEquals('09:15', $slots[1]['start']);
        $this->assertEquals('09:30', $slots[2]['start']);
        $this->assertEquals('09:45', $slots[3]['start']);
        $this->assertEquals('10:00', $slots[4]['start']);
    }

    public function test_uses_user_schedules_as_fallback(): void
    {
        $futureDate = Carbon::now()->addDay();
        $dowIso = $futureDate->dayOfWeekIso;
        $userDow = $dowIso === 7 ? 0 : $dowIso;

        // Delete professional_schedule for bp
        ProfessionalSchedule::where('professional_id', $this->bp->id)->delete();

        // Create user_schedule as fallback
        UserSchedule::create([
            'user_id' => $this->user->id,
            'day_of_week' => $userDow,
            'start_time' => '14:00',
            'end_time' => '18:00',
            'enabled' => true,
        ]);

        $engine = app(AvailabilityEngine::class);

        // Slots should come from user_schedule
        $slots = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate->toDateString()
        );

        $this->assertNotEmpty($slots);
        // User schedule is 14-18 (4h window), 15min granularity, 60min duration = 13 slots
        $this->assertEquals('14:00', $slots[0]['start']);
        $this->assertCount(13, $slots);

        // Dates should also reflect user_schedule
        $dates = $engine->getAvailableDates(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate->format('Y-m')
        );

        $dateMap = collect($dates)->keyBy('date');
        $this->assertTrue($dateMap[$futureDate->toDateString()]['has_availability']);
    }

    public function test_prefers_booking_schedules_over_user_schedules(): void
    {
        $futureDate = Carbon::now()->addDay();
        $dowIso = $futureDate->dayOfWeekIso;
        $userDow = $dowIso === 7 ? 0 : $dowIso;

        // Create user_schedule with different hours
        UserSchedule::create([
            'user_id' => $this->user->id,
            'day_of_week' => $userDow,
            'start_time' => '14:00',
            'end_time' => '18:00',
            'enabled' => true,
        ]);

        // professional_schedule already exists in setUp (09:00-13:00)
        // Engine must prefer the booking schedule

        $engine = app(AvailabilityEngine::class);

        $slots = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate->toDateString()
        );

        $this->assertNotEmpty($slots);
        // Should use 09:00-13:00 from professional_schedule, NOT 14:00-18:00 from user_schedule
        $this->assertEquals('09:00', $slots[0]['start']);
        $this->assertCount(13, $slots);
    }

    public function test_uses_user_schedule_exceptions_as_fallback(): void
    {
        $futureDate = Carbon::now()->addDay();
        $dowIso = $futureDate->dayOfWeekIso;
        $userDow = $dowIso === 7 ? 0 : $dowIso;

        // Delete professional_schedule, set up user_schedule
        ProfessionalSchedule::where('professional_id', $this->bp->id)->delete();

        UserSchedule::create([
            'user_id' => $this->user->id,
            'day_of_week' => $userDow,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'enabled' => true,
        ]);

        // Full-day block via user_schedule_exception
        UserScheduleException::create([
            'user_id' => $this->user->id,
            'date' => $futureDate->toDateString(),
            'reason' => 'Bloqueo de equipo',
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

    public function test_null_professional_appointment_blocks_all_professionals(): void
    {
        $futureDate = Carbon::now()->addDay();
        $start = $futureDate->copy()->setTime(10, 0);
        $end = $start->copy()->addHour();

        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Test', 'last_name' => 'Patient']);

        // Create an unassigned appointment (professional_id = null)
        Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'professional_id' => null,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 40,
            'payment_type' => 'single',
        ]);

        $engine = app(AvailabilityEngine::class);

        // Slot should be removed for professional 1
        $slots1 = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            $this->user->id,
            $futureDate->toDateString()
        );
        $this->assertNotContains('10:00', array_column($slots1, 'start'));

        // Slot should be removed for professional 2
        $slots2 = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            $this->user2->id,
            $futureDate->toDateString()
        );
        $this->assertNotContains('10:00', array_column($slots2, 'start'));

        // Slot should be removed from aggregated (null professional) view
        $slotsAll = $engine->getAvailableSlots(
            $this->clinic->id,
            $this->service->id,
            null,
            $futureDate->toDateString()
        );
        $this->assertNotContains('10:00', array_column($slotsAll, 'start'));

        // Slots around 10:00 should still be available
        $this->assertContains('09:00', array_column($slots1, 'start'));
        $this->assertContains('11:00', array_column($slots1, 'start'));
    }
}
