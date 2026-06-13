<?php

namespace App\Http\Controllers\Api\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\BookingProfessional;
use App\Models\Booking\ProfessionalSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    private function resolveProfessional(int $professionalId): BookingProfessional
    {
        return BookingProfessional::where('clinic_id', currentClinicId())
            ->findOrFail($professionalId);
    }

    public function index(int $professionalId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);

        $schedules = $bp->schedules()->orderBy('day_of_week')->get();

        return response()->json(['data' => $schedules]);
    }

    public function store(Request $request, int $professionalId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);

        $validated = $request->validate([
            'day_of_week' => 'required|integer|between:1,7',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $validated['professional_id'] = $bp->id;

        $schedule = ProfessionalSchedule::create($validated);

        return response()->json(['message' => 'Horario creado.', 'data' => $schedule], 201);
    }

    public function update(Request $request, int $professionalId, int $scheduleId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);

        $schedule = $bp->schedules()->findOrFail($scheduleId);

        $validated = $request->validate([
            'day_of_week' => 'nullable|integer|between:1,7',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
        ]);

        $schedule->update($validated);

        return response()->json(['message' => 'Horario actualizado.', 'data' => $schedule->fresh()]);
    }

    public function destroy(int $professionalId, int $scheduleId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);
        $bp->schedules()->where('id', $scheduleId)->delete();

        return response()->json(['message' => 'Horario eliminado.']);
    }
}
