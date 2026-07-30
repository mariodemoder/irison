<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonInterface;
use Modules\Notifications\Domain\Enums\NotificationChannel;
use Modules\Notifications\Domain\Enums\NotificationStatus;
use Modules\Notifications\Domain\Enums\ReminderType;

class ReminderLog
{
    public function __construct(
        public readonly int $id,
        public readonly int $clinicId,
        public readonly int $appointmentId,
        public readonly NotificationChannel $channel,
        public readonly ReminderType $reminderType,
        public readonly string $recipientEmail,
        public readonly NotificationStatus $status,
        public readonly ?CarbonInterface $sentAt = null,
        public readonly ?string $errorMessage = null,
        public readonly ?CarbonInterface $createdAt = null,
        public readonly ?CarbonInterface $updatedAt = null,
    ) {}

    public function isFailed(): bool
    {
        return $this->status === NotificationStatus::Failed;
    }

    public function isSent(): bool
    {
        return $this->status === NotificationStatus::Sent;
    }

    public function isQueued(): bool
    {
        return $this->status === NotificationStatus::Queued;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointmentId,
            'channel' => $this->channel->value,
            'reminder_type' => $this->reminderType->value,
            'recipient_email' => $this->recipientEmail,
            'status' => $this->status->value,
            'error_message' => $this->errorMessage,
            'sent_at' => $this->sentAt?->toDateTimeString(),
            'created_at' => $this->createdAt?->toDateTimeString(),
            'updated_at' => $this->updatedAt?->toDateTimeString(),
        ];
    }
}
