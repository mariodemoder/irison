<?php

namespace Modules\Booking\Tests\Feature;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\User;
use App\Models\UserSchedule;
use Modules\Booking\Models\BookingPage;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\ProfessionalSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_auto_creates_booking_professional(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Dr. Test',
            'clinic_name' => 'Clinica Test',
            'email' => 'test@clinic.com',
            'password' => 'password123',
            'nif' => '12345678Z',
            'zip' => '28001',
            'phone' => '600123456',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'test@clinic.com')->first();
        $this->assertNotNull($user);

        $bp = BookingProfessional::where('user_id', $user->id)->first();
        $this->assertNotNull($bp);
        $this->assertTrue($bp->allow_online_booking);
        $this->assertEquals($user->clinic_id, $bp->clinic_id);
    }

    public function test_registration_auto_creates_booking_page(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Dr. Test',
            'clinic_name' => 'Clinica Test',
            'email' => 'test@clinic.com',
            'password' => 'password123',
            'nif' => '12345678Z',
            'zip' => '28001',
            'phone' => '600123456',
        ]);

        $response->assertStatus(201);

        $clinic = Clinic::where('name', 'Clinica Test')->first();
        $this->assertNotNull($clinic);

        $page = BookingPage::where('clinic_id', $clinic->id)->first();
        $this->assertNotNull($page);
        $this->assertTrue($page->is_active);
        $this->assertEquals('clinica-test', $page->slug);
        $this->assertEquals(60, $page->max_horizon_days);
        $this->assertEquals(24, $page->cancellation_hours);
    }

    public function test_registration_auto_creates_default_schedules(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Dr. Test',
            'clinic_name' => 'Clinica Test',
            'email' => 'test@clinic.com',
            'password' => 'password123',
            'nif' => '12345678Z',
            'zip' => '28001',
            'phone' => '600123456',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'test@clinic.com')->first();
        $bp = BookingProfessional::where('user_id', $user->id)->first();

        // UserSchedule: Mon-Fri enabled (days 1-5)
        $userSchedules = UserSchedule::where('user_id', $user->id)
            ->where('enabled', true)
            ->orderBy('day_of_week')
            ->get();

        $this->assertCount(5, $userSchedules);
        $this->assertEquals([1, 2, 3, 4, 5], $userSchedules->pluck('day_of_week')->toArray());
        $this->assertEquals('09:00', $userSchedules->first()->start_time);
        $this->assertEquals('17:00', $userSchedules->first()->end_time);

        // ProfessionalSchedule: Mon-Fri (ISO 1-5)
        $proSchedules = ProfessionalSchedule::where('professional_id', $bp->id)
            ->orderBy('day_of_week')
            ->get();

        $this->assertCount(5, $proSchedules);
        $this->assertEquals([1, 2, 3, 4, 5], $proSchedules->pluck('day_of_week')->toArray());
        $this->assertEquals('09:00', $proSchedules->first()->start_time);
        $this->assertEquals('17:00', $proSchedules->first()->end_time);
    }

    public function test_registration_booking_page_slug_handles_collision(): void
    {
        // Create a clinic with the same name to force slug collision
        $clinic = Clinic::create(['name' => 'Clinica Test', 'plan' => 'basic']);
        BookingPage::create([
            'clinic_id' => $clinic->id,
            'slug' => 'clinica-test',
            'title' => 'Reserva',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Dr. Test',
            'clinic_name' => 'Clinica Test',
            'email' => 'test2@clinic.com',
            'password' => 'password123',
            'nif' => '12345678Z',
            'zip' => '28001',
            'phone' => '600123456',
        ]);

        $response->assertStatus(201);

        $newClinic = Clinic::where('name', 'Clinica Test')->where('id', '!=', $clinic->id)->first();
        $page = BookingPage::where('clinic_id', $newClinic->id)->first();

        $this->assertNotNull($page);
        $this->assertNotEquals('clinica-test', $page->slug);
        $this->assertStringStartsWith('clinica-test-', $page->slug);
    }
}
