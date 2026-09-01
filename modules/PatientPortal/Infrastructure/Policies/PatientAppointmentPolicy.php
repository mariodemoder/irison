<?php

namespace Modules\PatientPortal\Infrastructure\Policies;

use App\Models\Patient;
use App\Models\Appointment;
use Modules\PatientPortal\Infrastructure\Persistence\PatientPortalSettings;

class PatientAppointmentPolicy
{
    public function view(Patient $patient, Appointment $appointment): bool
    {
        return $appointment->patient_id === $patient->id
            && $appointment->clinic_id === $patient->clinic_id;
    }

    public function cancel(Patient $patient, Appointment $appointment): bool
    {
        if (!$this->view($patient, $appointment)) {
            return false;
        }

        if (!in_array($appointment->status, ['scheduled', 'confirmed'])) {
            return false;
        }

        $cancellationHours = PatientPortalSettings::forClinic($patient->clinic_id)->cancellation_hours;

        return $appointment->start_time->diffInHours(now()) >= $cancellationHours;
    }

    public function update(Patient $patient, Appointment $appointment): bool
    {
        return $this->view($patient, $appointment)
            && in_array($appointment->status, ['scheduled', 'confirmed']);
    }
}
