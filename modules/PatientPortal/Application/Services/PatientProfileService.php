<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\Patient;
use App\Models\PatientAuditLog;
use Modules\PatientPortal\Application\DTOs\ProfileUpdateDTO;

class PatientProfileService
{
    public function get(Patient $patient): Patient
    {
        return $patient;
    }

    public function update(Patient $patient, ProfileUpdateDTO $dto, ?string $ip = null, ?string $userAgent = null): Patient
    {
        $changes = $dto->toArray();
        $patient->update($changes);

        // Audit log
        PatientAuditLog::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'event' => 'patient_profile_updated',
            'description' => 'Perfil actualizado desde el portal',
            'properties' => ['changed_fields' => array_keys($changes)],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $patient->fresh();
    }
}
