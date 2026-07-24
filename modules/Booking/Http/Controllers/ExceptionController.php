<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\ScheduleException;

class ExceptionController extends Controller
{
    private function resolveProfessional(int $professionalId): BookingProfessional
    {
        return BookingProfessional::where('clinic_id', currentClinicId())
            ->findOrFail($professionalId);
    }

    public function index(int $professionalId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);

        $exceptions = $bp->exceptions()->orderBy('date')->get();

        return response()->json(['data' => $exceptions]);
    }

    public function store(Request $request, int $professionalId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ]);

        $validated['professional_id'] = $bp->id;

        $exception = ScheduleException::create($validated);

        return response()->json(['message' => 'Excepción creada.', 'data' => $exception], 201);
    }

    public function update(Request $request, int $professionalId, int $exceptionId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);
        $exception = $bp->exceptions()->findOrFail($exceptionId);

        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ]);

        $exception->update($validated);

        return response()->json(['message' => 'Excepción actualizada.', 'data' => $exception->fresh()]);
    }

    public function destroy(int $professionalId, int $exceptionId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);
        $bp->exceptions()->where('id', $exceptionId)->delete();

        return response()->json(['message' => 'Excepción eliminada.']);
    }
}
