<?php

declare(strict_types=1);

namespace Modules\Finance\Application\DTOs;

class ExpenseFilterData
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?int $categoryId = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            q: $validated['q'] ?? null,
            categoryId: isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            paymentMethod: $validated['payment_method'] ?? null,
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 15),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'q' => $this->q,
            'category_id' => $this->categoryId,
            'payment_method' => $this->paymentMethod,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'per_page' => $this->perPage,
        ], fn ($v) => $v !== null);
    }
}