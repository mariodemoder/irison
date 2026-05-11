<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Reminders\ReminderService;
use App\Services\Appointments\AppointmentReminderService;

class SendAppointmentReminder2h
{
    public function __construct(
        private readonly AppointmentReminderService $appointmentReminderService,
        private readonly ReminderService $reminderService,
    ) {
    }

    public function handle(): void
    {
        $appointments = $this->appointmentReminderService->getAppointmentsFor2hReminder();

        foreach ($appointments as $appointment) {
            $this->reminderService->sendAppointmentReminder($appointment, '2h');
        }
    }
}
