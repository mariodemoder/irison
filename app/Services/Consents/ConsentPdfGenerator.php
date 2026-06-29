<?php

namespace App\Services\Consents;

use App\Models\PatientConsent;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsentPdfGenerator
{
    public function generate(PatientConsent $consent): StreamedResponse
    {
        $patient = $consent->patient;
        $clinic = $consent->clinic;
        $template = $consent->template;

        $html = View::make('pdf.consent', [
            'clinicName' => $clinic->name ?? 'Irison',
            'contentHtml' => $consent->content_html,
            'signatureSvg' => $consent->signature_svg,
            'patientName' => ($patient->first_name ?? '') . ' ' . ($patient->last_name ?? ''),
            'signedAt' => $consent->signed_at?->format('d/m/Y H:i'),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'hash' => $consent->hash,
        ])->render();

        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->showBackground()
            ->setNodeModulePath(base_path('node_modules'));

        $pdfBinary = $browsershot->pdf();

        return response()->stream(function () use ($pdfBinary) {
            echo $pdfBinary;
        }, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="CONS-' . $consent->id . '.pdf"',
            'Content-Length' => strlen($pdfBinary),
        ]);
    }

    public function buildHash(PatientConsent $consent): string
    {
        $canonical = implode('|', [
            (string) $consent->id,
            (string) $consent->template_id,
            (string) $consent->template_version,
            $consent->content_html ?? '',
            $consent->signature_svg ?? '',
            (string) ($consent->signed_at?->timestamp ?? ''),
        ]);

        return hash('sha256', $canonical);
    }
}
