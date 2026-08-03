<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ExpenseCategoryRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateExpenseCategoryCommand
{
    public function __construct(
        private readonly ExpenseCategoryRepositoryInterface $repository,
    ) {}

    public function execute(int $id, int $clinicId, array $validated): array
    {
        if (! $this->repository->findById($id, $clinicId)) {
            throw new NotFoundHttpException('Categoría no encontrada.');
        }

        return $this->repository->update($id, $clinicId, $validated)->toArray();
    }
}
