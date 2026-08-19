<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Carbon\Carbon;
use Modules\Finance\Application\DTOs\FinanceSummaryData;
use Modules\Finance\Domain\Contracts\BenefitsDataProviderInterface;
use Modules\Finance\Domain\Services\MarginCalculator;

class BuildFinanceSummaryQuery
{
    public function __construct(
        private readonly BenefitsDataProviderInterface $dataProvider,
        private readonly MarginCalculator $marginCalculator,
    ) {}

    public function execute(int $clinicId, ?string $fromDate = null, ?string $toDate = null): FinanceSummaryData
    {
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : Carbon::now()->startOfMonth();
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : Carbon::now()->endOfDay();

        // Current period
        $revenue = $this->dataProvider->revenueOnPeriod($clinicId, $from, $to);
        $expenses = $this->dataProvider->expensesTotalOnPeriod($clinicId, $from, $to);
        $laborCost = $this->dataProvider->laborCostOnPeriod($clinicId, $from, $to);
        $cost = round($expenses + $laborCost, 2);
        $profit = $this->marginCalculator->margin($revenue, $cost);
        $marginPercentage = $this->marginCalculator->marginPercentage($revenue, $cost);
        $paidOperationsCount = $this->dataProvider->paidOperationsCount($clinicId, $from, $to);
        $pendingCount = $this->dataProvider->pendingPaymentsCount($clinicId);
        $pendingAmount = $this->dataProvider->pendingPaymentsAmount($clinicId);

        $ticketMedio = $paidOperationsCount > 0
            ? round($revenue / $paidOperationsCount, 2)
            : 0.0;

        $currentPeriod = [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'labor_cost' => $laborCost,
            'profit' => $profit,
            'margin_percentage' => $marginPercentage,
            'ticket_medio' => $ticketMedio,
            'paid_operations_count' => $paidOperationsCount,
            'pending_count' => $pendingCount,
            'pending_amount' => $pendingAmount,
        ];

        // Previous period (same length, immediately before)
        $lengthDays = max($from->copy()->startOfDay()->diffInDays($to->copy()->endOfDay()) + 1, 1);
        $prevFrom = $from->copy()->subDays($lengthDays)->startOfDay();
        $prevTo = $from->copy()->subDay()->endOfDay();

        $prevRevenue = $this->dataProvider->revenueOnPeriod($clinicId, $prevFrom, $prevTo);
        $prevExpenses = $this->dataProvider->expensesTotalOnPeriod($clinicId, $prevFrom, $prevTo);
        $prevLaborCost = $this->dataProvider->laborCostOnPeriod($clinicId, $prevFrom, $prevTo);
        $prevCost = round($prevExpenses + $prevLaborCost, 2);
        $prevProfit = $this->marginCalculator->margin($prevRevenue, $prevCost);
        $prevMarginPercentage = $this->marginCalculator->marginPercentage($prevRevenue, $prevCost);

        $previousPeriod = [
            'revenue' => $prevRevenue,
            'expenses' => $prevExpenses,
            'profit' => $prevProfit,
            'margin_percentage' => $prevMarginPercentage,
        ];

        // Variation
        $variation = [
            'revenue' => $this->percentageChange($revenue, $prevRevenue),
            'expenses' => $this->percentageChange($expenses, $prevExpenses),
            'profit' => $this->percentageChange($profit, $prevProfit),
            'margin_percentage' => $prevMarginPercentage !== null && $marginPercentage !== null
                ? round($marginPercentage - $prevMarginPercentage, 2)
                : null,
        ];

        // Evolution (last 12 months)
        $evolution = $this->dataProvider->revenueEvolution($clinicId, 12);

        // Payment methods
        $byPaymentMethod = $this->dataProvider->revenueByPaymentMethod($clinicId, $from, $to);

        return new FinanceSummaryData(
            currentPeriod: $currentPeriod,
            previousPeriod: $previousPeriod,
            variation: $variation,
            evolution: $evolution,
            byPaymentMethod: $byPaymentMethod,
        );
    }

    private function percentageChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.0001) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 2);
    }
}
