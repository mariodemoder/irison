<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

class PatientProfileTest extends PatientPortalTestCase
{
    public function test_index_returns_own_profile(): void
    {
        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/profile');

        $response->assertOk()
            ->assertJsonPath('patient.id', $this->patient->id)
            ->assertJsonPath('patient.email', $this->patient->email)
            ->assertJsonPath('patient.clinic_id', $this->clinic->id);
    }

    public function test_update_changes_fields_and_creates_audit_log(): void
    {
        $response = $this->withHeaders($this->patientHeaders())
            ->putJson('/api/patient/profile', [
                'first_name' => 'Nuevo',
                'last_name' => 'Nombre',
                'phone' => '600111222',
                'city' => 'Madrid',
            ]);

        $response->assertOk()
            ->assertJsonPath('patient.first_name', 'Nuevo')
            ->assertJsonPath('patient.city', 'Madrid');

        $this->assertDatabaseHas('patients', [
            'id' => $this->patient->id,
            'phone' => '600111222',
        ]);

        $this->assertDatabaseHas('patient_audit_logs', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'event' => 'patient_profile_updated',
        ]);
    }

    public function test_update_validates_max_length(): void
    {
        $this->withHeaders($this->patientHeaders())
            ->putJson('/api/patient/profile', [
                'first_name' => str_repeat('a', 300),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');
    }

    public function test_password_is_never_serialized_in_profile_response(): void
    {
        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/profile');

        $response->assertOk();
        $response->assertJsonMissingPath('patient.password');
    }
}