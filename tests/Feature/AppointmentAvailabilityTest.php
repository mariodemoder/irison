<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AppointmentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_appointment_success()
    {
        $clinic = Clinic::create(['name' => 'Test Clinic']);
        app()->instance('activeClinic', $clinic);

        /** @var \App\Models\User $user */
        $user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);

        $patient = Patient::create(['first_name' => 'Juan', 'last_name' => 'Perez']);

        $start = Carbon::now()->addDay()->startOfHour()->addHours(9); // mañana 9:00
        $end = (clone $start)->addHour(); // 10:00

        $payload = [
            'patient_id' => $patient->id,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
        ];

        $response = $this->postJson('/api/appointments', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_reject_overlapping_appointment()
    {
        $clinic = Clinic::create(['name' => 'Test Clinic']);
        app()->instance('activeClinic', $clinic);

        $user = User::create([
            'name' => 'Test User 2',
            'email' => 'testuser2@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);

        $patient1 = Patient::create(['first_name' => 'Ana', 'last_name' => 'Gomez']);
        $patient2 = Patient::create(['first_name' => 'Luis', 'last_name' => 'Martinez']);

        $start1 = Carbon::now()->addDay()->startOfHour()->addHours(10); // mañana 10:00
        $end1 = (clone $start1)->addHour(); // 11:00

        Appointment::create([
            'patient_id' => $patient1->id,
            'clinic_id' => $clinic->id,
            'start_time' => $start1,
            'end_time' => $end1,
        ]);

        // Intentar crear cita que solapa: 10:30 - 11:30
        $start2 = (clone $start1)->addMinutes(30);
        $end2 = (clone $start2)->addHour();

        $payload = [
            'patient_id' => $patient2->id,
            'start_time' => $start2->toDateTimeString(),
            'end_time' => $end2->toDateTimeString(),
        ];

        $response = $this->postJson('/api/appointments', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment(['La franja horaria se solapa con otra cita.']);
    }
}
