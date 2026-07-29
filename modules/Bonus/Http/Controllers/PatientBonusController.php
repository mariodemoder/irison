<?php

declare(strict_types=1);

namespace Modules\Bonus\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bonus;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Bonus\Services\BonusService;

class PatientBonusController extends Controller
{
    public function __construct(
        private readonly BonusService $bonusService,
    ) {}

    public function index(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewAny', Bonus::class);

        $clinicId = currentClinicId();
        $activeOnly = $request->filled('active')
            && in_array($request->input('active'), ['1', 'true', 'yes', 1, true], true);
        $mapped = $this->bonusService->forPatient($patient->id, $clinicId, $activeOnly);

        return response()->json(['data' => $mapped]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);
        Gate::authorize('create', Bonus::class);

        $validated = $request->validate([
            'bonus_type_id' => 'nullable|integer|exists:bonus_types,id',
            'name' => 'required|string|max:255',
            'total_sessions' => 'required|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $clinicId = currentClinicId();
        $bonus = $this->bonusService->createForPatient($patient->id, $validated, $clinicId);

        return response()->json(['data' => $bonus->load('sessionLines.appointmentType')], 201);
    }
}
