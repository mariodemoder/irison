<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Reminders\ReminderService;
use App\Services\Appointments\AppointmentReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminder24h implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    public function handle(
        AppointmentReminderService $appointmentReminderService,
        ReminderService $reminderService,
    ): void {
        $appointments = $appointmentReminderService->getAppointmentsFor24hReminder();

        foreach ($appointments as $appointment) {
            $reminderService->sendAppointmentReminder($appointment, '24h');
        }
    }
}
