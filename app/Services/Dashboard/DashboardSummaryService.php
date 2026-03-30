<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\CreditUsage;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\Bonus\BonusService;
use Carbon\Carbon;

class DashboardSummaryService
{
    private const REAL_PAYMENT_METHODS = ['cash', 'card', 'transfer', 'bizum', 'stripe'];

    public function __construct(private readonly BonusService $bonusService)
    {
    }

    public function summary(?Carbon $baseDate = null): array
    {
        $today = ($baseDate ?? now())->copy();
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();
        $todayDate = $today->toDateString();
        $creditMetrics = $this->buildCreditInFavorMetrics();

        $todaySummary = $this->buildTodaySummary($startOfDay, $endOfDay);
        $todayFinancial = $this->buildTodayFinancial($startOfDay, $endOfDay);
        $charts = $this->buildCharts($today);
        $importantAlerts = $this->buildImportantAlerts($startOfDay, $endOfDay, $creditMetrics);
        $riskAlerts = $this->buildRiskAlerts($todayDate, $creditMetrics);

        return [
            'data' => [
                'today_summary' => $todaySummary,
                'today_financial' => $todayFinancial,
                'charts' => $charts,
                'important_alerts' => $importantAlerts,
                'risk_alerts' => $riskAlerts,
                'date' => $todayDate,
            ],
        ];
    }

    public function cards(?Carbon $baseDate = null): array
    {
        $today = ($baseDate ?? now())->copy();
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();

        return [
            'data' => [
                'today_summary' => $this->buildTodaySummary($startOfDay, $endOfDay),
                'today_financial' => $this->buildTodayFinancial($startOfDay, $endOfDay),
                'charts' => $this->buildCharts($today),
                'date' => $today->toDateString(),
            ],
        ];
    }

    public function alerts(?Carbon $baseDate = null): array
    {
        $today = ($baseDate ?? now())->copy();
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();
        $todayDate = $today->toDateString();
        $creditMetrics = $this->buildCreditInFavorMetrics();

        return [
            'data' => [
                'important_alerts' => $this->buildImportantAlerts($startOfDay, $endOfDay, $creditMetrics),
                'risk_alerts' => $this->buildRiskAlerts($todayDate, $creditMetrics),
                'date' => $todayDate,
            ],
        ];
    }

    private function buildTodaySummary(Carbon $startOfDay, Carbon $endOfDay): array
    {
        $row = Appointment::query()
            ->whereBetween('start_time', [$startOfDay, $endOfDay])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled")
            ->selectRaw("SUM(CASE WHEN status IS NULL OR status NOT IN ('completed', 'canceled') THEN 1 ELSE 0 END) as pending")
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'completed' => (int) ($row?->completed ?? 0),
            'canceled' => (int) ($row?->canceled ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
        ];
    }

    private function buildTodayFinancial(Carbon $startOfDay, Carbon $endOfDay): array
    {
        $collectedAmount = (float) Payment::query()
            ->whereIn('method', self::REAL_PAYMENT_METHODS)
            ->whereBetween('paid_at', [$startOfDay, $endOfDay])
            ->sum('amount');

        $bonusRow = Appointment::query()
            ->whereBetween('start_time', [$startOfDay, $endOfDay])
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->where(function ($query) {
                $query->where('payment_type', 'bonus')
                    ->orWhere('payment_status', 'covered_by_pack')
                    ->orWhereNotNull('bonus_id');
            })
            ->selectRaw('COUNT(*) as sessions_used')
            ->selectRaw('COALESCE(SUM(price), 0) as sessions_value')
            ->first();

        $creditAppliedAmount = (float) CreditUsage::query()
            ->join('appointments', 'appointments.id', '=', 'credit_usages.appointment_id')
            ->whereNull('credit_usages.reversed_at')
            ->whereBetween('appointments.start_time', [$startOfDay, $endOfDay])
            ->where(function ($query) {
                $query->whereNull('appointments.status')
                    ->orWhere('appointments.status', '!=', 'canceled');
            })
            ->sum('credit_usages.amount');

        $bonusSessionsValue = (float) ($bonusRow?->sessions_value ?? 0);

        return [
            'collectedAmount' => round($collectedAmount, 2),
            'bonusSessionsUsed' => (int) ($bonusRow?->sessions_used ?? 0),
            'bonusSessionsValue' => round($bonusSessionsValue, 2),
            'creditAppliedAmount' => round($creditAppliedAmount, 2),
            'totalProductionAmount' => round($collectedAmount + $bonusSessionsValue + $creditAppliedAmount, 2),
        ];
    }

    private function buildImportantAlerts(Carbon $startOfDay, Carbon $endOfDay, array $creditMetrics): array
    {
        $unpaidSessionsBaseQuery = Appointment::query()
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->whereIn('payment_status', ['pending', 'partially_paid']);

        $unpaidSessionsCount = (clone $unpaidSessionsBaseQuery)->count();
        $unpaidSessionsTodayCount = (clone $unpaidSessionsBaseQuery)
            ->whereBetween('start_time', [$startOfDay, $endOfDay])
            ->count();

        return [
            'unpaidBonusesCount' => (int) $this->bonusService->unpaidCount(currentClinicId()),
            'creditInFavorAmount' => $creditMetrics['totalAmount'],
            'unpaidSessionsCount' => (int) $unpaidSessionsCount,
            'unpaidSessionsTodayCount' => (int) $unpaidSessionsTodayCount,
        ];
    }

    private function buildRiskAlerts(string $todayDate, array $creditMetrics): array
    {
        $appointmentsPaidSub = Payment::query()
            ->selectRaw('appointment_id, SUM(amount) as paid_amount')
            ->whereNotNull('appointment_id')
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->where('concept', 'appointment')
                    ->orWhereNull('concept');
            })
            ->groupBy('appointment_id');

        $appointmentsCreditSub = CreditUsage::query()
            ->selectRaw('appointment_id, SUM(amount) as credit_amount')
            ->whereNotNull('appointment_id')
            ->whereNull('reversed_at')
            ->groupBy('appointment_id');

        $pendingAmountExpression = 'GREATEST(COALESCE(appointments.price, 0) - COALESCE(appointments_paid.paid_amount, 0) - COALESCE(appointments_credit.credit_amount, 0), 0)';

        $completedUnpaidAppointmentsCount = (int) Appointment::query()
            ->leftJoinSub($appointmentsPaidSub, 'appointments_paid', function ($join) {
                $join->on('appointments_paid.appointment_id', '=', 'appointments.id');
            })
            ->leftJoinSub($appointmentsCreditSub, 'appointments_credit', function ($join) {
                $join->on('appointments_credit.appointment_id', '=', 'appointments.id');
            })
            ->where('appointments.status', 'completed')
            ->whereNull('appointments.bonus_id')
            ->whereRaw($pendingAmountExpression . ' > 0')
            ->count('appointments.id');

        $partialAppointmentsCount = (int) Appointment::query()
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->where('payment_status', 'partially_paid')
            ->count();

        $exhaustedBonusPatientsCount = (int) Bonus::query()
            ->where('remaining_sessions', '<=', 0)
            ->where(function ($query) use ($todayDate) {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $todayDate);
            })
            ->distinct('patient_id')
            ->count('patient_id');

        return [
            'completedUnpaidAppointmentsCount' => $completedUnpaidAppointmentsCount,
            'partialAppointmentsCount' => $partialAppointmentsCount,
            'exhaustedBonusPatientsCount' => $exhaustedBonusPatientsCount,
            'patientsWithCreditCount' => $creditMetrics['patientsCount'],
        ];
    }

    private function buildCharts(Carbon $baseDate): array
    {
        return [
            'monthly_revenue' => $this->buildMonthlyRevenueChart($baseDate),
            'weekly_appointments' => $this->buildWeeklyAppointmentsChart($baseDate),
        ];
    }

    private function buildMonthlyRevenueChart(Carbon $baseDate): array
    {
        $labels = [];
        $values = [];

        $monthStart = $baseDate->copy()->startOfMonth()->subMonths(3);

        for ($i = 0; $i < 4; $i++) {
            $start = $monthStart->copy()->addMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $amount = (float) Payment::query()
                ->where('status', 'completed')
                ->whereIn('method', self::REAL_PAYMENT_METHODS)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');

            $labels[] = ucfirst($start->locale('es')->isoFormat('MMM'));
            $values[] = round($amount, 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function buildWeeklyAppointmentsChart(Carbon $baseDate): array
    {
        $labels = [];
        $values = [];

        $weekStart = $baseDate->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(3);

        for ($i = 0; $i < 4; $i++) {
            $start = $weekStart->copy()->addWeeks($i)->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

            $count = (int) Appointment::query()
                ->whereBetween('start_time', [$start, $end])
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhere('status', '!=', 'canceled');
                })
                ->count();

            $labels[] = sprintf(
                '%s–%s',
                $start->copy()->locale('es')->isoFormat('DD MMM'),
                $end->copy()->locale('es')->isoFormat('DD MMM')
            );
            $values[] = $count;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function buildCreditInFavorMetrics(): array
    {
        $creditByPatientSub = Payment::query()
            ->selectRaw('patient_id, SUM(amount) as credit_total')
            ->where('concept', 'credit')
            ->where('status', 'completed')
            ->groupBy('patient_id');

        $creditUsageByPatientSub = CreditUsage::query()
            ->selectRaw('patient_id, SUM(amount) as credit_used')
            ->whereNull('reversed_at')
            ->groupBy('patient_id');

        $availableCreditExpression = 'GREATEST(COALESCE(credit_payments.credit_total, 0) - COALESCE(credit_usages.credit_used, 0), 0)';

        $row = Patient::query()
            ->leftJoinSub($creditByPatientSub, 'credit_payments', function ($join) {
                $join->on('credit_payments.patient_id', '=', 'patients.id');
            })
            ->leftJoinSub($creditUsageByPatientSub, 'credit_usages', function ($join) {
                $join->on('credit_usages.patient_id', '=', 'patients.id');
            })
            ->selectRaw('COALESCE(SUM(' . $availableCreditExpression . '), 0) as total_amount')
            ->selectRaw('SUM(CASE WHEN ' . $availableCreditExpression . ' > 0 THEN 1 ELSE 0 END) as patients_count')
            ->first();

        return [
            'totalAmount' => round((float) ($row?->total_amount ?? 0), 2),
            'patientsCount' => (int) ($row?->patients_count ?? 0),
        ];
    }
}
