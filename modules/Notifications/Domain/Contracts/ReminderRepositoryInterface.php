<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Notifications\Domain\Enums\NotificationStatus;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Domain\Models\ReminderLog;

interface ReminderRepositoryInterface
{
    public function findById(int $id): ?ReminderLog;

    public function create(
        int $clinicId,
        int $appointmentId,
        ReminderType $reminderType,
        string $recipientEmail,
        NotificationStatus $status,
        ?CarbonInterface $sentAt = null,
        ?string $errorMessage = null,
    ): ReminderLog;

    public function updateStatus(int $id, NotificationStatus $status, ?CarbonInterface $sentAt = null, ?string $errorMessage = null): void;

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findHistory(int $appointmentId, ReminderType $reminderType): array;

    public function count(): int;

    public function countByStatus(NotificationStatus $status): int;
}
