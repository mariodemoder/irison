<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Domain\Services\ReminderDomainService;
use Modules\Notifications\Patient\Notifications\AppointmentReminderNotification;

class SendAppointmentReminder2hJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        AppointmentReminderQueryService $queryService,
        ReminderDomainService $domainService,
    ): void {
        $appointments = $queryService->getAppointmentsFor2hReminder();

        foreach ($appointments as $appointment) {
            $result = $domainService->sendAppointmentReminder($appointment, ReminderType::TwoHours);

            if ($result['sent']) {
                $appointment->forceFill([
                    'reminder_2h_sent_at' => now(),
                ])->save();

                Notification::route('mail', $appointment->patient->email)
                    ->notify(new AppointmentReminderNotification($appointment, ReminderType::TwoHours, $result['reminder']->id));
            }
        }
    }
}
