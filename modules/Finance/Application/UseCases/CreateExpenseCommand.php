<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ExpenseRepositoryInterface;

class CreateExpenseCommand
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId, array $validated): array
    {
        return $this->repository->store($clinicId, $validated)->toArray();
    }
}
