<?php

namespace Modules\PatientPortal\Application\Services;

use App\Models\Document;
use App\Models\Patient;
use App\Models\PatientAuditLog;
use Illuminate\Support\Facades\Storage;

class PatientDocumentService
{
    public function index(Patient $patient): \Illuminate\Database\Eloquent\Collection
    {
        return Document::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->orderBy('date', 'desc')
            ->get();
    }

    public function show(Patient $patient, int $documentId): Document
    {
        return Document::where('clinic_id', $patient->clinic_id)
            ->where('patient_id', $patient->id)
            ->where('id', $documentId)
            ->firstOrFail();
    }

    public function download(Patient $patient, Document $document, ?string $ip = null, ?string $userAgent = null)
    {
        // Audit log
        PatientAuditLog::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'event' => 'patient_document_downloaded',
            'description' => 'Documento descargado desde el portal',
            'properties' => ['document_id' => $document->id],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        // Return file from private storage
        $path = "documents/{$document->clinic_id}/{$document->id}.pdf";

        return Storage::disk('private')->download($path, $document->counter . '.pdf');
    }
}
