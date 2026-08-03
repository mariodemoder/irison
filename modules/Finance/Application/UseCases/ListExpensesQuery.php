<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Application\DTOs\ExpenseFilterData;
use Modules\Finance\Domain\Contracts\ExpenseRepositoryInterface;

class ListExpensesQuery
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId, ExpenseFilterData $filter): array
    {
        $paginator = $this->repository->paginate($clinicId, $filter->toArray(), $filter->perPage);

        return [
            'data' => array_values($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
