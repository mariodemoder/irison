<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentType;
use App\Models\Bonus;
use App\Models\BonusType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyServicesController extends Controller
{
    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if ($user->role !== 'owner' && !in_array($user->profile?->slug, ['admin', 'manager'])) {
            abort(403, 'No tienes permisos para gestionar servicios');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess();
        $user = $request->user();
        $clinic = $user->clinic;

        return response()->json([
            'cesiones' => $clinic
                ? $clinic->appointmentTypes()->orderBy('id')->get(['id', 'description', 'estimated_hours', 'estimated_minutes', 'price', 'color'])->toArray()
                : [],
            'bonus_types' => $clinic ? $this->readBonusTypes($clinic) : [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $user = $request->user();
        $clinic = $user->clinic;

        if (!$clinic) {
            abort(404, 'Clínica no encontrada');
        }

        $data = Validator::make($request->all(), [
            'cesiones' => ['nullable', 'array'],
            'cesiones.*.id' => ['nullable', 'string'],
            'cesiones.*.description' => ['nullable', 'string', 'max:255'],
            'cesiones.*.estimated_hours' => ['required', 'integer', 'min:0'],
            'cesiones.*.estimated_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'cesiones.*.price' => ['required', 'numeric', 'min:0'],
            'cesiones.*.color' => ['nullable', 'string', 'max:7'],

            'bonus_types' => ['nullable', 'array'],
            'bonus_types.*.id' => ['nullable', 'string'],
            'bonus_types.*.description' => ['nullable', 'string', 'max:255'],
            'bonus_types.*.sessions' => ['required', 'integer', 'min:1'],
            'bonus_types.*.price' => ['required', 'numeric', 'min:0'],
            'bonus_types.*.expires_at' => ['nullable', 'date'],
            'bonus_types.*.lines' => ['nullable', 'array'],
            'bonus_types.*.lines.*.appointment_type_id' => ['nullable', 'integer'],
            'bonus_types.*.lines.*.appointment_type_index' => ['nullable', 'integer', 'min:0'],
            'bonus_types.*.lines.*.quantity' => ['required', 'integer', 'min:1'],
            'bonus_types.*.lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ])->validate();

        DB::transaction(function () use ($clinic, $data) {
            $appointmentTypeIdByIndex = [];

            // Guardar cesiones (appointment_types)
            if (!empty($data['cesiones']) && is_array($data['cesiones'])) {
                $existingIds = $clinic->appointmentTypes()->pluck('id')->all();
                $keptIds = [];

                foreach (array_values($data['cesiones']) as $index => $item) {
                    $payload = [
                        'clinic_id' => $clinic->id,
                        'description' => $item['description'] ?? '',
                        'estimated_hours' => max((int)($item['estimated_hours'] ?? 0), 0),
                        'estimated_minutes' => max((int)($item['estimated_minutes'] ?? 60), 0),
                        'price' => max((float)($item['price'] ?? 0), 0),
                        'color' => $item['color'] ?? null,
                    ];

                    $incomingId = isset($item['id']) && is_numeric($item['id'])
                        ? (int) $item['id']
                        : null;

                    $model = $incomingId
                        ? $clinic->appointmentTypes()->whereKey($incomingId)->first()
                        : null;

                    if ($model) {
                        $model->update($payload);
                    } else {
                        $model = $clinic->appointmentTypes()->create($payload);
                    }

                    $keptIds[] = $model->id;
                    $appointmentTypeIdByIndex[$index] = $model->id;
                }

                $toDelete = array_values(array_diff($existingIds, $keptIds));
                if (!empty($toDelete)) {
                    AppointmentType::query()
                        ->where('clinic_id', $clinic->id)
                        ->whereIn('id', $toDelete)
                        ->delete();
                }
            }

            // Guardar tipos de bono (bonus_types)
            if ($this->hasBonusTypesTable() && !empty($data['bonus_types']) && is_array($data['bonus_types'])) {
                $existingBonusTypes = BonusType::withTrashed()
                    ->where('clinic_id', $clinic->id)
                    ->orderBy('id')
                    ->get();

                $keptBonusIds = [];

                foreach (array_values($data['bonus_types']) as $index => $item) {
                    $incomingId = isset($item['id']) && is_numeric($item['id'])
                        ? (int) $item['id']
                        : null;

                    $bonusType = $incomingId
                        ? $existingBonusTypes->firstWhere('id', $incomingId)
                        : null;

                    $payload = [
                        'description' => $item['description'] ?? '',
                        'sessions' => max((int)($item['sessions'] ?? 1), 1),
                        'price' => max((float)($item['price'] ?? 0), 0),
                        'expires_at' => !empty($item['expires_at']) ? $item['expires_at'] : null,
                    ];

                    if ($bonusType) {
                        $bonusType->update($payload);
                    } else {
                        $bonusType = BonusType::create(array_merge($payload, ['clinic_id' => $clinic->id]));
                    }

                    // Sync pivot lines
                    $syncData = [];
                    if (!empty($item['lines']) && is_array($item['lines'])) {
                        foreach ($item['lines'] as $line) {
                            $atId = null;

                            if (!empty($line['appointment_type_id'])) {
                                $atId = (int) $line['appointment_type_id'];
                            } elseif (isset($line['appointment_type_index']) && $line['appointment_type_index'] !== null && $line['appointment_type_index'] !== '') {
                                $idx = (int) $line['appointment_type_index'];
                                $atId = $appointmentTypeIdByIndex[$idx] ?? null;
                            }

                            if ($atId) {
                                $syncData[$atId] = [
                                    'quantity' => max((int)($line['quantity'] ?? 1), 1),
                                    'unit_price' => max((float)($line['unit_price'] ?? 0), 0),
                                ];
                            }
                        }
                    }

                    $bonusType->appointmentTypes()->sync($syncData);
                    $keptBonusIds[] = $bonusType->id;
                }

                // Delete bonus types not in the incoming array
                foreach ($existingBonusTypes as $existing) {
                    if (!in_array($existing->id, $keptBonusIds)) {
                        $inUse = Bonus::query()
                            ->where('bonus_type_id', $existing->id)
                            ->where('clinic_id', $clinic->id)
                            ->exists();

                        if ($existing->trashed()) {
                            $existing->forceDelete();
                        } elseif ($inUse) {
                            $existing->delete();
                        } else {
                            $existing->forceDelete();
                        }
                    }
                }
            }
        }, 3);

        return response()->json([
            'cesiones' => $clinic->fresh()->appointmentTypes()->orderBy('id')->get(['id', 'description', 'estimated_hours', 'estimated_minutes', 'price', 'color'])->toArray(),
            'bonus_types' => $this->readBonusTypes($clinic->fresh()),
            'message' => 'Servicios actualizados',
        ]);
    }

    private function hasBonusTypesTable(): bool
    {
        return Schema::hasTable('bonus_types');
    }

    private function readBonusTypes($clinic): array
    {
        if (!$this->hasBonusTypesTable()) {
            return [];
        }

        return $clinic->bonusTypes()
            ->with(['appointmentTypes' => fn($q) => $q->select('appointment_types.id')])
            ->orderBy('id')
            ->get(['id', 'description', 'sessions', 'price', 'expires_at'])
            ->map(static function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'sessions' => (int) $item->sessions,
                    'price' => (float) $item->price,
                    'expires_at' => $item->expires_at?->toDateString(),
                    'lines' => $item->appointmentTypes->map(fn($at) => [
                        'appointment_type_id' => (int) $at->id,
                        'quantity' => max((int)($at->pivot->quantity ?? 1), 1),
                        'unit_price' => max((float)($at->pivot->unit_price ?? 0), 0),
                    ])->values()->toArray(),
                ];
            })->values()->toArray();
    }
}
