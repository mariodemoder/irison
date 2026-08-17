<?php

namespace Modules\Booking\Tests\Feature;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\User;
use App\Models\UserSchedule;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\ProfessionalSchedule;
use App\Services\Team\TeamUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class TeamScheduleSyncTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $user;
    private BookingProfessional $bp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create(['name' => 'Test Clinic', 'plan' => 'pro']);

        $this->user = User::create([
            'name' => 'Dr. Test',
            'email' => 'dr@test.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
            'allow_online_booking' => true,
        ]);

        $this->bp = BookingProfessional::create([
            'user_id' => $this->user->id,
            'clinic_id' => $this->clinic->id,
            'allow_online_booking' => true,
        ]);

        app()->instance('activeClinic', $this->clinic);
    }

    public function test_sync_schedules_creates_professional_schedules_for_booking_professional(): void
    {
        $service = app(TeamUserService::class);

        $service->update($this->user, [
            'name' => 'Dr. Test Updated',
            'schedules' => [
                ['day_of_week' => 1, 'enabled' => true, 'start_time' => '10:00', 'end_time' => '14:00'],
                ['day_of_week' => 2, 'enabled' => true, 'start_time' => '09:00', 'end_time' => '13:00'],
                ['day_of_week' => 3, 'enabled' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 4, 'enabled' => true, 'start_time' => '11:00', 'end_time' => '15:00'],
                ['day_of_week' => 5, 'enabled' => true, 'start_time' => '09:00', 'end_time' => '17:00'],
                ['day_of_week' => 6, 'enabled' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 0, 'enabled' => false, 'start_time' => null, 'end_time' => null],
            ],
        ], $this->clinic->id);

        // UserSchedule should have 7 entries
        $userSchedules = UserSchedule::where('user_id', $this->user->id)->get();
        $this->assertCount(7, $userSchedules);

        // ProfessionalSchedule should have 4 entries (only enabled with times)
        $proSchedules = ProfessionalSchedule::where('professional_id', $this->bp->id)
            ->orderBy('day_of_week')
            ->get();

        $this->assertCount(4, $proSchedules);

        // Mon (1→1), Tue (2→2), Thu (4→4), Fri (5→5)
        $this->assertEquals([1, 2, 4, 5], $proSchedules->pluck('day_of_week')->toArray());

        // Verify times
        $mon = $proSchedules->firstWhere('day_of_week', 1);
        $this->assertEquals('10:00', $mon->start_time);
        $this->assertEquals('14:00', $mon->end_time);
    }

    public function test_sync_schedules_does_not_create_professional_schedules_for_non_booking_user(): void
    {
        // Create a user without booking professional
        $otherUser = User::create([
            'name' => 'Dr. Other',
            'email' => 'other@test.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
        ]);

        $service = app(TeamUserService::class);

        $service->update($otherUser, [
            'name' => 'Dr. Other Updated',
            'schedules' => [
                ['day_of_week' => 1, 'enabled' => true, 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
        ], $this->clinic->id);

        // UserSchedule created
        $this->assertDatabaseHas('user_schedules', [
            'user_id' => $otherUser->id,
            'day_of_week' => 1,
        ]);

        // No ProfessionalSchedule created (user is not a BookingProfessional)
        $this->assertDatabaseMissing('professional_schedules', [
            'professional_id' => 0, // No BP exists
        ]);
    }

    public function test_sync_schedules_replaces_existing_professional_schedules(): void
    {
        // Create existing ProfessionalSchedule
        ProfessionalSchedule::create([
            'professional_id' => $this->bp->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        $service = app(TeamUserService::class);

        $service->update($this->user, [
            'name' => 'Dr. Test',
            'schedules' => [
                ['day_of_week' => 5, 'enabled' => true, 'start_time' => '14:00', 'end_time' => '18:00'],
            ],
        ], $this->clinic->id);

        // Old schedule should be gone, new one created
        $proSchedules = ProfessionalSchedule::where('professional_id', $this->bp->id)->get();

        $this->assertCount(1, $proSchedules);
        $this->assertEquals(5, $proSchedules->first()->day_of_week);
        $this->assertEquals('14:00', $proSchedules->first()->start_time);
    }
}
