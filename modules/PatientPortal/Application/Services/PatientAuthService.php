<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\Patient;
use App\Models\PatientAuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Modules\PatientPortal\Domain\Events\PatientLoggedIn;
use Modules\PatientPortal\Infrastructure\Persistence\PatientPortalSettings;

class PatientAuthService
{
    public function login(
        string $email,
        string $password,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $clinicSlug = null
    ): array {
        $patient = Patient::query()->where('email', $email);
        if ($clinicSlug) {
            $patient->whereHas('clinic', fn ($q) => $q->where('slug', $clinicSlug));
        }
        $patient = $patient->first();

        if (!$patient || !Hash::check($password, $patient->password)) {
            throw new \Exception('Credenciales incorrectas.');
        }

        if ($patient->status !== 'active') {
            throw new \Exception('Su cuenta no está activa. Contacte con la clínica.');
        }

        if (empty($patient->clinic?->slug)) {
            throw new \Exception('El portal del paciente no está disponible para esta clínica.');
        }

        if (! PatientPortalSettings::forClinic($patient->clinic_id)->is_active) {
            throw new \Exception('El portal del paciente no está disponible para esta clínica.');
        }

        // Revoke existing tokens
        $patient->tokens()->delete();

        // Create new token
        $token = $patient->createToken('Patient Portal', ['patient'])->plainTextToken;

        // Update login timestamp
        $patient->update(['last_login_at' => now()]);

        // Audit log
        PatientAuditLog::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'event' => 'patient_logged_in',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        event(new PatientLoggedIn($patient, $ip, $userAgent));

        return [
            'token' => $token,
            'patient' => [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'email' => $patient->email,
                'clinic_id' => $patient->clinic_id,
                'clinic' => $this->clinicBranding($patient),
            ],
            'portal' => $this->portalSettings($patient),
        ];
    }

    private function clinicBranding(Patient $patient): array
    {
        $clinic = $patient->clinic;

        return [
            'id' => $clinic?->id,
            'name' => $clinic?->name,
            'slug' => $clinic?->slug,
            'logo_url' => $clinic && $clinic->usesClinicBranding() && $clinic->hasClinicLogo()
                ? $clinic->clinicLogoUrl()
                : null,
        ];
    }

    /**
     * Config del portal expuesta al SPA del paciente (no sensible): estado,
     * horizonte máximo de reserva y política de cancelación en horas.
     */
    private function portalSettings(Patient $patient): array
    {
        $settings = PatientPortalSettings::forClinic($patient->clinic_id);

        return [
            'is_active' => $settings->is_active,
            'max_horizon_days' => $settings->max_horizon_days,
            'cancellation_hours' => $settings->cancellation_hours,
        ];
    }

    public function logout(Patient $patient): void
    {
        $patient->currentAccessToken()->delete();
    }

    public function forgotPassword(string $email, ?string $clinicSlug = null): void
    {
        $patient = Patient::query()->where('email', $email);
        if ($clinicSlug) {
            $patient->whereHas('clinic', fn ($q) => $q->where('slug', $clinicSlug));
        }
        $patient = $patient->first();

        // Solo enviamos link si el paciente puede usar el portal (acceso opt-in
        // activo + clínica con slug). De lo contrario respuesta neutral.
        if ($patient && $patient->canUsePortal()) {
            Password::broker('patients')->sendResetLink([
                'email' => $email,
                'clinic' => fn ($q) => $q->whereHas('clinic', fn ($c) => $c->where('slug', $patient->clinic->slug)),
            ]);
        }
    }

    public function resetPassword(string $token, string $email, string $password, ?string $clinicSlug = null): void
    {
        $patient = Patient::query()->where('email', $email);
        if ($clinicSlug) {
            $patient->whereHas('clinic', fn ($q) => $q->where('slug', $clinicSlug));
        }
        $patient = $patient->first();

        $credentials = [
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'token' => $token,
        ];

        if ($patient?->clinic?->slug) {
            $credentials['clinic'] = fn ($q) => $q->whereHas('clinic', fn ($c) => $c->where('slug', $patient->clinic->slug));
        }

        $status = Password::broker('patients')->reset(
            $credentials,
            function ($patient, $password) {
                $patient->forceFill([
                    'password' => $password,
                    'email_verified_at' => now(),
                ])->save();

                // Revoke all existing tokens for security
                $patient->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new \Exception('Token inválido o expirado.');
        }
    }

    public function me(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'email' => $patient->email,
            'phone' => $patient->phone,
            'birth_date' => $patient->birth_date,
            'address' => $patient->address,
            'zip' => $patient->zip,
            'city' => $patient->city,
            'province' => $patient->province,
            'country' => $patient->country,
            'clinic_id' => $patient->clinic_id,
            'clinic' => $this->clinicBranding($patient),
            'portal' => $this->portalSettings($patient),
        ];
    }
}
