<?php

namespace App\Http\Controllers\Api\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = BookingService::where('clinic_id', currentClinicId())
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $services]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'appointment_type_id' => 'nullable|integer|exists:appointment_types,id',
        ]);

        $validated['clinic_id'] = currentClinicId();

        $service = BookingService::create($validated);

        return response()->json(['message' => 'Servicio creado.', 'data' => $service], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $service = BookingService::where('clinic_id', currentClinicId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'appointment_type_id' => 'nullable|integer|exists:appointment_types,id',
        ]);

        $service->update($validated);

        return response()->json(['message' => 'Servicio actualizado.', 'data' => $service->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $service = BookingService::where('clinic_id', currentClinicId())->findOrFail($id);
        $service->delete();

        return response()->json(['message' => 'Servicio eliminado.']);
    }
}
