<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\PatientConsent;
use App\Models\Patient;
use App\Models\PatientAuditLog;
use Modules\PatientPortal\Domain\Events\PatientConsentSigned;

class PatientConsentService
{
    public function index(Patient $patient): \Illuminate\Database\Eloquent\Collection
    {
        return PatientConsent::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->with('template')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function show(Patient $patient, int $consentId): PatientConsent
    {
        return PatientConsent::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->where('id', $consentId)
            ->with('template')
            ->firstOrFail();
    }

    public function sign(Patient $patient, PatientConsent $consent, string $signatureSvg, ?string $ip = null, ?string $userAgent = null): PatientConsent
    {
        if ($consent->status !== 'sent') {
            throw new \Exception('Este consentimiento ya ha sido firmado o revocado.');
        }

        // Reuse existing consent signature service.
        // NOTE: `signed_by` references staff `users` (FK) and is only set by the
        // backoffice flow. Patient self-signature leaves it null; the patient
        // identity is captured by patient_id + audit log + ip/user_agent.
        $signatureService = app(\App\Services\Consents\ConsentSignatureService::class);
        $signatureService->sign($consent, $signatureSvg, [
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        // Audit log
        PatientAuditLog::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'event' => 'patient_consent_signed',
            'description' => 'Consentimiento firmado desde el portal',
            'properties' => ['consent_id' => $consent->id],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        event(new PatientConsentSigned($patient, $consent));

        return $consent->fresh(['template']);
    }
}
