<?php

namespace App\Http\Controllers\Api;

use App\Events\ConsentSigned;
use App\Http\Controllers\Controller;
use App\Models\PatientConsent;
use App\Services\Consents\ConsentPdfGenerator;
use App\Services\Consents\ConsentSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsentSignController extends Controller
{
    public function __construct(
        private readonly ConsentSignatureService $signatureService,
        private readonly ConsentPdfGenerator $pdfGenerator,
    ) {}

    public function show(string $token): JsonResponse
    {
        $consent = PatientConsent::query()
            ->where('token', $token)
            ->with(['template:id,title', 'patient:id,first_name,last_name', 'clinic:id,name'])
            ->first();

        if (! $consent) {
            return response()->json(['message' => 'Enlace no válido.'], 404);
        }

        if (! $this->signatureService->isTokenValid($consent)) {
            if ($consent->status === 'signed') {
                return response()->json(['message' => 'Este consentimiento ya ha sido firmado.', 'status' => 'already_signed'], 410);
            }

            return response()->json(['message' => 'Este enlace ha caducado.', 'status' => 'expired'], 410);
        }

        $consent->logs()->create([
            'event' => 'opened',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'data' => [
                'id' => $consent->id,
                'token' => $consent->token,
                'template_title' => $consent->template->title,
                'content_html' => $consent->content_html,
                'patient_name' => ($consent->patient->first_name ?? '') . ' ' . ($consent->patient->last_name ?? ''),
                'clinic_name' => $consent->clinic->name,
            ],
        ]);
    }

    public function sign(Request $request, string $token): JsonResponse
    {
        $consent = PatientConsent::query()
            ->where('token', $token)
            ->first();

        if (! $consent) {
            return response()->json(['message' => 'Enlace no válido.'], 404);
        }

        if (! $this->signatureService->isTokenValid($consent)) {
            return response()->json(['message' => 'Este enlace ha caducado o ya no está disponible.'], 410);
        }

        $data = $request->validate([
            'signature_svg' => 'required|string',
        ]);

        $this->signatureService->sign($consent, $data['signature_svg'], [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $consent->hash = $this->pdfGenerator->buildHash($consent);
        $consent->save();

        ConsentSigned::dispatch($consent);

        return response()->json([
            'message' => 'Gracias. Su consentimiento ha sido registrado.',
        ]);
    }
}
