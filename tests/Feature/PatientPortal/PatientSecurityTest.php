<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

class PatientSecurityTest extends PatientPortalTestCase
{
    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $patient = $this->makePatient($this->clinic, 'ratelimit@portal.test');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/patient/auth/login', [
                'email' => $patient->email,
                'password' => $this->plainPassword,
            ])->assertOk();
        }

        $this->postJson('/api/patient/auth/login', [
            'email' => $patient->email,
            'password' => $this->plainPassword,
        ])->assertStatus(429);
    }

    public function test_forgot_password_is_rate_limited_after_three_attempts(): void
    {
        $email = $this->patient->email;

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/patient/auth/forgot-password', [
                'email' => $email,
            ])->assertOk();
        }

        $this->postJson('/api/patient/auth/forgot-password', [
            'email' => $email,
        ])->assertStatus(429);
    }

    public function test_login_replaces_previous_tokens(): void
    {
        $this->postJson('/api/patient/auth/login', [
            'email' => $this->patient->email,
            'password' => $this->plainPassword,
        ])->assertOk();

        // setUp already created one token; a second login must leave only one.
        $this->assertSame(1, $this->patient->tokens()->count());
    }

    public function test_reset_password_with_invalid_token_returns_400(): void
    {
        $this->withHeaders($this->patientHeaders())
            ->postJson('/api/patient/auth/reset-password', [
                'token' => 'invalid-token',
                'email' => $this->patient->email,
                'password' => 'newpassword456',
                'password_confirmation' => 'newpassword456',
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Token inválido o expirado.');
    }

    public function test_inactive_patient_token_is_rejected(): void
    {
        $patient = $this->makePatient($this->clinic, 'will-inactivate@portal.test');
        $token = $this->loginAsPatient($patient);

        $patient->update(['status' => 'inactive']);

        $this->withHeaders($this->patientHeaders($token))
            ->getJson('/api/patient/auth/me')
            ->assertForbidden();
    }

    public function test_token_is_created_with_patient_ability_only(): void
    {
        $token = $this->patient->tokens()->first();

        $this->assertNotNull($token);
        $this->assertTrue($token->can('patient'));
        $this->assertFalse($token->can('admin'));
        $this->assertSame(['patient'], $token->abilities);
    }
}