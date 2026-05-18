<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bonuses\StoreBonusRequest;
use App\Http\Requests\Bonuses\UpdateBonusRequest;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Bonus;
use App\Services\Bonus\BonusService;
use App\Services\Documents\InvoicingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('viewAny', Bonus::class);

        $clinicId = currentClinicId();

        return response()->json($this->bonusService->index($request->all(), $clinicId));
    }

    public function forPatient(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewAny', Bonus::class);

        $clinicId = currentClinicId();
        $activeOnly = $request->filled('active')
            && in_array($request->input('active'), ['1', 'true', 'yes', 1, true], true);
        $mapped = $this->bonusService->forPatient($patient->id, $clinicId, $activeOnly);

        return response()->json(['data' => $mapped]);
    }

    public function storeForPatient(StoreBonusRequest $request, Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);
        Gate::authorize('create', Bonus::class);

        $clinicId = currentClinicId();
        $bonus = $this->bonusService->createForPatient($patient->id, $request->validated(), $clinicId);

        return response()->json(['data' => $bonus], 201);
    }

    public function show(Bonus $bonus): JsonResponse
    {
        Gate::authorize('view', $bonus);

        return response()->json(['data' => $bonus]);
    }

    public function update(UpdateBonusRequest $request, Bonus $bonus): JsonResponse
    {
        Gate::authorize('update', $bonus);

        $bonus = $this->bonusService->updateBonus($bonus, $request->validated());

        return response()->json(['data' => $bonus]);
    }

    public function destroy(Bonus $bonus): JsonResponse
    {
        Gate::authorize('delete', $bonus);

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
        Gate::authorize('viewAny', Bonus::class);

        $clinicId = currentClinicId();
        $mapped = $this->bonusService->expiring($clinicId);

        return response()->json($mapped);
    }

    public function issueInvoice(Request $request, Bonus $bonus): JsonResponse
    {
        Gate::authorize('issueInvoice', $bonus);

        $user = $request->user();

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
