<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use Carbon\Carbon;
use Modules\Finance\Application\DTOs\BenefitsReportData;
use Modules\Finance\Domain\Contracts\BenefitsDataProviderInterface;
use Modules\Finance\Domain\Services\MarginCalculator;

class BuildBenefitsReportQuery
{
    public function __construct(
        private readonly BenefitsDataProviderInterface $dataProvider,
        private readonly MarginCalculator $marginCalculator,
    ) {}

    public function execute(int $clinicId, ?string $fromDate, ?string $toDate): BenefitsReportData
    {
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : null;
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : null;

        $revenue = $this->dataProvider->revenueOnPeriod($clinicId, $from, $to);
        $expenses = $this->dataProvider->expensesTotalOnPeriod($clinicId, $from, $to);
        $laborCost = $this->dataProvider->laborCostOnPeriod($clinicId, $from, $to);

        $cost = round($expenses + $laborCost, 2);
        $profit = $this->marginCalculator->margin($revenue, $cost);
        $marginPercentage = $this->marginCalculator->marginPercentage($revenue, $cost);

        [$previousTotals, $variation] = $this->computeComparison($clinicId, $from, $to, $revenue, $expenses, $profit, $marginPercentage);

        return new BenefitsReportData(
            revenue: $revenue,
            expenses: $expenses,
            laborCost: $laborCost,
            cost: $cost,
            profit: $profit,
            marginPercentage: $marginPercentage,
            byService: $this->dataProvider->revenueByAppointmentType($clinicId, $from, $to),
            byProfessional: $this->dataProvider->revenueAndLaborByProfessional($clinicId, $from, $to),
            byCategory: $this->dataProvider->expensesByCategory($clinicId, $from, $to),
            previousTotals: $previousTotals,
            variation: $variation,
        );
    }

    /**
     * @return array{0: ?array, 1: ?array}
     */
    private function computeComparison(
        int $clinicId,
        ?Carbon $from,
        ?Carbon $to,
        float $revenue,
        float $expenses,
        float $profit,
        ?float $marginPercentage,
    ): array {
        if (! $from || ! $to) {
            return [null, null];
        }

        $lengthDays = $from->copy()->startOfDay()->diffInDays($to->copy()->endOfDay()) + 1;
        $prevFrom = $from->copy()->subDays($lengthDays)->startOfDay();
        $prevTo = $from->copy()->subDay()->endOfDay();

        $prevRevenue = $this->dataProvider->revenueOnPeriod($clinicId, $prevFrom, $prevTo);
        $prevExpenses = $this->dataProvider->expensesTotalOnPeriod($clinicId, $prevFrom, $prevTo);
        $prevLaborCost = $this->dataProvider->laborCostOnPeriod($clinicId, $prevFrom, $prevTo);
        $prevCost = round($prevExpenses + $prevLaborCost, 2);
        $prevProfit = $this->marginCalculator->margin($prevRevenue, $prevCost);
        $prevMarginPercentage = $this->marginCalculator->marginPercentage($prevRevenue, $prevCost);

        return [
            [
                'revenue' => $prevRevenue,
                'expenses' => $prevExpenses,
                'labor_cost' => $prevLaborCost,
                'cost' => $prevCost,
                'profit' => $prevProfit,
                'margin_percentage' => $prevMarginPercentage,
            ],
            [
                'revenue' => $this->percentageChange($revenue, $prevRevenue),
                'expenses' => $this->percentageChange($expenses, $prevExpenses),
                'profit' => $this->percentageChange($profit, $prevProfit),
                'margin_percentage' => $prevMarginPercentage !== null && $marginPercentage !== null
                    ? round($marginPercentage - $prevMarginPercentage, 2)
                    : null,
            ],
        ];
    }

    private function percentageChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.0001) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 2);
    }
}