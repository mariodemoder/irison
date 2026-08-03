<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use DomainException;
use Modules\Notifications\Infrastructure\Persistence\EmailLogEloquentModel;
use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ResendEmailLogCommand
{
    public function __construct(
        private readonly ResendReminderCommand $resendReminder,
    ) {}

    public function execute(EmailLogEloquentModel $log): void
    {
        if (!$log->reminder_id) {
            throw new DomainException('Este envío no admite reenvío.');
        }

        $reminder = ReminderEloquentModel::find($log->reminder_id);
        if (!$reminder) {
            throw new DomainException('El recordatorio original no existe.');
        }

        $this->resendReminder->execute($reminder);
    }
}
