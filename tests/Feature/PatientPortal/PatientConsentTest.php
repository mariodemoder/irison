<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

class PatientConsentTest extends PatientPortalTestCase
{
    public function test_index_returns_own_consents(): void
    {
        $this->makeConsentFor($this->patient);
        $this->makeConsentFor($this->patient);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/consents');

        $response->assertOk()
            ->assertJsonCount(2, 'consents');
    }

    public function test_show_returns_own_consent(): void
    {
        $consent = $this->makeConsentFor($this->patient);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/consents/{$consent->id}");

        $response->assertOk()
            ->assertJsonPath('consent.id', $consent->id)
            ->assertJsonPath('consent.status', 'sent');
    }

    public function test_sign_requires_signature_svg(): void
    {
        $consent = $this->makeConsentFor($this->patient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/consents/{$consent->id}/sign", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature_svg');
    }

    public function test_sign_marks_consent_signed_and_creates_audit_log(): void
    {
        $consent = $this->makeConsentFor($this->patient);

        $response = $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/consents/{$consent->id}/sign", [
                'signature_svg' => '<svg>mi-firma</svg>',
            ]);

        $response->assertOk()
            ->assertJsonPath('consent.status', 'signed');

        $this->assertDatabaseHas('patient_consents', [
            'id' => $consent->id,
            'status' => 'signed',
        ]);

        $this->assertDatabaseHas('patient_audit_logs', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'event' => 'patient_consent_signed',
        ]);
    }

    public function test_sign_already_signed_consent_returns_400(): void
    {
        $consent = $this->makeConsentFor($this->patient, ['status' => 'signed']);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/consents/{$consent->id}/sign", [
                'signature_svg' => '<svg>firma</svg>',
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Este consentimiento ya ha sido firmado o revocado.');
    }

    public function test_sign_foreign_clinic_consent_returns_404(): void
    {
        $consent = $this->makeConsentFor($this->foreignPatient);

        $this->withHeaders($this->patientHeaders())
            ->postJson("/api/patient/consents/{$consent->id}/sign", [
                'signature_svg' => '<svg>firma</svg>',
            ])
            ->assertNotFound();
    }
}