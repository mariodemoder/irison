<?php

namespace Modules\PatientPortal\Infrastructure\Policies;

use App\Models\Patient;
use App\Models\Document;

class PatientDocumentPolicy
{
    public function view(Patient $patient, Document $document): bool
    {
        return $document->patient_id === $patient->id
            && $document->clinic_id === $patient->clinic_id;
    }
}
