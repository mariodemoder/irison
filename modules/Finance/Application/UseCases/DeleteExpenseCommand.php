<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ExpenseRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteExpenseCommand
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
    ) {}

    public function execute(int $id, int $clinicId): void
    {
        if ($this->repository->delete($id, $clinicId) === 0) {
            throw new NotFoundHttpException('Gasto no encontrado.');
        }
    }
}
