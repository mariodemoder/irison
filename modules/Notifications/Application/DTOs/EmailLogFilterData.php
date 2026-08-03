<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\DTOs;

class EmailLogFilterData
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $status = null,
        public readonly ?string $category = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            q: $validated['q'] ?? null,
            status: $validated['status'] ?? null,
            category: $validated['category'] ?? null,
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 15),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'q' => $this->q,
            'status' => $this->status,
            'category' => $this->category,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'per_page' => $this->perPage,
        ], fn ($v) => $v !== null);
    }
}
