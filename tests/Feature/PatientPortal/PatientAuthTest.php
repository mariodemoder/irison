<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use App\Models\Patient;
use App\Notifications\PatientResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PatientAuthTest extends PatientPortalTestCase
{
    public function test_patient_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/patient/auth/login', [
            'email' => $this->patient->email,
            'password' => $this->plainPassword,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'patient' => ['id', 'first_name', 'last_name', 'email', 'clinic_id'],
            ]);

        $this->assertSame($this->patient->id, $response->json('patient.id'));
    }

    public function test_patient_cannot_login_with_wrong_password(): void
    {
        $response = $this->postJson('/api/patient/auth/login', [
            'email' => $this->patient->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_patient_cannot_login_with_unknown_email(): void
    {
        $response = $this->postJson('/api/patient/auth/login', [
            'email' => 'noexiste@portal.test',
            'password' => $this->plainPassword,
        ]);

        $response->assertUnauthorized();
    }

    public function test_inactive_patient_cannot_login(): void
    {
        $this->patient->update(['status' => 'inactive']);

        $response = $this->postJson('/api/patient/auth/login', [
            'email' => $this->patient->email,
            'password' => $this->plainPassword,
        ]);

        $response->assertForbidden();
    }

    public function test_patient_can_logout(): void
    {
        $response = $this->withHeaders($this->patientHeaders())
            ->postJson('/api/patient/auth/logout');

        $response->assertOk();

        // The token used to logout must be revoked.
        $this->assertSame(0, $this->patient->tokens()->count());
    }

    public function test_patient_can_get_me(): void
    {
        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/auth/me');

        $response->assertOk()
            ->assertJsonFragment(['email' => $this->patient->email]);
    }

    public function test_unauthenticated_patient_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/patient/dashboard');

        $response->assertUnauthorized();
    }

    public function test_login_updates_last_login_at(): void
    {
        $freshPatient = $this->makePatient($this->clinic, 'fresh@portal.test');

        $this->assertNull($freshPatient->fresh()->last_login_at);

        $this->postJson('/api/patient/auth/login', [
            'email' => $freshPatient->email,
            'password' => $this->plainPassword,
        ])->assertOk();

        $this->assertNotNull($freshPatient->fresh()->last_login_at);
    }

    public function test_login_creates_audit_log(): void
    {
        $this->postJson('/api/patient/auth/login', [
            'email' => $this->patient->email,
            'password' => $this->plainPassword,
        ])->assertOk();

        $this->assertDatabaseHas('patient_audit_logs', [
            'patient_id' => $this->patient->id,
            'clinic_id' => $this->clinic->id,
            'event' => 'patient_logged_in',
        ]);
    }

    public function test_password_reset_flow_works(): void
    {
        // Request a reset token directly via the broker (email is not dispatched in tests).
        $token = Password::broker('patients')->createToken($this->patient);

        $response = $this->postJson('/api/patient/auth/reset-password', [
            'token' => $token,
            'email' => $this->patient->email,
            'password' => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ]);

        $response->assertOk();

        // Old tokens must be revoked and the password updated.
        $this->assertTrue(Hash::check('newpassword456', $this->patient->fresh()->password));
        $this->assertSame(0, $this->patient->tokens()->count());

        // Can log in with the new password.
        $this->postJson('/api/patient/auth/login', [
            'email' => $this->patient->email,
            'password' => 'newpassword456',
        ])->assertOk();
    }

    public function test_forgot_password_returns_neutral_response(): void
    {
        $response = $this->postJson('/api/patient/auth/forgot-password', [
            'email' => $this->patient->email,
        ]);

        $response->assertOk();

        // Unknown emails also return a neutral 200 (no enumeration).
        $this->postJson('/api/patient/auth/forgot-password', [
            'email' => 'desconocido@portal.test',
        ])->assertOk();
    }

    public function test_patient_reset_email_points_to_patient_route_with_clinic(): void
    {
        $token = Password::broker('patients')->createToken($this->patient);

        $mail = (new PatientResetPasswordNotification($token))->toMail($this->patient);

        // El enlace debe apuntar al SPA del paciente (no a la ruta de staff /reset-password).
        $this->assertSame('/patient/reset-password', parse_url($mail->actionUrl, PHP_URL_PATH));
        $this->assertStringContainsString('clinic=' . $this->clinic->slug, $mail->actionUrl);
    }

    public function test_patient_reset_email_uses_clinic_from_name(): void
    {
        $token = Password::broker('patients')->createToken($this->patient);

        $mail = (new PatientResetPasswordNotification($token))->toMail($this->patient);

        $this->assertSame($this->clinic->name, $mail->from[1] ?? null);
    }
}
