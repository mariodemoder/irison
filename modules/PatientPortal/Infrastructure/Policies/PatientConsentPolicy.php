<?php

namespace Modules\PatientPortal\Infrastructure\Policies;

use App\Models\Patient;
use App\Models\PatientConsent;

class PatientConsentPolicy
{
    public function view(Patient $patient, PatientConsent $consent): bool
    {
        return $consent->patient_id === $patient->id
            && $consent->clinic_id === $patient->clinic_id;
    }

    public function sign(Patient $patient, PatientConsent $consent): bool
    {
        return $this->view($patient, $consent)
            && $consent->status === 'sent';
    }
}
