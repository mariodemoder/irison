<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Contracts;

use Carbon\CarbonInterface;

/**
 * Provee las agregaciones financieras necesarias para el Dashboard de beneficios.
 * Implementado en Infrastructure\Persistence\BenefitsDataProvider.
 */
interface BenefitsDataProviderInterface
{
    public function revenueOnPeriod(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): float;

    public function expensesTotalOnPeriod(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): float;

    public function laborCostOnPeriod(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): float;

    /**
     * @return list<array{name:string, count:int, revenue:float}>
     */
    public function revenueByAppointmentType(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): array;

    /**
     * @return list<array{user_id:int, user_name:string, revenue:float, labor_cost:float, contribution:float}>
     */
    public function revenueAndLaborByProfessional(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): array;

    /**
     * @return list<array{name:string, total:float}>
     */
    public function expensesByCategory(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): array;

    public function paidOperationsCount(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): int;

    /**
     * @return list<array{method:string, label:string, count:int, total:float, percentage:float}>
     */
    public function revenueByPaymentMethod(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): array;

    public function pendingPaymentsCount(int $clinicId): int;

    public function pendingPaymentsAmount(int $clinicId): float;

    /**
     * Monthly revenue/expenses/profit for the last N months.
     *
     * @return list<array{month:string, revenue:float, expenses:float, profit:float}>
     */
    public function revenueEvolution(int $clinicId, int $months = 12): array;
}