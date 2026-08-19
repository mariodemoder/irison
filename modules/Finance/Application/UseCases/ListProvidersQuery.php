<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ProviderRepositoryInterface;

class ListProvidersQuery
{
    public function __construct(
        private readonly ProviderRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId): array
    {
        return array_map(
            fn ($provider) => $provider->toArray(),
            $this->repository->list($clinicId),
        );
    }
}
