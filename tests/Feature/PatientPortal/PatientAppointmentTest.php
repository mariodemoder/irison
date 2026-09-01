<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use App\Models\BonusUsage;

class PatientAppointmentTest extends PatientPortalTestCase
{
    public function test_upcoming_returns_only_own_future_scheduled_or_confirmed_appointments(): void
    {
        $this->makeAppointment($this->patient, ['start_time' => now()->addDays(2)->setTime(9, 0), 'status' => 'scheduled']);
        $this->makeAppointment($this->patient, ['start_time' => now()->addDays(4)->setTime(10, 0), 'status' => 'confirmed']);
        // Past and cancelled appointments must not appear as upcoming.
        $this->makeAppointment($this->patient, ['start_time' => now()->subDay()->setTime(9, 0), 'status' => 'scheduled']);
        $this->makeAppointment($this->patient, ['start_time' => now()->addDays(6)->setTime(9, 0), 'status' => 'canceled']);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/appointments/upcoming');

        $response->assertOk()
            ->assertJsonCount(2, 'appointments');
    }

    public function test_history_returns_own_appointments_paginated(): void
    {
        $this->makeAppointment($this->patient, ['start_time' => now()->subDays(2)->setTime(9, 0)]);
        $this->makeAppointment($this->patient, ['start_time' => now()->subDays(1)->setTime(10, 0)]);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/appointments/history');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [],
                'total', 'per_page', 'current_page',
            ])
            ->assertJsonPath('total', 2);
    }

    public function test_show_returns_own_appointment(): void
    {
        $appointment = $this->makeAppointment($this->patient);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/appointments/{$appointment->id}");

        $response->assertOk()
            ->assertJsonPath('appointment.id', $appointment->id)
            ->assertJsonPath('appointment.patient_id', $this->patient->id);
    }

    public function test_request_creates_appointment_with_booking_source_and_audit_log(): void
    {
        $response = $this->withHeaders($this->patientHeaders())
            ->postJson('/api/patient/appointments/requests', [
                'preferred_date' => now()->addDays(5)->format('Y-m-d'),
                'preferred_time' => '12:30',
                'service_name' => 'Fisioterapia',
                'notes' => 'Prefiero por la tarde',
            ]);

        $response->assertCreated()
            ->assertJsonPath('appointment.status', 'scheduled')
            ->assertJsonPath('appointment.booking_source', 'patient_portal');

        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'booking_source' => 'patient_portal',
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('patient_audit_logs', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'event' => 'patient_appointment_requested',
        ]);
    }

    public function test_request_validates_preferred_date(): void
    {
        $this->withHeaders($this->patientHeaders())
            ->postJson('/api/patient/appointments/requests', [
                'preferred_time' => '12:30',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preferred_date');
    }

    public function test_cancel_within_24_hours_is_denied(): void
    {
        $appointment = $this->makeAppointment($this->patient, [
            'start_time' => now()->addHours(2),
        ]);

        $response = $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/appointments/{$appointment->id}/cancel");

        $response->assertForbidden()
            ->assertJsonPath('message', 'No es posible cancelar con menos de 24h de antelación. Contacte con la clínica.');
    }

    public function test_cancel_more_than_24_hours_ahead_sets_canceled(): void
    {
        $appointment = $this->makeAppointment($this->patient);

        $response = $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/appointments/{$appointment->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('message', 'Cita cancelada correctamente.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'canceled',
        ]);

        $this->assertDatabaseHas('patient_audit_logs', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'event' => 'patient_appointment_cancelled',
        ]);
    }

    public function test_cancel_more_than_24_hours_ahead_restores_bonus_usage(): void
    {
        $bonus = $this->makeBonus($this->patient, ['total_sessions' => 10, 'remaining_sessions' => 4]);
        $appointment = $this->makeAppointment($this->patient, ['bonus_id' => $bonus->id]);

        BonusUsage::create([
            'clinic_id' => $this->clinic->id,
            'bonus_id' => $bonus->id,
            'appointment_id' => $appointment->id,
            'used_at' => now(),
        ]);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/appointments/{$appointment->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('bonuses', [
            'id' => $bonus->id,
            'remaining_sessions' => 5,
        ]);

        $this->assertDatabaseMissing('bonus_usages', [
            'appointment_id' => $appointment->id,
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'canceled',
        ]);
    }

    public function test_reschedule_marks_old_appointment_and_creates_new_one(): void
    {
        $appointment = $this->makeAppointment($this->patient);

        $response = $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/appointments/{$appointment->id}/reschedule", [
                'preferred_date' => now()->addDays(7)->format('Y-m-d'),
                'preferred_time' => '09:15',
                'service_name' => 'Osteopatía',
            ]);

        $response->assertCreated()
            ->assertJsonPath('appointment.status', 'scheduled')
            ->assertJsonPath('appointment.booking_source', 'patient_portal');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'rescheduled',
        ]);
    }

    public function test_history_filters_by_status(): void
    {
        $this->makeAppointment($this->patient, ['start_time' => now()->subDays(3)->setTime(9, 0), 'status' => 'canceled']);
        $this->makeAppointment($this->patient, ['start_time' => now()->subDays(2)->setTime(9, 0), 'status' => 'completed']);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/appointments/history?status=completed');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.status', 'completed');
    }
}