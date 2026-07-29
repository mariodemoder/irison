<?php

declare(strict_types=1);

namespace Modules\Bonus\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bonus;
use App\Services\Documents\InvoicingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Bonus\Services\BonusService;

class BonusController extends Controller
{
    public function __construct(
        private readonly BonusService $bonusService,
        private readonly InvoicingService $invoicingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Bonus::class);

        $clinicId = currentClinicId();

        return response()->json($this->bonusService->index($request->all(), $clinicId));
    }

    public function show(Bonus $bonus): JsonResponse
    {
        Gate::authorize('view', $bonus);

        return response()->json(['data' => $bonus->load('sessionLines.appointmentType')]);
    }

    public function update(Request $request, Bonus $bonus): JsonResponse
    {
        Gate::authorize('update', $bonus);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'total_sessions' => 'sometimes|integer|min:1',
            'remaining_sessions' => 'sometimes|integer|min:0',
            'price' => 'sometimes|numeric|min:0',
            'expires_at' => 'nullable|date',
        ]);

        $bonus = $this->bonusService->updateBonus($bonus, $validated);

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
