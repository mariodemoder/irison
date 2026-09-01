<?php

namespace Modules\PatientPortal\Infrastructure\Policies;

use App\Models\Patient;
use App\Models\Bonus;

class PatientBonusPolicy
{
    public function view(Patient $patient, Bonus $bonus): bool
    {
        return $bonus->patient_id === $patient->id
            && $bonus->clinic_id === $patient->clinic_id;
    }
}
