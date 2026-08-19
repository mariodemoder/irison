<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Carbon\CarbonInterface;

class Provider
{
    public function __construct(
        public readonly int $id,
        public readonly int $clinicId,
        public readonly string $name,
        public readonly ?string $nif = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $notes = null,
        public readonly ?CarbonInterface $createdAt = null,
        public readonly ?CarbonInterface $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nif' => $this->nif,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'created_at' => $this->createdAt?->toDateTimeString(),
            'updated_at' => $this->updatedAt?->toDateTimeString(),
        ];
    }
}
