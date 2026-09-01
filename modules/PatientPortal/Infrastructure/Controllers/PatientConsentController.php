<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientConsentService;

class PatientConsentController extends Controller
{
    public function __construct(
        private PatientConsentService $consentService
    ) {}

    public function index(Request $request)
    {
        $consents = $this->consentService->index($request->user());

        return response()->json(['consents' => $consents]);
    }

    public function show(Request $request, int $id)
    {
        $consent = $this->consentService->show($request->user(), $id);

        return response()->json(['consent' => $consent]);
    }

    public function sign(Request $request, int $id)
    {
        $request->validate([
            'signature_svg' => 'required|string',
        ]);

        $consent = \App\Models\PatientConsent::where('clinic_id', $request->user()->clinic_id)
            ->where('patient_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        try {
            $result = $this->consentService->sign(
                $request->user(),
                $consent,
                $request->input('signature_svg'),
                $request->ip(),
                $request->userAgent()
            );

            return response()->json(['consent' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
