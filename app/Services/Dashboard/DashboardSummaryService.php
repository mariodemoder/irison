<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\Clinic;
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
            'todayInactivityMinutes' => $this->buildTodayInactivityMinutes(),
        ];
    }

    private function buildTodayInactivityMinutes(): int
    {
        $clinicId = currentClinicId();
        if ($clinicId <= 0) {
            return 0;
        }

        $clinic = Clinic::query()->find($clinicId);
        if (! $clinic) {
            return 0;
        }

        $timezone = trim((string) ($clinic->timezone ?? '')) ?: (string) config('app.timezone', 'UTC');
        $now = now($timezone);
        $todayIso = $now->toDateString();

        if ($this->isDateClosed($todayIso, (array) ($clinic->closed_days ?? []))) {
            return 0;
        }

        $businessHours = (array) ($clinic->business_hours ?? []);
        if (empty($businessHours)) {
            return 0;
        }

        $dayKey = strtolower($now->englishDayOfWeek);
        $dayConfig = collect($businessHours)->first(function ($item) use ($dayKey) {
            return strtolower((string) ($item['day'] ?? '')) === $dayKey;
        });

        if (! $dayConfig || ! filter_var($dayConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return 0;
        }

        $openStart = trim((string) ($dayConfig['start'] ?? ''));
        $openEnd = trim((string) ($dayConfig['end'] ?? ''));
        if (! preg_match('/^\d{2}:\d{2}$/', $openStart) || ! preg_match('/^\d{2}:\d{2}$/', $openEnd)) {
            return 0;
        }

        $openAt = Carbon::parse($todayIso . ' ' . $openStart, $timezone);
        $closeAt = Carbon::parse($todayIso . ' ' . $openEnd, $timezone);
        if ($closeAt->lessThanOrEqualTo($openAt) || $now->lessThanOrEqualTo($openAt)) {
            return 0;
        }

        $windowEnd = $now->lessThan($closeAt) ? $now->copy() : $closeAt->copy();
        if ($windowEnd->lessThanOrEqualTo($openAt)) {
            return 0;
        }

        $appointments = Appointment::query()
            ->where('start_time', '<', $windowEnd)
            ->where('end_time', '>', $openAt)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        $intervals = [];
        foreach ($appointments as $appointment) {
            if (! $appointment->start_time || ! $appointment->end_time) {
                continue;
            }

            $start = $appointment->start_time->copy();
            $end = $appointment->end_time->copy();

            if ($end->lessThanOrEqualTo($openAt) || $start->greaterThanOrEqualTo($windowEnd)) {
                continue;
            }

            if ($start->lessThan($openAt)) {
                $start = $openAt->copy();
            }

            if ($end->greaterThan($windowEnd)) {
                $end = $windowEnd->copy();
            }

            if ($end->greaterThan($start)) {
                $intervals[] = [$start, $end];
            }
        }

        if (empty($intervals)) {
            return max(0, $openAt->diffInMinutes($windowEnd));
        }

        usort($intervals, function (array $a, array $b): int {
            return $a[0]->lessThan($b[0]) ? -1 : ($a[0]->equalTo($b[0]) ? 0 : 1);
        });

        $merged = [];
        foreach ($intervals as [$start, $end]) {
            if (empty($merged)) {
                $merged[] = [$start, $end];
                continue;
            }

            $lastIndex = count($merged) - 1;
            $lastStart = $merged[$lastIndex][0];
            $lastEnd = $merged[$lastIndex][1];

            if ($start->lessThanOrEqualTo($lastEnd)) {
                if ($end->greaterThan($lastEnd)) {
                    $merged[$lastIndex] = [$lastStart, $end];
                }
                continue;
            }

            $merged[] = [$start, $end];
        }

        $busyMinutes = 0;
        foreach ($merged as [$start, $end]) {
            $busyMinutes += $start->diffInMinutes($end);
        }

        $elapsedMinutes = $openAt->diffInMinutes($windowEnd);

        return max(0, $elapsedMinutes - $busyMinutes);
    }

    private function isDateClosed(string $dateIso, array $rules): bool
    {
        foreach ($rules as $ruleRaw) {
            $rule = trim((string) $ruleRaw);
            if ($rule === '') {
                continue;
            }

            if (! str_contains($rule, '..')) {
                if ($rule === $dateIso) {
                    return true;
                }
                continue;
            }

            [$from, $to] = array_pad(explode('..', $rule, 2), 2, null);
            $from = trim((string) $from);
            $to = trim((string) $to);

            if ($from !== '' && $to !== '' && $from <= $dateIso && $dateIso <= $to) {
                return true;
            }
        }

        return false;
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
            ->where('status', '!=', 'refunded')
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
