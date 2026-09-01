<?php

namespace Modules\PatientPortal\Infrastructure\Policies;

use App\Models\Patient;
use App\Models\Payment;

class PatientPaymentPolicy
{
    public function view(Patient $patient, Payment $payment): bool
    {
        return $payment->patient_id === $patient->id
            && $payment->clinic_id === $patient->clinic_id;
    }
}
