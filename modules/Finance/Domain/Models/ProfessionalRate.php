<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Carbon\CarbonInterface;

class ProfessionalRate
{
    public function __construct(
        public readonly int $id,
        public readonly int $clinicId,
        public readonly int $userId,
        public readonly float $costPerHour,
        public readonly ?CarbonInterface $createdAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'cost_per_hour' => number_format($this->costPerHour, 2, '.', ''),
            'created_at' => $this->createdAt?->toDateTimeString(),
        ];
    }
}
