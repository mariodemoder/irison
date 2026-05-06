<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AppointmentReminderService
{
    public function getAppointmentsFor24hReminder(?CarbonImmutable $now = null): Collection
    {
        return $this->getAppointmentsForWindow('reminder_24h_sent_at', 24, $now);
    }

    public function getAppointmentsFor2hReminder(?CarbonImmutable $now = null): Collection
    {
        return $this->getAppointmentsForWindow('reminder_2h_sent_at', 2, $now);
    }

    private function getAppointmentsForWindow(string $sentAtColumn, int $hoursAhead, ?CarbonImmutable $now = null): Collection
    {
        [$from, $to] = $this->buildWindow($hoursAhead, $now);

        return Appointment::query()
            ->with(['patient:id,first_name,last_name,email', 'clinic:id,name,timezone'])
            ->where('status', 'scheduled')
            ->whereNotNull('patient_id')
            ->whereBetween('start_time', [$from, $to])
            ->whereNull($sentAtColumn)
            ->whereHas('patient', function ($query): void {
                $query
                    ->whereNotNull('email')
                    ->where('email', '!=', '');
            })
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    private function buildWindow(int $hoursAhead, ?CarbonImmutable $now = null): array
    {
        $base = $now ?? CarbonImmutable::now();

        return [
            $base->addHours($hoursAhead),
            $base->addHours($hoursAhead + 1),
        ];
    }
}
