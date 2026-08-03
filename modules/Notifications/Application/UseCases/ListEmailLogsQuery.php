<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\UseCases;

use Modules\Notifications\Application\DTOs\EmailLogFilterData;
use Modules\Notifications\Application\DTOs\EmailLogResponseData;
use Modules\Notifications\Infrastructure\Persistence\EmailLogRepository;

class ListEmailLogsQuery
{
    public function __construct(
        private readonly EmailLogRepository $repository,
    ) {}

    public function execute(EmailLogFilterData $filter): array
    {
        $paginator = $this->repository->paginate($filter->toArray(), $filter->perPage);

        return [
            'data' => collect($paginator->items())
                ->map(fn ($log) => EmailLogResponseData::fromModel($log))
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
                'sent_count' => $this->repository->countByStatus('sent'),
                'failed_count' => $this->repository->countByStatus('failed'),
            ],
        ];
    }
}
