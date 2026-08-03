<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use Modules\Notifications\Application\DTOs\EmailLogResponseData;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Infrastructure\Persistence\EmailLogEloquentModel;

class ShowEmailLogDetailQuery
{
    public function __construct(
        private readonly ReminderRepositoryInterface $reminderRepository,
    ) {}

    public function execute(EmailLogEloquentModel $log): array
    {
        $log->load([
            'patient:id,counter,first_name,last_name,email,phone',
            'appointment:id,clinic_id,patient_id,start_time,status',
            'appointment.patient:id,counter,first_name,last_name,email,phone',
            'clinic:id,name,email,phone,timezone',
            'reminder',
        ]);

        $history = [];
        $reminder = $log->reminder;

        if ($reminder && $log->appointment_id && $reminder->reminder_type) {
            try {
                $history = $this->reminderRepository->findHistory(
                    (int) $log->appointment_id,
                    ReminderType::from((string) $reminder->reminder_type),
                );
            } catch (\ValueError) {
                $history = [];
            }
        }

        return EmailLogResponseData::fromModel($log, $history);
    }
}
