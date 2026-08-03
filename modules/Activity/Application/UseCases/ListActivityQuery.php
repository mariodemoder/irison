<?php

declare(strict_types=1);

namespace Modules\Activity\Application\UseCases;

use Modules\Activity\Application\DTOs\ActivityFilterData;
use Modules\Activity\Domain\Contracts\ActivityRepositoryInterface;

class ListActivityQuery
{
    public function __construct(
        private readonly ActivityRepositoryInterface $repository,
    ) {}

    public function execute(int $clinicId, ActivityFilterData $filter): array
    {
        return $this->repository->search($clinicId, $filter);
    }
}
