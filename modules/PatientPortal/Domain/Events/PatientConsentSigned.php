<?php

namespace Modules\PatientPortal\Domain\Events;

use App\Models\Patient;
use App\Models\PatientConsent;

class PatientConsentSigned
{
    public function __construct(
        public readonly Patient $patient,
        public readonly PatientConsent $consent,
    ) {}
}
