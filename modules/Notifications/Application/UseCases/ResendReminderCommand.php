<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use Illuminate\Support\Facades\Notification;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Domain\Services\ReminderDomainService;
use Modules\Notifications\Patient\Notifications\AppointmentReminderNotification;
use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ResendReminderCommand
{
    public function __construct(
        private readonly ReminderDomainService $domainService,
    ) {}

    public function execute(ReminderEloquentModel $reminder): ReminderEloquentModel
    {
        $appointment = $reminder->appointment()
            ->with(['patient', 'clinic'])
            ->firstOrFail();

        $result = $this->domainService->sendAppointmentReminder(
            $appointment,
            ReminderType::from((string) $reminder->reminder_type),
            markAppointmentSent: true,
            throwOnFailure: true,
        );

        if ($result['sent'] && $appointment->patient?->email) {
            Notification::route('mail', $appointment->patient->email)
                ->notify(new AppointmentReminderNotification(
                    $appointment,
                    ReminderType::from((string) $reminder->reminder_type),
                    $result['reminder']->id,
                ));
        }

        return ReminderEloquentModel::findOrFail($reminder->id);
    }
}
