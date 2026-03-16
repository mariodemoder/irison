<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Bonus;
use App\Services\Bonus\BonusService;
use App\Services\Documents\InvoicingService;
use Illuminate\Http\JsonResponse;

class BonusController extends Controller
{
    public function __construct(
        private readonly BonusService $bonusService,
        private readonly InvoicingService $invoicingService,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $clinicId = app()->has('activeClinic') ? app()->get('activeClinic')->id : null;

        return response()->json($this->bonusService->index($request->all(), $clinicId));
    }

    public function forPatient(Request $request, Patient $patient): JsonResponse
    {
        $clinicId = app()->has('activeClinic') ? app()->get('activeClinic')->id : null;
        $activeOnly = $request->filled('active')
            && in_array($request->input('active'), ['1', 'true', 'yes', 1, true], true);
        $mapped = $this->bonusService->forPatient($patient->id, $clinicId, $activeOnly);

        return response()->json(['data' => $mapped]);
    }

    public function storeForPatient(Request $request, Patient $patient): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'total_sessions' => 'required|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
        ]);

        $clinicId = app()->has('activeClinic') ? app()->get('activeClinic')->id : null;
        $bonus = $this->bonusService->createForPatient($patient->id, $data, $clinicId);

        return response()->json(['data' => $bonus], 201);
    }

    public function show(Bonus $bonus): JsonResponse
    {
        return response()->json(['data' => $bonus]);
    }

    public function update(Request $request, Bonus $bonus): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'total_sessions' => 'sometimes|integer|min:1',
            'remaining_sessions' => 'sometimes|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
        ]);

        $bonus = $this->bonusService->updateBonus($bonus, $data);

        return response()->json(['data' => $bonus]);
    }

    public function destroy(Bonus $bonus): JsonResponse
    {
        try {
            $this->bonusService->deleteBonus($bonus);
            return response()->json([], 204);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Devuelve un listado compacto de bonos con 1 sesión restante.
     * Formato: array de objetos { id: patient_id, patient_name, sessions_left, bonus_id }
     */
    public function expiring(): JsonResponse
    {
        $clinicId = app()->has('activeClinic') ? app()->get('activeClinic')->id : null;
        $mapped = $this->bonusService->expiring($clinicId);

        return response()->json($mapped);
    }

    public function issueInvoice(Request $request, Bonus $bonus): JsonResponse
    {
        $user = $request->user();

        if (!$user || (int) $user->clinic_id !== (int) $bonus->clinic_id) {
            return response()->json([
                'message' => 'No autorizado para emitir factura en este bono.',
            ], 403);
        }

        $result = $this->invoicingService->issueForBonus($bonus, $user);
        $document = $result['document'];
        $created = (bool) $result['created'];

        return response()->json([
            'message' => $created ? 'Factura del bono emitida correctamente.' : 'El bono ya tenía una factura emitida.',
            'data' => [
                'id' => $document->id,
                'counter' => $document->counter,
            ],
        ], $created ? 201 : 200);
    }
}
