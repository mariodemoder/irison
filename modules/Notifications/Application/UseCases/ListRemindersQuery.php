<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use Modules\Notifications\Application\DTOs\ReminderFilterData;
use Modules\Notifications\Application\DTOs\ReminderResponseData;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Domain\Enums\NotificationStatus;

class ListRemindersQuery
{
    public function __construct(
        private readonly ReminderRepositoryInterface $repository,
    ) {}

    public function execute(ReminderFilterData $filter): array
    {
        $paginator = $this->repository->paginate($filter->toArray(), $filter->perPage);

        return [
            'data' => collect($paginator->items())
                ->map(fn ($reminder) => ReminderResponseData::fromReminderWithAppointment($reminder))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => [
                'count' => $this->repository->count(),
                'sent_count' => $this->repository->countByStatus(NotificationStatus::Sent),
                'failed_count' => $this->repository->countByStatus(NotificationStatus::Failed),
            ],
        ];
    }
}
