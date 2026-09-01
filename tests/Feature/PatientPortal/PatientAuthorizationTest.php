<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use App\Models\Bonus;

/**
 * IDOR / cross-tenant isolation for the Patient Portal.
 *
 * A patient must never see, mutate or consume resources that belong to other
 * patients (same clinic) or to patients of other clinics.
 */
class PatientAuthorizationTest extends PatientPortalTestCase
{
    public function test_cannot_show_other_patient_same_clinic_appointment(): void
    {
        $appointment = $this->makeAppointment($this->otherPatient);

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/appointments/{$appointment->id}")
            ->assertNotFound();
    }

    public function test_cannot_show_foreign_clinic_appointment(): void
    {
        $appointment = $this->makeAppointment($this->foreignPatient);

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/appointments/{$appointment->id}")
            ->assertNotFound();
    }

    public function test_cannot_cancel_other_patient_appointment(): void
    {
        $appointment = $this->makeAppointment($this->otherPatient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/appointments/{$appointment->id}/cancel")
            ->assertNotFound();
    }

    public function test_cannot_cancel_foreign_clinic_appointment(): void
    {
        $appointment = $this->makeAppointment($this->foreignPatient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/appointments/{$appointment->id}/cancel")
            ->assertNotFound();
    }

    public function test_cannot_reschedule_foreign_clinic_appointment(): void
    {
        $appointment = $this->makeAppointment($this->foreignPatient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/appointments/{$appointment->id}/reschedule", [
                'preferred_date' => now()->addDays(5)->format('Y-m-d'),
                'preferred_time' => '12:00',
            ])
            ->assertNotFound();
    }

    public function test_cannot_show_other_patient_bonus(): void
    {
        $bonus = $this->makeBonus($this->otherPatient);

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/bonuses/{$bonus->id}")
            ->assertNotFound();
    }

    public function test_cannot_show_foreign_clinic_bonus(): void
    {
        $bonus = $this->makeBonus($this->foreignPatient);

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/bonuses/{$bonus->id}")
            ->assertNotFound();
    }

    public function test_cannot_show_other_patient_consent(): void
    {
        $consent = $this->makeConsentFor($this->otherPatient);

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/consents/{$consent->id}")
            ->assertNotFound();
    }

    public function test_cannot_sign_foreign_clinic_consent(): void
    {
        $consent = $this->makeConsentFor($this->foreignPatient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/consents/{$consent->id}/sign", [
                'signature_svg' => '<svg>firma</svg>',
            ])
            ->assertNotFound();
    }

    public function test_cannot_show_other_patient_document(): void
    {
        $document = $this->makeDocumentFor($this->otherPatient, 'FR-000100');

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/documents/{$document->id}")
            ->assertNotFound();
    }

    public function test_cannot_show_foreign_clinic_document(): void
    {
        $document = $this->makeDocumentFor($this->foreignPatient, 'FR-000200');

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/documents/{$document->id}")
            ->assertNotFound();
    }

    public function test_cannot_mark_read_foreign_clinic_notification(): void
    {
        $notification = $this->makeNotification($this->foreignPatient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/notifications/{$notification->id}/read")
            ->assertNotFound();
    }

    public function test_dashboard_excludes_other_patient_data(): void
    {
        $this->makeBonus($this->otherPatient, ['total_sessions' => 3, 'remaining_sessions' => 3]);
        $this->makeBonus($this->patient, ['total_sessions' => 9, 'remaining_sessions' => 9]);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/dashboard');

        $response->assertOk()
            ->assertJsonPath('bonuses_summary.active_count', 1)
            ->assertJsonPath('bonuses_summary.total_remaining_sessions', 9);
    }
}