<?php

namespace Modules\PatientPortal\Domain\Events;

use App\Models\Patient;
use App\Models\Appointment;

class PatientAppointmentRequested
{
    public function __construct(
        public readonly Patient $patient,
        public readonly Appointment $appointment,
    ) {}
}
