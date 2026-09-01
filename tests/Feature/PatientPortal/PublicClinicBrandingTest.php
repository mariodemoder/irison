<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

class PublicClinicBrandingTest extends PatientPortalTestCase
{
    public function test_public_branding_returns_clinic_by_slug(): void
    {
        $response = $this->getJson('/api/patient/public/branding/' . $this->clinic->slug);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => $this->clinic->name,
                'slug' => $this->clinic->slug,
                'logo_url' => null, // sin logo subido en el test
            ]);
    }

    public function test_public_branding_is_public_without_authentication(): void
    {
        $this->getJson('/api/patient/public/branding/' . $this->clinic->slug)
            ->assertOk();
    }

    public function test_public_branding_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/patient/public/branding/clinica-inexistente')
            ->assertNotFound();
    }

    public function test_me_includes_clinic_branding(): void
    {
        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/auth/me');

        $response->assertOk()
            ->assertJsonFragment([
                'name' => $this->clinic->name,
                'slug' => $this->clinic->slug,
                'logo_url' => null,
            ]);
    }

    public function test_login_includes_clinic_branding(): void
    {
        $response = $this->postJson('/api/patient/auth/login', [
            'email' => $this->patient->email,
            'password' => $this->plainPassword,
        ]);

        $response->assertOk()
            ->assertJsonPath('patient.clinic.name', $this->clinic->name)
            ->assertJsonPath('patient.clinic.slug', $this->clinic->slug);
    }
}