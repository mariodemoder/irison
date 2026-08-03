<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use App\Models\User;
use Modules\Finance\Domain\Contracts\ProfessionalRateRepositoryInterface;
use Modules\Finance\Domain\Models\ProfessionalRate;

class ProfessionalRateRepository implements ProfessionalRateRepositoryInterface
{
    public function listForClinic(int $clinicId): array
    {
        return ProfessionalRateEloquentModel::with('user:id,name')
            ->where('clinic_id', $clinicId)
            ->orderBy('id')
            ->get()
            ->map(fn (ProfessionalRateEloquentModel $model) => [
                'user_id' => (int) $model->user_id,
                'cost_per_hour' => (float) $model->cost_per_hour,
                'user_name' => $model->user?->name,
            ])
            ->values()
            ->all();
    }

    public function findForUser(int $userId, int $clinicId): ?ProfessionalRate
    {
        $model = ProfessionalRateEloquentModel::where('clinic_id', $clinicId)
            ->where('user_id', $userId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(int $clinicId, int $userId, float $costPerHour): ProfessionalRate
    {
        $model = ProfessionalRateEloquentModel::updateOrCreate(
            ['clinic_id' => $clinicId, 'user_id' => $userId],
            ['cost_per_hour' => round($costPerHour, 2)],
        );

        return $this->toDomain($model->fresh());
    }

    public function deleteForUser(int $userId, int $clinicId): int
    {
        return ProfessionalRateEloquentModel::where('clinic_id', $clinicId)
            ->where('user_id', $userId)
            ->delete();
    }

    private function toDomain(ProfessionalRateEloquentModel $model): ProfessionalRate
    {
        return new ProfessionalRate(
            id: (int) $model->id,
            clinicId: (int) $model->clinic_id,
            userId: (int) $model->user_id,
            costPerHour: (float) $model->cost_per_hour,
            createdAt: $model->created_at,
        );
    }
}