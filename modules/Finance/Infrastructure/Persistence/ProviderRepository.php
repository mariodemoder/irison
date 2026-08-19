<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use Modules\Finance\Domain\Contracts\ProviderRepositoryInterface;
use Modules\Finance\Domain\Models\Provider;

class ProviderRepository implements ProviderRepositoryInterface
{
    public function list(int $clinicId): array
    {
        return ProviderEloquentModel::where('clinic_id', $clinicId)
            ->orderBy('name')
            ->get()
            ->map(fn ($model) => $this->toDomain($model))
            ->all();
    }

    public function findById(int $id, int $clinicId): ?Provider
    {
        $model = ProviderEloquentModel::where('clinic_id', $clinicId)->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function store(int $clinicId, array $attributes): Provider
    {
        $model = ProviderEloquentModel::create([
            'clinic_id' => $clinicId,
            'name' => $attributes['name'],
            'nif' => $attributes['nif'] ?? null,
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'address' => $attributes['address'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);

        return $this->toDomain($model);
    }

    public function update(int $id, int $clinicId, array $attributes): Provider
    {
        $model = ProviderEloquentModel::where('clinic_id', $clinicId)->findOrFail($id);

        $model->update([
            'name' => $attributes['name'] ?? $model->name,
            'nif' => array_key_exists('nif', $attributes) ? $attributes['nif'] : $model->nif,
            'email' => array_key_exists('email', $attributes) ? $attributes['email'] : $model->email,
            'phone' => array_key_exists('phone', $attributes) ? $attributes['phone'] : $model->phone,
            'address' => array_key_exists('address', $attributes) ? $attributes['address'] : $model->address,
            'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $model->notes,
        ]);

        return $this->toDomain($model->fresh());
    }

    public function delete(int $id, int $clinicId): int
    {
        return ProviderEloquentModel::where('clinic_id', $clinicId)
            ->where('id', $id)
            ->delete();
    }

    private function toDomain(ProviderEloquentModel $model): Provider
    {
        return new Provider(
            id: (int) $model->id,
            clinicId: (int) $model->clinic_id,
            name: (string) $model->name,
            nif: $model->nif,
            email: $model->email,
            phone: $model->phone,
            address: $model->address,
            notes: $model->notes,
            createdAt: $model->created_at,
            updatedAt: $model->updated_at,
        );
    }
}
