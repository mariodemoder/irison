<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\Bonus;
use App\Models\Patient;

class PatientBonusService
{
    public function index(Patient $patient): \Illuminate\Database\Eloquent\Collection
    {
        return Bonus::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->with('bonusType')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function show(Patient $patient, int $bonusId): Bonus
    {
        return Bonus::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->where('id', $bonusId)
            ->with(['bonusType', 'sessionLines.appointmentType', 'usages.appointment'])
            ->firstOrFail();
    }
}
