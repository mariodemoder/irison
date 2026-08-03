<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ExpenseRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowExpenseDetailQuery
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
    ) {}

    public function execute(int $id, int $clinicId): array
    {
        $expense = $this->repository->findById($id, $clinicId);

        if (! $expense) {
            throw new NotFoundHttpException('Gasto no encontrado.');
        }

        return $expense->toArray();
    }
}
