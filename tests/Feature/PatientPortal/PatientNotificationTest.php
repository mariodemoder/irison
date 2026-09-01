<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use App\Events\AppointmentReminderSent;
use App\Events\PaymentCreated;

class PatientNotificationTest extends PatientPortalTestCase
{
    public function test_index_returns_own_notifications_paginated(): void
    {
        $this->makeNotification($this->patient);
        $this->makeNotification($this->patient);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [],
                'total', 'per_page', 'current_page',
            ])
            ->assertJsonPath('total', 2);
    }

    public function test_mark_read_marks_notification_as_read(): void
    {
        $notification = $this->makeNotification($this->patient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_read_other_patient_notification_returns_404(): void
    {
        $notification = $this->makeNotification($this->otherPatient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/notifications/{$notification->id}/read")
            ->assertNotFound();
    }

    public function test_index_excludes_other_patient_notifications(): void
    {
        $this->makeNotification($this->patient);
        $this->makeNotification($this->otherPatient);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/notifications');

        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_payment_created_event_creates_pending_notification(): void
    {
        $payment = $this->makePayment($this->patient, ['status' => 'pending']);

        event(new PaymentCreated($payment));

        $this->assertDatabaseHas('patient_portal_notifications', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'type' => 'payment_pending',
        ]);
    }

    public function test_appointment_reminder_event_creates_reminder_notification(): void
    {
        $appointment = $this->makeAppointment($this->patient);

        event(new AppointmentReminderSent($appointment, '24h'));

        $this->assertDatabaseHas('patient_portal_notifications', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'type' => 'appointment_reminder',
        ]);
    }
}