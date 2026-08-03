<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use Modules\Finance\Domain\Contracts\ExpenseCategoryRepositoryInterface;
use Modules\Finance\Domain\Models\ExpenseCategory;

class ExpenseCategoryRepository implements ExpenseCategoryRepositoryInterface
{
    public function list(int $clinicId): array
    {
        return ExpenseCategoryEloquentModel::where('clinic_id', $clinicId)
            ->orderBy('name')
            ->get()
            ->map(fn ($model) => $this->toDomain($model))
            ->all();
    }

    public function findById(int $id, int $clinicId): ?ExpenseCategory
    {
        $model = ExpenseCategoryEloquentModel::where('clinic_id', $clinicId)->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function store(int $clinicId, string $name, ?string $color, ?string $description = null): ExpenseCategory
    {
        $model = ExpenseCategoryEloquentModel::create([
            'clinic_id' => $clinicId,
            'name' => $name,
            'color' => $color,
            'description' => $description,
        ]);

        return $this->toDomain($model);
    }

    public function update(int $id, int $clinicId, array $attributes): ExpenseCategory
    {
        $model = ExpenseCategoryEloquentModel::where('clinic_id', $clinicId)->findOrFail($id);

        $model->update([
            'name' => $attributes['name'] ?? $model->name,
            'color' => array_key_exists('color', $attributes) ? $attributes['color'] : $model->color,
            'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $model->description,
        ]);

        return $this->toDomain($model->fresh());
    }

    public function delete(int $id, int $clinicId): int
    {
        return ExpenseCategoryEloquentModel::where('clinic_id', $clinicId)
            ->where('id', $id)
            ->delete();
    }

    private function toDomain(ExpenseCategoryEloquentModel $model): ExpenseCategory
    {
        return new ExpenseCategory(
            id: (int) $model->id,
            clinicId: (int) $model->clinic_id,
            name: (string) $model->name,
            color: $model->color,
            description: $model->description,
            createdAt: $model->created_at,
        );
    }
}