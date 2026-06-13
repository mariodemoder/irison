<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Booking\BookingProfessional;
use App\Models\Booking\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityEngine
{
    public function getAvailableSlots(int $clinicId, int $serviceId, ?int $professionalId, string $date): array
    {
        $service = BookingService::where('clinic_id', $clinicId)
            ->where('id', $serviceId)
            ->where('is_active', true)
            ->firstOrFail();

        $duration = $service->duration_minutes;
        $dateCarbon = Carbon::parse($date);
        $dayOfWeek = $dateCarbon->dayOfWeekIso;
        $dateStr = $dateCarbon->toDateString();

        $professionals = $this->resolveProfessionals($clinicId, $professionalId);

        $slots = collect();

        foreach ($professionals as $bp) {
            $schedules = $bp->schedules()->where('day_of_week', $dayOfWeek)->get();

            foreach ($schedules as $schedule) {
                $blockedRanges = $this->getBlockedRanges($bp->id, $dateStr);
                $appointments = $this->getExistingAppointments($clinicId, $bp->user_id, $dateStr);
                $daySlots = $this->generateSlots($schedule->start_time, $schedule->end_time, $duration, $blockedRanges, $appointments);

                foreach ($daySlots as $slot) {
                    $slots->push([
                        'start' => $slot['start'],
                        'end' => $slot['end'],
                        'professional_id' => $bp->user_id,
                        'professional_name' => $bp->user->name,
                    ]);
                }
            }
        }

        return $slots->sortBy('start')->values()->toArray();
    }

    public function getAvailableDates(int $clinicId, int $serviceId, ?int $professionalId, string $yearMonth): array
    {
        $professionals = $this->resolveProfessionals($clinicId, $professionalId);

        $monthStart = Carbon::parse($yearMonth . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $today = Carbon::today();

        $maxHorizon = null;
        $page = \App\Models\Booking\BookingPage::where('clinic_id', $clinicId)->first();
        if ($page) {
            $maxHorizon = $today->copy()->addDays($page->max_horizon_days);
        }

        $dates = [];

        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            if ($day->lt($today)) {
                $dates[] = ['date' => $day->toDateString(), 'has_availability' => false];
                continue;
            }

            if ($maxHorizon && $day->gt($maxHorizon)) {
                $dates[] = ['date' => $day->toDateString(), 'has_availability' => false];
                continue;
            }

            $dayOfWeek = $day->dayOfWeekIso;
            $hasAvailability = false;

            foreach ($professionals as $bp) {
                $hasSchedule = $bp->schedules()->where('day_of_week', $dayOfWeek)->exists();
                if (! $hasSchedule) {
                    continue;
                }

                $isBlocked = $bp->exceptions()
                    ->where('date', $day->toDateString())
                    ->whereNull('start_time')
                    ->whereNull('end_time')
                    ->exists();

                if ($isBlocked) {
                    continue;
                }

                $hasAvailability = true;
                break;
            }

            $dates[] = ['date' => $day->toDateString(), 'has_availability' => $hasAvailability];
        }

        return $dates;
    }

    private function resolveProfessionals(int $clinicId, ?int $professionalId): Collection
    {
        $query = BookingProfessional::with('user')
            ->where('clinic_id', $clinicId)
            ->where('allow_online_booking', true);

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        return $query->get();
    }

    private function getBlockedRanges(int $bookingProfessionalId, string $date): array
    {
        $exceptions = \App\Models\Booking\ScheduleException::where('professional_id', $bookingProfessionalId)
            ->where('date', $date)
            ->get();

        $ranges = [];

        foreach ($exceptions as $exc) {
            if ($exc->start_time && $exc->end_time) {
                $ranges[] = ['start' => substr($exc->start_time, 0, 5), 'end' => substr($exc->end_time, 0, 5)];
            } elseif (! $exc->start_time && ! $exc->end_time) {
                $ranges[] = ['start' => '00:00', 'end' => '23:59'];
            }
        }

        return $ranges;
    }

    private function getExistingAppointments(int $clinicId, int $userId, string $date): Collection
    {
        return Appointment::where('clinic_id', $clinicId)
            ->where('professional_id', $userId)
            ->whereDate('start_time', $date)
            ->whereNotIn('status', ['canceled', 'cancelled'])
            ->get(['start_time', 'end_time']);
    }

    private function generateSlots(string $scheduleStart, string $scheduleEnd, int $durationMinutes, array $blockedRanges, Collection $appointments): array
    {
        $slots = [];
        $start = Carbon::parse($scheduleStart);
        $end = Carbon::parse($scheduleEnd);

        while ($start->copy()->addMinutes($durationMinutes)->lte($end)) {
            $slotEnd = $start->copy()->addMinutes($durationMinutes);
            $slotStartStr = $start->format('H:i');
            $slotEndStr = $slotEnd->format('H:i');

            if (! $this->isSlotAvailable($slotStartStr, $slotEndStr, $blockedRanges)) {
                $start->addMinutes(15);
                continue;
            }

            if (! $this->isSlotFree($slotStartStr, $slotEndStr, $appointments)) {
                $start->addMinutes(15);
                continue;
            }

            $slots[] = ['start' => $slotStartStr, 'end' => $slotEndStr];
            $start->addMinutes(15);
        }

        return $slots;
    }

    private function isSlotAvailable(string $start, string $end, array $blockedRanges): bool
    {
        foreach ($blockedRanges as $blocked) {
            if ($start < $blocked['end'] && $end > $blocked['start']) {
                return false;
            }
        }

        return true;
    }

    private function isSlotFree(string $start, string $end, Collection $appointments): bool
    {
        foreach ($appointments as $apt) {
            $aptStart = $apt->start_time instanceof Carbon
                ? $apt->start_time->format('H:i')
                : substr($apt->start_time, 11, 5);

            $aptEnd = $apt->end_time instanceof Carbon
                ? $apt->end_time->format('H:i')
                : substr($apt->end_time, 11, 5);

            if ($start < $aptEnd && $end > $aptStart) {
                return false;
            }
        }

        return true;
    }
}
