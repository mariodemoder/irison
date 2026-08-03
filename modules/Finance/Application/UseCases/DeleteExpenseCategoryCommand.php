<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ExpenseCategoryRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteExpenseCategoryCommand
{
    public function __construct(
        private readonly ExpenseCategoryRepositoryInterface $repository,
    ) {}

    public function execute(int $id, int $clinicId): void
    {
        if ($this->repository->delete($id, $clinicId) === 0) {
            throw new NotFoundHttpException('Categoría no encontrada.');
        }
    }
}
