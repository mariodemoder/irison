<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Contracts;

use Modules\Finance\Domain\Models\ExpenseCategory;

interface ExpenseCategoryRepositoryInterface
{
    /**
     * @return ExpenseCategory[] categorías de la clínica
     */
    public function list(int $clinicId): array;

    public function findById(int $id, int $clinicId): ?ExpenseCategory;

    public function store(int $clinicId, string $name, ?string $color, ?string $description = null): ExpenseCategory;

    public function update(int $id, int $clinicId, array $attributes): ExpenseCategory;

    public function delete(int $id, int $clinicId): int;
}