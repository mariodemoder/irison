<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ExpenseCategoryRepositoryInterface;

class ListExpenseCategoriesQuery
{
    public function __construct(
        private readonly ExpenseCategoryRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId): array
    {
        return array_map(
            fn ($category) => $category->toArray(),
            $this->repository->list($clinicId),
        );
    }
}
