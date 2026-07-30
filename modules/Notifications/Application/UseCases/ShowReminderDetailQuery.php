<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use Modules\Notifications\Application\DTOs\ReminderResponseData;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ShowReminderDetailQuery
{
    public function __construct(
        private readonly ReminderRepositoryInterface $repository,
    ) {}

    public function execute(ReminderEloquentModel $reminder): array
    {
        $reminder->load([
            'appointment:id,clinic_id,patient_id,start_time,status,reminder_24h_sent_at,reminder_2h_sent_at',
            'appointment.patient:id,counter,first_name,last_name,email,phone',
            'appointment.clinic:id,name,timezone,email,phone',
        ]);

        $reminderType = $reminder->reminder_type ? ReminderType::from($reminder->reminder_type) : null;

        $history = $reminderType
            ? $this->repository->findHistory($reminder->appointment_id, $reminderType)
            : [];

        $clinic = $reminder->appointment?->clinic ? [
            'id' => $reminder->appointment->clinic->id,
            'name' => $reminder->appointment->clinic->name,
            'timezone' => $reminder->appointment->clinic->timezone,
            'email' => $reminder->appointment->clinic->email,
            'phone' => $reminder->appointment->clinic->phone,
        ] : null;

        return ReminderResponseData::fromReminderWithAppointment($reminder, $history, $clinic);
    }
}
