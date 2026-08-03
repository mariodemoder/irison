<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ProfessionalRateRepositoryInterface;

class ListProfessionalRatesQuery
{
    public function __construct(
        private readonly ProfessionalRateRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId): array
    {
        return $this->repository->listForClinic($clinicId);
    }
}