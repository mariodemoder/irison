<?php

declare(strict_types=1);

namespace Modules\Booking\Services;

use App\Models\Appointment;
use App\Models\UserSchedule;
use App\Models\UserScheduleException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Booking\Contracts\AvailabilityCheckerInterface;
use Modules\Booking\Models\BookingPage;
use Modules\Booking\Models\BookingProfessional;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\ScheduleException;

class AvailabilityEngine implements AvailabilityCheckerInterface
{
    private array $hasBookingSchedulesCache = [];

    private function professionalHasAnyBookingSchedules(BookingProfessional $bp): bool
    {
        if (!array_key_exists($bp->id, $this->hasBookingSchedulesCache)) {
            $this->hasBookingSchedulesCache[$bp->id] = $bp->schedules()->exists();
        }
        return $this->hasBookingSchedulesCache[$bp->id];
    }

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
            $schedules = $this->resolveSchedules($bp, $dayOfWeek);

            foreach ($schedules as $schedule) {
                $blockedRanges = $this->getBlockedRanges($bp, $dateStr);
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
        $page = BookingPage::where('clinic_id', $clinicId)->first();
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
                if (! $this->professionalHasSchedule($bp, $dayOfWeek)) {
                    continue;
                }

                if ($this->isProfessionalBlocked($bp, $day->toDateString())) {
                    continue;
                }

                $hasAvailability = true;
                break;
            }

            $dates[] = ['date' => $day->toDateString(), 'has_availability' => $hasAvailability];
        }

        return $dates;
    }

    private function resolveSchedules(BookingProfessional $bp, int $dayOfWeekIso): Collection
    {
        if ($this->professionalHasAnyBookingSchedules($bp)) {
            return $bp->schedules()->where('day_of_week', $dayOfWeekIso)->get();
        }

        $userDow = $dayOfWeekIso === 7 ? 0 : $dayOfWeekIso;

        return $bp->user->schedules()
            ->where('enabled', true)
            ->where('day_of_week', $userDow)
            ->get();
    }

    private function professionalHasSchedule(BookingProfessional $bp, int $dayOfWeekIso): bool
    {
        if ($this->professionalHasAnyBookingSchedules($bp)) {
            return $bp->schedules()->where('day_of_week', $dayOfWeekIso)->exists();
        }

        $userDow = $dayOfWeekIso === 7 ? 0 : $dayOfWeekIso;

        return $bp->user->schedules()
            ->where('enabled', true)
            ->where('day_of_week', $userDow)
            ->exists();
    }

    private function isProfessionalBlocked(BookingProfessional $bp, string $date): bool
    {
        $blocked = $bp->exceptions()
            ->where('date', $date)
            ->whereNull('start_time')
            ->whereNull('end_time')
            ->exists();

        if ($blocked) {
            return true;
        }

        return $bp->user->scheduleExceptions()
            ->whereNull('start_time')
            ->whereNull('end_time')
            ->where(function ($q) use ($date) {
                $q->where('date', $date)
                  ->orWhere(function ($q2) use ($date) {
                      $q2->where('date', '<=', $date)
                         ->where('end_date', '>=', $date);
                  });
            })
            ->exists();
    }

    private function resolveProfessionals(int $clinicId, ?int $professionalId): Collection
    {
        $query = BookingProfessional::with(['user', 'user.schedules', 'user.scheduleExceptions'])
            ->where('clinic_id', $clinicId)
            ->where('allow_online_booking', true);

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        return $query->get();
    }

    private function getBlockedRanges(BookingProfessional $bp, string $date): array
    {
        $exceptions = ScheduleException::where('professional_id', $bp->id)
            ->where('date', $date)
            ->get();

        if ($exceptions->isEmpty()) {
            $exceptions = UserScheduleException::where('user_id', $bp->user_id)
                ->where(function ($q) use ($date) {
                    $q->where('date', $date)
                      ->orWhere(function ($q2) use ($date) {
                          $q2->where('date', '<=', $date)
                             ->where('end_date', '>=', $date);
                      });
                })
                ->get();
        }

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
