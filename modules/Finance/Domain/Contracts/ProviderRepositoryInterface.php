<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Contracts;

use Modules\Finance\Domain\Models\Provider;

interface ProviderRepositoryInterface
{
    /**
     * @return Provider[]
     */
    public function list(int $clinicId): array;

    public function findById(int $id, int $clinicId): ?Provider;

    public function store(int $clinicId, array $attributes): Provider;

    public function update(int $id, int $clinicId, array $attributes): Provider;

    public function delete(int $id, int $clinicId): int;
}
