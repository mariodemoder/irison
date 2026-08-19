<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ProviderRepositoryInterface;

class CreateProviderCommand
{
    public function __construct(
        private readonly ProviderRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId, array $validated): array
    {
        return $this->repository->store($clinicId, $validated)->toArray();
    }
}
