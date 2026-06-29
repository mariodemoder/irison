<?php

namespace App\Http\Controllers\Api;

use App\Events\ConsentCreated;
use App\Events\ConsentRevoked;
use App\Events\ConsentSent;
use App\Events\ConsentSigned;
use App\Http\Controllers\Controller;
use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\User;
use App\Services\Consents\ConsentPdfGenerator;
use App\Services\Consents\ConsentSignatureService;
use App\Services\Consents\ConsentVariableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PatientConsentController extends Controller
{
    public function __construct(
        private readonly ConsentVariableResolver $variableResolver,
        private readonly ConsentPdfGenerator $pdfGenerator,
        private readonly ConsentSignatureService $signatureService,
    ) {}

    public function index(Patient $patient): JsonResponse
    {
        Gate::authorize('viewAny', PatientConsent::class);

        $consents = PatientConsent::query()
            ->where('clinic_id', Auth::user()->clinic_id)
            ->where('patient_id', $patient->id)
            ->with('template:id,title')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $consents]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize('create', PatientConsent::class);

        $data = $request->validate([
            'template_id' => 'required|integer|exists:consent_templates,id',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
        ]);

        $clinicId = (int) Auth::user()->clinic_id;

        $template = ConsentTemplate::query()
            ->where('clinic_id', $clinicId)
            ->where('id', $data['template_id'])
            ->firstOrFail();

        $resolved = $this->variableResolver->resolve(
            $template->content,
            $patient,
            $template->clinic,
            Auth::user(),
        );

        $consent = PatientConsent::create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient->id,
            'template_id' => $template->id,
            'template_version' => $template->version,
            'appointment_id' => $data['appointment_id'] ?? null,
            'status' => 'pending',
            'snapshot' => $resolved['snapshot'],
            'content_html' => $resolved['html'],
            'created_by' => Auth::id(),
        ]);

        $consent->hash = $this->pdfGenerator->buildHash($consent);
        $consent->save();

        ConsentCreated::dispatch($consent);

        return response()->json([
            'data' => $consent->load('template:id,title'),
        ], 201);
    }

    public function show(PatientConsent $consent): JsonResponse
    {
        Gate::authorize('view', $consent);

        return response()->json([
            'data' => $consent->load(['template:id,title', 'patient:id,first_name,last_name', 'logs']),
        ]);
    }

    public function send(PatientConsent $consent): JsonResponse
    {
        Gate::authorize('update', $consent);

        if ($consent->status !== 'pending') {
            return response()->json(['message' => 'Solo se pueden enviar consentimientos pendientes.'], 422);
        }

        $this->signatureService->generateToken($consent);

        $consent->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
        ])->save();

        ConsentSent::dispatch($consent);

        return response()->json(['data' => $consent->fresh()]);
    }

    public function signPresential(Request $request, PatientConsent $consent): JsonResponse
    {
        Gate::authorize('signPresential', $consent);

        if (! in_array($consent->status, ['pending', 'sent'], true)) {
            return response()->json(['message' => 'El consentimiento no está pendiente de firma.'], 422);
        }

        $data = $request->validate([
            'signature_svg' => 'required|string',
        ]);

        $this->signatureService->sign($consent, $data['signature_svg'], [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'signed_by' => Auth::id(),
        ]);

        $consent->hash = $this->pdfGenerator->buildHash($consent);
        $consent->save();

        ConsentSigned::dispatch($consent);

        return response()->json(['data' => $consent->fresh()]);
    }

    public function download(PatientConsent $consent): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        Gate::authorize('view', $consent);

        if ($consent->status !== 'signed') {
            return response()->json(['message' => 'El consentimiento aún no ha sido firmado.'], 422);
        }

        $currentHash = $this->pdfGenerator->buildHash($consent);
        if ($currentHash !== $consent->hash) {
            return response()->json(['message' => 'El contenido del consentimiento ha sido alterado.'], 409);
        }

        return $this->pdfGenerator->generate($consent);
    }

    public function revoke(Request $request, PatientConsent $consent): JsonResponse
    {
        Gate::authorize('update', $consent);

        if ($consent->status !== 'signed') {
            return response()->json(['message' => 'Solo se pueden revocar consentimientos firmados.'], 422);
        }

        $consent->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
        ])->save();

        ConsentRevoked::dispatch($consent);

        return response()->json(['data' => $consent->fresh()]);
    }

    public function resend(PatientConsent $consent): JsonResponse
    {
        Gate::authorize('update', $consent);

        if (! in_array($consent->status, ['pending', 'sent'], true)) {
            return response()->json(['message' => 'El consentimiento no está pendiente de firma.'], 422);
        }

        $this->signatureService->generateToken($consent);

        $consent->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
        ])->save();

        ConsentSent::dispatch($consent);

        return response()->json(['data' => $consent->fresh()]);
    }
}
