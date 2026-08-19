<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ProviderRepositoryInterface;

class UpdateProviderCommand
{
    public function __construct(
        private readonly ProviderRepositoryInterface $repository,
    ) {}

    public function execute(int $id, int $clinicId, array $validated): array
    {
        return $this->repository->update($id, $clinicId, $validated)->toArray();
    }
}
