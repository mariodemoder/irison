<?php

namespace Modules\PatientPortal\Domain\Events;

use App\Models\Patient;

class PatientLoggedIn
{
    public function __construct(
        public readonly Patient $patient,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {}
}
