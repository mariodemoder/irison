<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Contracts;

use Modules\Finance\Domain\Models\ProfessionalRate;

interface ProfessionalRateRepositoryInterface
{
    /**
     * @return list<array{user_id:int, cost_per_hour:float, user_name:?string}> Tarifas de la clínica mapeadas para respuesta
     */
    public function listForClinic(int $clinicId): array;

    public function findForUser(int $userId, int $clinicId): ?ProfessionalRate;

    public function save(int $clinicId, int $userId, float $costPerHour): ProfessionalRate;

    public function deleteForUser(int $userId, int $clinicId): int;
}