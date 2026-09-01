<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\PatientPortalNotification;
use App\Models\Patient;

class PatientNotificationService
{
    public function index(Patient $patient): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return PatientPortalNotification::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    public function markRead(Patient $patient, PatientPortalNotification $notification): void
    {
        $notification->update(['read_at' => now()]);
    }
}
