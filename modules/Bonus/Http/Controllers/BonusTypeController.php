<?php

declare(strict_types=1);

namespace Modules\Bonus\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppointmentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Bonus\Models\BonusType;

class BonusTypeController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', BonusType::class);

        $clinicId = currentClinicId();
        $types = BonusType::with('appointmentTypes')
            ->where('clinic_id', $clinicId)
            ->get();

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', BonusType::class);

        $clinicId = currentClinicId();

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'sessions' => 'required|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
            'appointment_types' => 'nullable|array',
            'appointment_types.*.appointment_type_id' => 'required|integer|exists:appointment_types,id',
            'appointment_types.*.quantity' => 'required|integer|min:1',
            'appointment_types.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $clinicId) {
            $type = BonusType::create([
                'clinic_id' => $clinicId,
                'description' => $validated['description'],
                'sessions' => $validated['sessions'],
                'price' => $validated['price'] ?? 0,
                'expires_at' => $validated['expires_at'] ?? null,
            ]);

            if (!empty($validated['appointment_types'])) {
                $syncData = [];
                foreach ($validated['appointment_types'] as $at) {
                    $syncData[$at['appointment_type_id']] = [
                        'quantity' => $at['quantity'],
                        'unit_price' => $at['unit_price'] ?? 0,
                    ];
                }
                $type->appointmentTypes()->sync($syncData);
            }

            return response()->json([
                'message' => 'Tipo de bono creado.',
                'data' => $type->load('appointmentTypes'),
            ], 201);
        });
    }

    public function update(Request $request, BonusType $bonusType): JsonResponse
    {
        Gate::authorize('update', $bonusType);

        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
            'sessions' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'expires_at' => 'nullable|date',
            'appointment_types' => 'nullable|array',
            'appointment_types.*.appointment_type_id' => 'required|integer|exists:appointment_types,id',
            'appointment_types.*.quantity' => 'required|integer|min:1',
            'appointment_types.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $bonusType) {
            $typeData = collect($validated)->only(['description', 'sessions', 'price', 'expires_at'])->toArray();
            if (!empty($typeData)) {
                $bonusType->update($typeData);
            }

            if (isset($validated['appointment_types'])) {
                $syncData = [];
                foreach ($validated['appointment_types'] as $at) {
                    $syncData[$at['appointment_type_id']] = [
                        'quantity' => $at['quantity'],
                        'unit_price' => $at['unit_price'] ?? 0,
                    ];
                }
                $bonusType->appointmentTypes()->sync($syncData);
            }

            return response()->json([
                'message' => 'Tipo de bono actualizado.',
                'data' => $bonusType->load('appointmentTypes'),
            ]);
        });
    }

    public function destroy(BonusType $bonusType): JsonResponse
    {
        Gate::authorize('delete', $bonusType);

        if ($bonusType->bonuses()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un tipo de bono que tiene bonos asignados.',
            ], 422);
        }

        $bonusType->delete();

        return response()->json([], 204);
    }
}
