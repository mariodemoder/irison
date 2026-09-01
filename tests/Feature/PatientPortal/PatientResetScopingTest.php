<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use App\Models\Clinic;
use App\Models\Patient;
use App\Notifications\PatientResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Acotado del flujo forgot/reset del Portal del Paciente por clínica.
 *
 * Verifica que:
 *  - un email compartido entre clínicas se resuelve por la clínica del slug
 *    (fix "email con otro nombre de clínica"),
 *  - el reset solo cambia la contraseña del paciente de la clínica correcta
 *    (fix "login falla tras guardar la contraseña"),
 *  - las clínicas sin slug de portal quedan fuera de todo el circuito,
 *  - el token de reset es por clinic-patient (independiente por paciente),
 *  - los pacientes sin acceso activo no reciben link de reset.
 */
class PatientResetScopingTest extends PatientPortalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reconstruir dos clínicas con slug (la del padre las crea con slug).
        $this->clinic = Clinic::create([
            'name' => 'Clinica A',
            'slug' => 'clinica-a',
            'email' => 'a@clinic.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
        ]);
        $this->otherClinic = Clinic::create([
            'name' => 'Clinica B',
            'slug' => 'clinica-b',
            'email' => 'b@clinic.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
        ]);

        // Mismo email en las dos clínicas con slug.
        $this->patient = $this->makePatient($this->clinic, 'duplicado@portal.test');
        $this->otherPatient = $this->makePatient($this->otherClinic, 'duplicado@portal.test');
    }

    public function test_reset_email_is_scoped_to_the_clinic_from_slug(): void
    {
        $token = Password::broker('patients')->createToken($this->patient);

        // Genera el mail desde el paciente de la clínica A.
        $mail = (new PatientResetPasswordNotification($token))->toMail($this->patient);

        $this->assertSame('Clinica A', $mail->from[1] ?? null);
        $this->assertStringContainsString('clinic=clinica-a', $mail->actionUrl);

        // El mismo proceso con el paciente de la clínica B lleva el branding de B.
        $mailB = (new PatientResetPasswordNotification($token))->toMail($this->otherPatient);
        $this->assertSame('Clinica B', $mailB->from[1] ?? null);
        $this->assertStringContainsString('clinic=clinica-b', $mailB->actionUrl);
    }

    public function test_reset_token_is_independent_per_patient(): void
    {
        // Cada clinic-patient debe tener su propio token almacenado.
        $tokenA = Password::broker('patients')->createToken($this->patient);

        // Crear un token para el paciente de la clínica B no invalida el de A.
        Password::broker('patients')->createToken($this->otherPatient);

        $this->assertDatabaseHas('patient_password_reset_tokens', [
            'email' => (string) $this->patient->id,
        ]);
        $this->assertDatabaseHas('patient_password_reset_tokens', [
            'email' => (string) $this->otherPatient->id,
        ]);

        // El token de A sigue siendo válido (independiente de B).
        $this->postJson('/api/patient/auth/reset-password', [
            'token' => $tokenA,
            'email' => $this->patient->email,
            'clinic' => 'clinica-a',
            'password' => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ])->assertOk();
    }

    public function test_reset_only_changes_the_password_of_the_scoped_clinic_patient(): void
    {
        $oldPasswordA = $this->plainPassword;
        $oldPasswordB = $this->plainPassword;

        $token = Password::broker('patients')->createToken($this->patient);

        // Reset acotado a la clínica A (slug en el payload).
        $this->postJson('/api/patient/auth/reset-password', [
            'token' => $token,
            'email' => $this->patient->email,
            'clinic' => 'clinica-a',
            'password' => 'nuevaClaveA1',
            'password_confirmation' => 'nuevaClaveA1',
        ])->assertOk();

        // El paciente de A tiene la nueva contraseña.
        $this->assertTrue(Hash::check('nuevaClaveA1', $this->patient->fresh()->password));

        // El paciente de B (mismo email) conserva la suya.
        $this->assertTrue(Hash::check($oldPasswordB, $this->otherPatient->fresh()->password));

        // Login de B con su contraseña original sigue funcionando (fix problema #2).
        $this->postJson('/api/patient/auth/login', [
            'email' => $this->otherPatient->email,
            'password' => $oldPasswordB,
            'clinic' => 'clinica-b',
        ])->assertOk();

        // A puede loguear con la nueva contraseña.
        $this->postJson('/api/patient/auth/login', [
            'email' => $this->patient->email,
            'password' => 'nuevaClaveA1',
            'clinic' => 'clinica-a',
        ])->assertOk();
    }

    public function test_login_is_scoped_by_clinic(): void
    {
        // El mismo email en dos clínicas con contraseñas distintas.
        $patientA = $this->makePatient($this->clinic, 'dist@portal.test');
        $patientB = $this->makePatient($this->otherClinic, 'dist@portal.test');
        $patientA->forceFill(['password' => Hash::make('claveA1')])->save();
        $patientB->forceFill(['password' => Hash::make('claveB1')])->save();

        $this->postJson('/api/patient/auth/login', [
            'email' => 'dist@portal.test',
            'password' => 'claveA1',
            'clinic' => 'clinica-a',
        ])->assertOk();

        $this->postJson('/api/patient/auth/login', [
            'email' => 'dist@portal.test',
            'password' => 'claveB1',
            'clinic' => 'clinica-b',
        ])->assertOk();

        // Credenciales cruzadas fallan.
        $this->postJson('/api/patient/auth/login', [
            'email' => 'dist@portal.test',
            'password' => 'claveA1',
            'clinic' => 'clinica-b',
        ])->assertStatus(401);
    }

    public function test_clinic_without_slug_is_excluded_from_the_whole_circuit(): void
    {
        $noSlugClinic = Clinic::create([
            'name' => 'Clinica Sin Slug',
            'slug' => null,
            'email' => 'sin@clinic.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
        ]);
        $patient = $this->makePatient($noSlugClinic, 'sin-slug@portal.test');

        // Login rechazado.
        $this->postJson('/api/patient/auth/login', [
            'email' => $patient->email,
            'password' => $this->plainPassword,
        ])->assertStatus(401);

        // Forgot: respuesta neutral pero NO se emite ningún token.
        $this->postJson('/api/patient/auth/forgot-password', [
            'email' => $patient->email,
        ])->assertOk();

        $this->assertDatabaseMissing('patient_password_reset_tokens', [
            'email' => (string) $patient->id,
        ]);

        // Un token emitido previamente tampoco sirve para las rutas autenticadas.
        $this->assertFalse($patient->canUsePortal());
    }

    public function test_inactive_patient_does_not_receive_a_reset_link(): void
    {
        $inactive = $this->makePatient($this->clinic, 'inactivo@portal.test', 'inactive');

        $this->postJson('/api/patient/auth/forgot-password', [
            'email' => $inactive->email,
            'clinic' => 'clinica-a',
        ])->assertOk();

        // Neutral pero sin token emitido para un paciente sin acceso.
        $this->assertDatabaseMissing('patient_password_reset_tokens', [
            'email' => (string) $inactive->id,
        ]);
    }

    public function test_forgot_scoped_to_clinic_resolves_the_right_patient(): void
    {
        // Email duplicado en A y B. Pedir reset con slug de B debe emitir el
        // token para el paciente de B (no para el de A).
        $this->postJson('/api/patient/auth/forgot-password', [
            'email' => $this->patient->email,
            'clinic' => 'clinica-b',
        ])->assertOk();

        $this->assertDatabaseHas('patient_password_reset_tokens', [
            'email' => (string) $this->otherPatient->id,
        ]);
        $this->assertDatabaseMissing('patient_password_reset_tokens', [
            'email' => (string) $this->patient->id,
        ]);
    }
}
