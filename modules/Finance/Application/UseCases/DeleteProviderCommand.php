<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Modules\Finance\Domain\Contracts\ProviderRepositoryInterface;

class DeleteProviderCommand
{
    public function __construct(
        private readonly ProviderRepositoryInterface $repository,
    ) {}

    public function execute(int $id, int $clinicId): int
    {
        return $this->repository->delete($id, $clinicId);
    }
}
