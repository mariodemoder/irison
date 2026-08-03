<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ProfessionalRateRepositoryInterface;

class SaveProfessionalRateCommand
{
    public function __construct(
        private readonly ProfessionalRateRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId, int $userId, float $costPerHour, ?bool $remove = false): array
    {
        if ($remove || $costPerHour <= 0) {
            $this->repository->deleteForUser($userId, $clinicId);

            return [
                'user_id' => $userId,
                'cost_per_hour' => '0.00',
            ];
        }

        return $this->repository->save($clinicId, $userId, $costPerHour)->toArray();
    }
}