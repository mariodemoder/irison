<?php

namespace Tests\Feature\Appointments;

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
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Juan',
            'last_name' => 'Perez',
        ]);

        $date = Carbon::now()->addDay();
        $start = (clone $date)->startOfHour()->addHours(9); // mañana 9:00
        $end = (clone $start)->addHour(); // 10:00

        $payload = [
            'patient_id' => $patient->id,
            'date' => $date->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'price' => 40,
            'payment_type' => 'single',
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
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        $patient1 = Patient::create(['clinic_id' => $clinic->id, 'first_name' => 'Ana', 'last_name' => 'Gomez']);
        $patient2 = Patient::create(['clinic_id' => $clinic->id, 'first_name' => 'Luis', 'last_name' => 'Martinez']);

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
            'date' => $start2->format('Y-m-d'),
            'start_time' => $start2->format('H:i'),
            'end_time' => $end2->format('H:i'),
            'price' => 50,
            'payment_type' => 'single',
        ];

        $response = $this->postJson('/api/appointments', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'La franja horaria se solapa con otra cita.');
    }

    public function test_create_appointment_accepts_datetime_local_payload_without_date_field()
    {
        $clinic = Clinic::create(['name' => 'Test Clinic']);
        app()->instance('activeClinic', $clinic);

        $user = User::create([
            'name' => 'Test User 3',
            'email' => 'testuser3@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Carlos',
            'last_name' => 'Ruiz',
        ]);

        $start = Carbon::now()->addDay()->setTime(9, 0);
        $end = (clone $start)->addHour();

        $payload = [
            'patient_id' => $patient->id,
            'start_time' => $start->format('Y-m-d\\TH:i'),
            'end_time' => $end->format('Y-m-d\\TH:i'),
            'price' => 40,
            'payment_type' => 'single',
        ];

        $response = $this->postJson('/api/appointments', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_update_appointment_accepts_date_and_hhmm_payload()
    {
        $clinic = Clinic::create(['name' => 'Test Clinic']);
        app()->instance('activeClinic', $clinic);

        $user = User::create([
            'name' => 'Test User 4',
            'email' => 'testuser4@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Mario',
            'last_name' => 'Lopez',
        ]);

        $originalStart = Carbon::now()->addDay()->setTime(8, 0);
        $originalEnd = (clone $originalStart)->addHour();

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'start_time' => $originalStart,
            'end_time' => $originalEnd,
            'price' => 40,
            'payment_type' => 'single',
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $newDate = $originalStart->copy()->toDateString();
        $payload = [
            'date' => $newDate,
            'start_time' => '09:00',
            'end_time' => '10:30',
            'price' => 40,
            'payment_type' => 'single',
        ];

        $response = $this->putJson('/api/appointments/' . $appointment->id, $payload);

        $response->assertOk();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'start_time' => $newDate . ' 09:00:00',
            'end_time' => $newDate . ' 10:30:00',
        ]);
    }

    public function test_reprogram_can_move_status_from_canceled_to_rescheduled()
    {
        $clinic = Clinic::create(['name' => 'Test Clinic']);
        app()->instance('activeClinic', $clinic);

        $user = User::create([
            'name' => 'Test User 5',
            'email' => 'testuser5@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Lucia',
            'last_name' => 'Perez',
        ]);

        $start = Carbon::now()->addDay()->setTime(9, 0);
        $end = (clone $start)->addHour();

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'canceled',
            'payment_status' => 'pending',
            'price' => 50,
            'payment_type' => 'single',
        ]);

        $payload = [
            'date' => $start->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'rescheduled',
            'price' => 50,
            'payment_type' => 'single',
        ];

        $response = $this->patchJson('/api/appointments/' . $appointment->id, $payload);

        $response->assertOk();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'rescheduled',
            'start_time' => $start->toDateString() . ' 10:00:00',
            'end_time' => $start->toDateString() . ' 11:00:00',
        ]);
    }

}
