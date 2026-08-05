<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class BackofficeAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $type,
        private readonly int $clinicId,
        private readonly string $clinicName,
        private readonly string $message,
        private readonly array $extra = [],
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        return new DatabaseMessage(array_merge([
            'type' => $this->type,
            'clinic_id' => $this->clinicId,
            'clinic_name' => $this->clinicName,
            'message' => $this->message,
        ], $this->extra));
    }
}
