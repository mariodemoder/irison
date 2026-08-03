<?php

declare(strict_types=1);

namespace Modules\Activity\Domain\Contracts;

use Modules\Activity\Application\DTOs\ActivityFilterData;

interface ActivityRepositoryInterface
{
    public function search(int $clinicId, ActivityFilterData $filter): array;
}
