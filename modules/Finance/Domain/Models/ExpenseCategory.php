<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Carbon\CarbonInterface;

class ExpenseCategory
{
    public function __construct(
        public readonly int $id,
        public readonly int $clinicId,
        public readonly string $name,
        public readonly ?string $color = null,
        public readonly ?string $description = null,
        public readonly ?CarbonInterface $createdAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'description' => $this->description,
            'created_at' => $this->createdAt?->toDateTimeString(),
        ];
    }
}
