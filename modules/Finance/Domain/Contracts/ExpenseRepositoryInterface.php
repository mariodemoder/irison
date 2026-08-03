<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Finance\Domain\Models\Expense;

interface ExpenseRepositoryInterface
{
    public function paginate(int $clinicId, array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id, int $clinicId): ?Expense;

    public function store(int $clinicId, array $attributes): Expense;

    public function update(int $id, int $clinicId, array $attributes): Expense;

    /**
     * @return int 1 si se eliminó, 0 si no existía (scoped por clínica)
     */
    public function delete(int $id, int $clinicId): int;
}