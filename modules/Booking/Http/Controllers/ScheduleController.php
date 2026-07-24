<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\ProfessionalSchedule;

class ScheduleController extends Controller
{
    private function resolveProfessional(int $professionalId): BookingProfessional
    {
        return BookingProfessional::where('clinic_id', currentClinicId())
            ->findOrFail($professionalId);
    }

    private function mapUserDowToIso(int $userDow): int
    {
        return $userDow === 0 ? 7 : $userDow;
    }

    private function mapIsoDowToUser(int $isoDow): int
    {
        return $isoDow === 7 ? 0 : $isoDow;
    }

    public function index(int $professionalId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);

        $schedules = $bp->schedules()->orderBy('day_of_week')->get();

        if ($schedules->isNotEmpty()) {
            return response()->json(['data' => $schedules, 'from_user' => false]);
        }

        $userSchedules = UserSchedule::where('user_id', $bp->user_id)
            ->where('enabled', true)
            ->orderBy('day_of_week')
            ->get()
            ->map(function (UserSchedule $us) {
                $ps = new ProfessionalSchedule();
                $ps->day_of_week = $this->mapUserDowToIso($us->day_of_week);
                $ps->start_time = $us->start_time;
                $ps->end_time = $us->end_time;
                $ps->from_user = true;
                return $ps;
            });

        return response()->json(['data' => $userSchedules, 'from_user' => true]);
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

    public function bulkUpdate(Request $request, int $professionalId): JsonResponse
    {
        $bp = $this->resolveProfessional($professionalId);

        $validated = $request->validate([
            'schedules' => 'required|array|size:7',
            'schedules.*.day_of_week' => 'required|integer|between:1,7',
            'schedules.*.start_time' => 'nullable|date_format:H:i,H:i:s',
            'schedules.*.end_time' => 'nullable|date_format:H:i,H:i:s',
        ]);

        $bp->schedules()->delete();

        foreach ($validated['schedules'] as $entry) {
            if ($entry['start_time'] && $entry['end_time']) {
                ProfessionalSchedule::create([
                    'professional_id' => $bp->id,
                    'day_of_week' => $entry['day_of_week'],
                    'start_time' => substr($entry['start_time'], 0, 5),
                    'end_time' => substr($entry['end_time'], 0, 5),
                ]);
            }
        }

        $schedules = $bp->schedules()->orderBy('day_of_week')->get();

        return response()->json(['message' => 'Horarios guardados.', 'data' => $schedules]);
    }
}
