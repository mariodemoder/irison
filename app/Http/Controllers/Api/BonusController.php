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
        return response()->json(['data' => $list]);
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
        $bonus = $service->assignBonusToPatient($clinicId, $patient->id, $data['name'], $data['total_sessions'], $data['price'] ?? 0, isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null);

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
}
