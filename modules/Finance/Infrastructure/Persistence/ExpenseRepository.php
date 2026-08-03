<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Domain\Contracts\ExpenseRepositoryInterface;
use Modules\Finance\Domain\Enums\ExpensePaymentMethod;
use Modules\Finance\Domain\Models\Expense;

class ExpenseRepository implements ExpenseRepositoryInterface
{
    public function paginate(int $clinicId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ExpenseEloquentModel::with('category:id,name,color')
            ->where('clinic_id', $clinicId);

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function ($q) use ($term): void {
                $q->where('concept', 'like', "%{$term}%")
                    ->orWhere('supplier', 'like', "%{$term}%")
                    ->orWhere('receipt_number', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }

        $paginator = $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);

        $mapper = fn (ExpenseEloquentModel $model) => $this->toResponseArray($model);

        $paginator->getCollection()->transform($mapper);

        return $paginator;
    }

    public function findById(int $id, int $clinicId): ?Expense
    {
        $model = ExpenseEloquentModel::where('clinic_id', $clinicId)->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function store(int $clinicId, array $attributes): Expense
    {
        $amount = (float) ($attributes['amount'] ?? 0);
        $taxRate = (float) ($attributes['tax_rate'] ?? 0);

        $model = ExpenseEloquentModel::create([
            'clinic_id' => $clinicId,
            'category_id' => $attributes['category_id'] ?? null,
            'concept' => $attributes['concept'],
            'supplier' => $attributes['supplier'] ?? null,
            'amount' => $amount,
            'tax_rate' => $taxRate,
            'total' => round($amount * (1 + $taxRate / 100), 2),
            'date' => $attributes['date'] ?? null,
            'payment_method' => $attributes['payment_method'] ?? null,
            'receipt_number' => $attributes['receipt_number'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);

        return $this->toDomain($model->load('category'));
    }

    public function update(int $id, int $clinicId, array $attributes): Expense
    {
        $model = ExpenseEloquentModel::where('clinic_id', $clinicId)->findOrFail($id);

        $payload = [];

        foreach (['concept', 'supplier', 'category_id', 'date', 'payment_method', 'receipt_number', 'notes'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('amount', $attributes)) {
            $payload['amount'] = (float) $attributes['amount'];
        }

        if (array_key_exists('tax_rate', $attributes)) {
            $payload['tax_rate'] = (float) $attributes['tax_rate'];
        }

        $amount = (float) ($payload['amount'] ?? $model->amount);
        $taxRate = (float) ($payload['tax_rate'] ?? $model->tax_rate);
        $payload['total'] = round($amount * (1 + $taxRate / 100), 2);

        $model->update($payload);

        return $this->toDomain($model->load('category'));
    }

    public function delete(int $id, int $clinicId): int
    {
        return ExpenseEloquentModel::where('clinic_id', $clinicId)
            ->where('id', $id)
            ->delete();
    }

    private function toResponseArray(ExpenseEloquentModel $model): array
    {
        return array_merge($this->toDomain($model)->toArray(), [
            'category' => $model->category ? [
                'id' => $model->category->id,
                'name' => $model->category->name,
                'color' => $model->category->color,
            ] : null,
        ]);
    }

    private function toDomain(ExpenseEloquentModel $model): Expense
    {
        return new Expense(
            id: (int) $model->id,
            clinicId: (int) $model->clinic_id,
            concept: (string) $model->concept,
            amount: (float) $model->amount,
            taxRate: (float) $model->tax_rate,
            total: (float) $model->total,
            categoryId: $model->category_id ? (int) $model->category_id : null,
            supplier: $model->supplier,
            date: $model->date ? Carbon::parse($model->date) : null,
            paymentMethod: $model->payment_method ? ExpensePaymentMethod::tryFrom($model->payment_method) : null,
            receiptNumber: $model->receipt_number,
            notes: $model->notes,
            createdAt: $model->created_at,
        );
    }
}