<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientHistoryPdf\DownloadPatientHistoryPdfRequest;
use App\Models\Patient;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\Browsershot\Browsershot;

class PatientHistoryPdfController extends Controller
{
    public function pdf(DownloadPatientHistoryPdfRequest $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);

        $patient->load([
            'clinic:id,name,nif,address,zip,province,country',
            'appointments' => function ($query) {
                $query->select(['id', 'patient_id', 'start_time', 'status', 'notes'])
                    ->with(['clinicalRecord:id,appointment_id,notes'])
                    ->orderBy('start_time');
            },
        ]);

        $html = view('pdf.patient-clinical-history', [
            'patient' => $patient,
            'professional' => $request->user(),
            'issuedAt' => now(),
        ])->render();

        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->setNodeModulePath(base_path('node_modules'));

        $pdfBinary = $browsershot->pdf();
        $filename = sprintf('historia-clinica-%d.pdf', (int) $patient->id);
        $asDownload = (string) ($request->validated()['download'] ?? '0') === '1';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($asDownload ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
        ]);
    }
}
