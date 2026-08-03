<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ExpenseRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateExpenseCommand
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
    ) {}

    public function execute(int $id, int $clinicId, array $validated): array
    {
        $expense = $this->repository->findById($id, $clinicId);

        if (! $expense) {
            throw new NotFoundHttpException('Gasto no encontrado.');
        }

        return $this->repository->update($id, $clinicId, $validated)->toArray();
    }
}
