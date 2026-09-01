<?php

namespace Modules\PatientPortal\Infrastructure\Policies;

use App\Models\Patient;
use App\Models\PatientPortalNotification;

class PatientNotificationPolicy
{
    public function update(Patient $patient, PatientPortalNotification $notification): bool
    {
        return $notification->patient_id === $patient->id
            && $notification->clinic_id === $patient->clinic_id;
    }
}
