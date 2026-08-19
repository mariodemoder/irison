<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use App\Models\Appointment;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Application\DTOs\ReportFilterData;
use Modules\Finance\Infrastructure\Persistence\ExpenseEloquentModel;
use Modules\Finance\Infrastructure\Persistence\ProfessionalRateEloquentModel;

class GenerateFinanceReportQuery
{
    public function execute(int $clinicId, ReportFilterData $filter): array
    {
        $from = $filter->fromDate ? Carbon::parse($filter->fromDate) : null;
        $to = $filter->toDate ? Carbon::parse($filter->toDate) : null;

        return match ($filter->type) {
            'income' => $this->incomeReport($clinicId, $from, $to, $filter->groupBy),
            'expenses' => $this->expensesReport($clinicId, $from, $to, $filter->groupBy),
            'profit' => $this->profitReport($clinicId, $from, $to, $filter->groupBy),
            'professional' => $this->professionalReport($clinicId, $from, $to),
            'service' => $this->serviceReport($clinicId, $from, $to),
        };
    }

    /**
     * Detalle de ingresos por fecha/concepto.
     *
     * @return array{type:string, period:array, headers:list<string>, rows:list<list>, summary:array}
     */
    private function incomeReport(int $clinicId, ?Carbon $from, ?Carbon $to, string $groupBy): array
    {
        // Invoices — positive income
        $invoices = Document::query()
            ->where('clinic_id', $clinicId)
            ->where('type', Document::TYPE_INVOICE)
            ->when($from, fn ($q) => $q->where('date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->format('Y-m-d')))
            ->get(['date', 'amount']);

        // Abonos — negative
        $abonos = Document::query()
            ->where('clinic_id', $clinicId)
            ->where('type', Document::TYPE_ABONO)
            ->when($from, fn ($q) => $q->where('date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->format('Y-m-d')))
            ->get(['date', 'amount']);

        // Manual income (Payment concept=other, status=completed)
        $manualIncome = Payment::query()
            ->where('clinic_id', $clinicId)
            ->where('concept', 'other')
            ->where('status', 'completed')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->get(['paid_at', 'amount']);

        // PHP-level grouping
        $grouped = [];

        foreach ($invoices as $row) {
            $key = $this->groupKey($row->date, $groupBy);
            $grouped[$key]['invoices'] = ($grouped[$key]['invoices'] ?? 0.0) + (float) $row->amount;
        }
        foreach ($abonos as $row) {
            $key = $this->groupKey($row->date, $groupBy);
            $grouped[$key]['abonos'] = ($grouped[$key]['abonos'] ?? 0.0) + (float) $row->amount;
        }
        foreach ($manualIncome as $row) {
            $key = $this->groupKey($row->paid_at, $groupBy);
            $grouped[$key]['manual'] = ($grouped[$key]['manual'] ?? 0.0) + (float) $row->amount;
        }

        ksort($grouped);

        $rows = [];
        $totalIncome = 0.0;

        foreach ($grouped as $key => $values) {
            $invoiceTotal = round($values['invoices'] ?? 0, 2);
            $abonoTotal = round($values['abonos'] ?? 0, 2);
            $manualTotal = round($values['manual'] ?? 0, 2);
            $net = round($invoiceTotal - $abonoTotal + $manualTotal, 2);
            $totalIncome += $net;

            $rows[] = [$this->formatGroupLabel($key, $groupBy), $invoiceTotal, $abonoTotal, $manualTotal, $net];
        }

        return [
            'type' => 'income',
            'period' => ['from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d')],
            'headers' => ['Período', 'Facturado', 'Abonos', 'Ingresos manuales', 'Neto'],
            'rows' => $rows,
            'summary' => ['total' => round($totalIncome, 2), 'count' => count($rows)],
        ];
    }

    /**
     * Detalle de gastos por fecha.
     *
     * @return array{type:string, period:array, headers:list<string>, rows:list<list>, summary:array}
     */
    private function expensesReport(int $clinicId, ?Carbon $from, ?Carbon $to, string $groupBy): array
    {
        $data = ExpenseEloquentModel::query()
            ->where('expenses.clinic_id', $clinicId)
            ->when($from, fn ($q) => $q->where('expenses.date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('expenses.date', '<=', $to->format('Y-m-d')))
            ->get(['date', 'amount', 'total']);

        $grouped = [];
        foreach ($data as $row) {
            $key = $this->groupKey($row->date, $groupBy);
            if (! isset($grouped[$key])) {
                $grouped[$key] = ['subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0, 'count' => 0];
            }
            $grouped[$key]['subtotal'] += (float) $row->amount;
            $grouped[$key]['tax'] += (float) $row->total - (float) $row->amount;
            $grouped[$key]['total'] += (float) $row->total;
            $grouped[$key]['count']++;
        }

        ksort($grouped);

        $rows = [];
        $total = 0.0;

        foreach ($grouped as $key => $values) {
            $total += $values['total'];
            $rows[] = [
                $this->formatGroupLabel($key, $groupBy),
                round($values['subtotal'], 2),
                round($values['tax'], 2),
                round($values['total'], 2),
                $values['count'],
            ];
        }

        return [
            'type' => 'expenses',
            'period' => ['from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d')],
            'headers' => ['Período', 'Base imponible', 'IVA', 'Total', 'Nº gastos'],
            'rows' => $rows,
            'summary' => ['total' => round($total, 2), 'count' => count($data)],
        ];
    }

    /**
     * Beneficio por período = ingresos - gastos.
     *
     * @return array{type:string, period:array, headers:list<string>, rows:list<list>, summary:array}
     */
    private function profitReport(int $clinicId, ?Carbon $from, ?Carbon $to, string $groupBy): array
    {
        $invoices = Document::query()
            ->where('clinic_id', $clinicId)
            ->where('type', Document::TYPE_INVOICE)
            ->when($from, fn ($q) => $q->where('date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->format('Y-m-d')))
            ->get(['date', 'amount']);

        $abonos = Document::query()
            ->where('clinic_id', $clinicId)
            ->where('type', Document::TYPE_ABONO)
            ->when($from, fn ($q) => $q->where('date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->format('Y-m-d')))
            ->get(['date', 'amount']);

        $expenses = ExpenseEloquentModel::query()
            ->where('clinic_id', $clinicId)
            ->when($from, fn ($q) => $q->where('date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->format('Y-m-d')))
            ->get(['date', 'total']);

        $revenueByGroup = [];
        $expenseByGroup = [];

        foreach ($invoices as $row) {
            $key = $this->groupKey($row->date, $groupBy);
            $revenueByGroup[$key] = ($revenueByGroup[$key] ?? 0.0) + (float) $row->amount;
        }
        foreach ($abonos as $row) {
            $key = $this->groupKey($row->date, $groupBy);
            $revenueByGroup[$key] = ($revenueByGroup[$key] ?? 0.0) - (float) $row->amount;
        }
        foreach ($expenses as $row) {
            $key = $this->groupKey($row->date, $groupBy);
            $expenseByGroup[$key] = ($expenseByGroup[$key] ?? 0.0) + (float) $row->total;
        }

        $allGroups = array_unique(array_merge(array_keys($revenueByGroup), array_keys($expenseByGroup)));
        sort($allGroups);

        $rows = [];
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($allGroups as $groupKey) {
            $revenue = round($revenueByGroup[$groupKey] ?? 0, 2);
            $expenseTotal = round($expenseByGroup[$groupKey] ?? 0, 2);
            $profit = round($revenue - $expenseTotal, 2);
            $totalRevenue += $revenue;
            $totalExpenses += $expenseTotal;

            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : null;

            $rows[] = [
                $this->formatGroupLabel($groupKey, $groupBy),
                $revenue,
                $expenseTotal,
                $profit,
                $margin !== null ? $margin . '%' : '—',
            ];
        }

        $totalProfit = round($totalRevenue - $totalExpenses, 2);
        $marginPct = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : null;

        return [
            'type' => 'profit',
            'period' => ['from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d')],
            'headers' => ['Período', 'Ingresos', 'Gastos', 'Beneficio', 'Margen %'],
            'rows' => $rows,
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_expenses' => round($totalExpenses, 2),
                'total_profit' => $totalProfit,
                'margin_percentage' => $marginPct,
            ],
        ];
    }

    /**
     * Ingresos y coste laboral por profesional.
     *
     * @return array{type:string, period:array, headers:list<string>, rows:list<list>, summary:array}
     */
    private function professionalReport(int $clinicId, ?Carbon $from, ?Carbon $to): array
    {
        $rates = ProfessionalRateEloquentModel::where('clinic_id', $clinicId)
            ->pluck('cost_per_hour', 'user_id');

        $users = User::where('clinic_id', $clinicId)->pluck('name', 'id');

        $appointments = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('status', '!=', 'canceled')
            ->whereNotNull('professional_id')
            ->when($from, fn ($q) => $q->where('start_time', '>=', $from))
            ->when($to, fn ($q) => $q->where('start_time', '<=', $to))
            ->get(['professional_id', 'start_time', 'end_time', 'price']);

        $aggregate = [];
        foreach ($appointments as $row) {
            $pid = (int) $row->professional_id;
            if (! isset($aggregate[$pid])) {
                $aggregate[$pid] = ['revenue' => 0.0, 'labor_cost' => 0.0, 'appointments' => 0];
            }
            $aggregate[$pid]['revenue'] += (float) $row->price;
            $aggregate[$pid]['appointments']++;

            $rate = (float) ($rates[$pid] ?? 0);
            if ($rate > 0 && $row->end_time && $row->start_time) {
                $minutes = max((int) $row->start_time->diffInMinutes($row->end_time), 0);
                $aggregate[$pid]['labor_cost'] += ($rate / 60) * $minutes;
            }
        }

        // Add manual income per professional
        $manualPayments = Payment::query()
            ->where('clinic_id', $clinicId)
            ->where('concept', 'other')
            ->where('status', 'completed')
            ->whereNotNull('professional_id')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->get(['professional_id', 'amount']);

        foreach ($manualPayments as $payment) {
            $pid = (int) $payment->professional_id;
            if (! isset($aggregate[$pid])) {
                $aggregate[$pid] = ['revenue' => 0.0, 'labor_cost' => 0.0, 'appointments' => 0];
            }
            $aggregate[$pid]['revenue'] += (float) $payment->amount;
        }

        $rows = [];
        $totalRevenue = 0.0;
        $totalLabor = 0.0;

        foreach ($aggregate as $pid => $values) {
            $revenue = round($values['revenue'], 2);
            $labor = round($values['labor_cost'], 2);
            $contribution = round($revenue - $labor, 2);
            $totalRevenue += $revenue;
            $totalLabor += $labor;

            $rows[] = [
                $users[$pid] ?? 'Profesional',
                $values['appointments'],
                $revenue,
                $labor,
                $contribution,
            ];
        }

        usort($rows, fn ($a, $b) => $b[2] <=> $a[2]);

        return [
            'type' => 'professional',
            'period' => ['from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d')],
            'headers' => ['Profesional', 'Nº citas', 'Ingresos', 'Coste laboral', 'Contribución'],
            'rows' => $rows,
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_labor' => round($totalLabor, 2),
                'total_contribution' => round($totalRevenue - $totalLabor, 2),
            ],
        ];
    }

    /**
     * Ingresos por tipo de servicio.
     *
     * @return array{type:string, period:array, headers:list<string>, rows:list<list>, summary:array}
     */
    private function serviceReport(int $clinicId, ?Carbon $from, ?Carbon $to): array
    {
        $data = Appointment::query()
            ->where('appointments.clinic_id', $clinicId)
            ->where('appointments.status', '!=', 'canceled')
            ->when($from, fn ($q) => $q->where('appointments.start_time', '>=', $from))
            ->when($to, fn ($q) => $q->where('appointments.start_time', '<=', $to))
            ->leftJoin('appointment_types', 'appointment_types.id', '=', 'appointments.app_type_id')
            ->select(
                DB::raw('COALESCE(NULLIF(appointment_types.description, \'\'), \'Sin servicio\') as name'),
                DB::raw('COUNT(*) as count'),
                DB::raw('ROUND(COALESCE(SUM(appointments.price), 0), 2) as revenue'),
            )
            ->groupBy('appointments.app_type_id', 'name')
            ->orderByDesc('revenue')
            ->get();

        $rows = [];
        $totalRevenue = 0.0;
        $totalCount = 0;

        foreach ($data as $row) {
            $revenue = (float) $row->revenue;
            $count = (int) $row->count;
            $totalRevenue += $revenue;
            $totalCount += $count;
            $avg = $count > 0 ? round($revenue / $count, 2) : 0.0;

            $rows[] = [$row->name, $count, $revenue, $avg];
        }

        return [
            'type' => 'service',
            'period' => ['from' => $from?->format('Y-m-d'), 'to' => $to?->format('Y-m-d')],
            'headers' => ['Servicio', 'Nº citas', 'Ingresos', 'Ticket medio'],
            'rows' => $rows,
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_count' => $totalCount,
                'avg_ticket' => $totalCount > 0 ? round($totalRevenue / $totalCount, 2) : 0.0,
            ],
        ];
    }

    /**
     * PHP-level grouping key from a date.
     */
    private function groupKey($date, string $groupBy): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return match ($groupBy) {
            'week' => $carbon->startOfWeek()->format('Y-m-d'),
            'month' => $carbon->format('Y-m'),
            default => $carbon->format('Y-m-d'),
        };
    }

    private function formatGroupLabel(string $key, string $groupBy): string
    {
        if ($key === '' || $key === 'Sin fecha') {
            return 'Sin fecha';
        }

        return match ($groupBy) {
            'week' => 'Sem ' . Carbon::parse($key)->format('d/m/Y'),
            'month' => Carbon::parse($key . '-01')->format('m/Y'),
            default => Carbon::parse($key)->format('d/m/Y'),
        };
    }
}
