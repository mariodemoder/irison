<?php

declare(strict_types=1);

namespace Modules\Finance\Application\DTOs;

class BenefitsReportData
{
    public function __construct(
        public readonly float $revenue,
        public readonly float $expenses,
        public readonly float $laborCost,
        public readonly float $cost,
        public readonly float $profit,
        public readonly ?float $marginPercentage,
        public readonly array $byService,
        public readonly array $byProfessional,
        public readonly array $byCategory,
        public readonly ?array $previousTotals,
        public readonly ?array $variation,
    ) {}

    public function toArray(): array
    {
        return [
            'totals' => [
                'revenue' => $this->revenue,
                'expenses' => $this->expenses,
                'labor_cost' => $this->laborCost,
                'cost' => $this->cost,
                'profit' => $this->profit,
                'margin_percentage' => $this->marginPercentage,
            ],
            'by_service' => $this->byService,
            'by_professional' => $this->byProfessional,
            'by_category' => $this->byCategory,
            'previous_totals' => $this->previousTotals,
            'variation' => $this->variation,
        ];
    }
}