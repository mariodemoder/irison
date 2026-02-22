<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Bonus;
use App\Services\BonusService;
use Illuminate\Http\JsonResponse;

class BonusController extends Controller
{
    public function forPatient(Request $request, Patient $patient): JsonResponse
    {
        $query = Bonus::where('patient_id', $patient->id);

        // If an active clinic is set in the app, limit to that clinic's bonuses
        $clinicId = app()->has('activeClinic') ? app()->get('activeClinic')->id : null;
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        // If ?active=1 is passed, filter to active bonuses (remaining > 0 and not expired)
        if ($request->filled('active') && in_array($request->input('active'), ['1','true','yes',1,true], true)) {
            $query->where('remaining_sessions', '>', 0)
                  ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); });
        }

        $list = $query->orderBy('created_at', 'desc')->get();

        $mapped = $list->map(function($b) {
            return [
                'id' => $b->id,
                'name' => $b->name,
                'total_sessions' => (int) $b->total_sessions,
                'remaining_sessions' => (int) $b->remaining_sessions,
                'price' => $b->price ?? 0,
                'expires_at' => $b->expires_at ? $b->expires_at->toDateString() : null,
                'status' => $b->status,
            ];
        });

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

        $service = new BonusService();
        $clinicId = app()->has('activeClinic') ? app()->get('activeClinic')->id : null;
        $bonus = $service->assignBonusToPatient(
            $clinicId,
            $patient->id,
            $data['name'],
            (int) $data['total_sessions'],
            $data['price'] ?? 0,
            isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null
        );

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

        $bonus->fill($data);
        $bonus->save();

        return response()->json(['data' => $bonus]);
    }

    public function destroy(Bonus $bonus): JsonResponse
    {
        $bonus->delete();
        return response()->json([], 204);
    }

    /**
     * Devuelve un listado compacto de bonos con 1 sesión restante.
     * Formato: array de objetos { id: patient_id, patient_name, sessions_left, bonus_id }
     */
    public function expiring(): JsonResponse
    {
        $query = Bonus::with('patient')
            ->where('remaining_sessions', 1)
            ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); });

        $clinicId = app()->has('activeClinic') ? app()->get('activeClinic')->id : null;
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        $list = $query->orderBy('remaining_sessions', 'asc')->orderBy('updated_at', 'desc')->get();

        $mapped = $list->map(function($b) {
            return [
                'id' => $b->patient ? $b->patient->id : null,
                'patient_name' => $b->patient ? $b->patient->name : '—',
                'sessions_left' => (int) $b->remaining_sessions,
                'bonus_id' => $b->id,
                'bonus_name' => $b->name ?? null,
                'expires_at' => $b->expires_at ? $b->expires_at->toDateString() : null,
            ];
        })->filter(function($item){ return $item['id'] !== null; })->values();

        return response()->json($mapped);
    }
}
