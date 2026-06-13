<?php

namespace App\Http\Controllers\Api\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\BookingProfessional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingProfessionalController extends Controller
{
    public function index(): JsonResponse
    {
        $professionals = BookingProfessional::with('user:id,name,email')
            ->where('clinic_id', currentClinicId())
            ->get();

        return response()->json(['data' => $professionals]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id|unique:booking_professionals,user_id',
            'allow_online_booking' => 'nullable|boolean',
        ]);

        $user = \App\Models\User::where('clinic_id', currentClinicId())->findOrFail($validated['user_id']);

        $validated['clinic_id'] = currentClinicId();

        $bp = BookingProfessional::create($validated);

        return response()->json(['message' => 'Profesional añadido.', 'data' => $bp->load('user:id,name,email')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $bp = BookingProfessional::where('clinic_id', currentClinicId())->findOrFail($id);

        $validated = $request->validate([
            'allow_online_booking' => 'nullable|boolean',
        ]);

        $bp->update($validated);

        return response()->json(['message' => 'Profesional actualizado.', 'data' => $bp->fresh()->load('user:id,name,email')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $bp = BookingProfessional::where('clinic_id', currentClinicId())->findOrFail($id);
        $bp->delete();

        return response()->json(['message' => 'Profesional eliminado.']);
    }
}
