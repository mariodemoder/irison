<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\Payment;
use App\Models\Patient;

class PatientPaymentService
{
    public function index(Patient $patient): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Payment::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->with('appointment')
            ->orderBy('paid_at', 'desc')
            ->paginate(15);
    }

    public function pending(Patient $patient): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->where('status', 'pending')
            ->with('appointment')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
