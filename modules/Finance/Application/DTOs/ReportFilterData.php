<?php

declare(strict_types=1);

namespace Modules\Finance\Application\DTOs;

class ReportFilterData
{
    public const VALID_TYPES = ['income', 'expenses', 'profit', 'professional', 'service'];
    public const VALID_GROUP_BY = ['day', 'week', 'month'];

    public function __construct(
        public readonly string $type,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly string $groupBy = 'day',
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            type: $validated['type'],
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
            groupBy: $validated['group_by'] ?? 'day',
        );
    }
}
