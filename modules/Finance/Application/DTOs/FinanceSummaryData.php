<?php

declare(strict_types=1);

namespace Modules\Finance\Application\DTOs;

class FinanceSummaryData
{
    public function __construct(
        public readonly array $currentPeriod,
        public readonly ?array $previousPeriod,
        public readonly ?array $variation,
        public readonly array $evolution,
        public readonly array $byPaymentMethod,
    ) {}

    public function toArray(): array
    {
        return [
            'current_period' => $this->currentPeriod,
            'previous_period' => $this->previousPeriod,
            'variation' => $this->variation,
            'evolution' => $this->evolution,
            'by_payment_method' => $this->byPaymentMethod,
        ];
    }
}
