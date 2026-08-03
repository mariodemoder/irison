<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ExpenseCategoryRepositoryInterface;

class CreateExpenseCategoryCommand
{
    public function __construct(
        private readonly ExpenseCategoryRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId, array $validated): array
    {
        return $this->repository->store(
            $clinicId,
            $validated['name'],
            $validated['color'] ?? null,
            $validated['description'] ?? null,
        )->toArray();
    }
}
