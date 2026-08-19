<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Persistence;

use App\Models\Appointment;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Domain\Contracts\BenefitsDataProviderInterface;

class BenefitsDataProvider implements BenefitsDataProviderInterface
{
    public function revenueOnPeriod(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): float
    {
        $invoices = Document::query()
            ->where('clinic_id', $clinicId)
            ->where('type', Document::TYPE_INVOICE)
            ->when($from, fn ($q) => $q->where('date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->format('Y-m-d')))
            ->sum('amount');

        $abonos = Document::query()
            ->where('clinic_id', $clinicId)
            ->where('type', Document::TYPE_ABONO)
            ->when($from, fn ($q) => $q->where('date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->format('Y-m-d')))
            ->sum('amount');

        $manualIncome = (float) Payment::query()
            ->where('clinic_id', $clinicId)
            ->where('concept', 'other')
            ->where('status', 'completed')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->sum('amount');

        return round((float) $invoices - (float) $abonos + $manualIncome, 2);
    }

    public function expensesTotalOnPeriod(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): float
    {
        return round((float) ExpenseEloquentModel::query()
            ->where('clinic_id', $clinicId)
            ->when($from, fn ($q) => $q->where('date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->format('Y-m-d')))
            ->sum('total'), 2);
    }

    public function laborCostOnPeriod(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): float
    {
        $rates = ProfessionalRateEloquentModel::where('clinic_id', $clinicId)
            ->pluck('cost_per_hour', 'user_id');

        $appointments = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->whereNotNull('professional_id')
            ->where('status', '!=', 'canceled')
            ->when($from, fn ($q) => $q->where('start_time', '>=', $from))
            ->when($to, fn ($q) => $q->where('start_time', '<=', $to))
            ->get(['professional_id', 'start_time', 'end_time']);

        $total = 0.0;

        foreach ($appointments as $appointment) {
            $rate = (float) ($rates[$appointment->professional_id] ?? 0);
            if ($rate <= 0 || ! $appointment->end_time || ! $appointment->start_time) {
                continue;
            }

            $minutes = max((int) $appointment->start_time->diffInMinutes($appointment->end_time), 0);
            $total += ($rate / 60) * $minutes;
        }

        return round($total, 2);
    }

    public function revenueByAppointmentType(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return Appointment::query()
            ->where('appointments.clinic_id', $clinicId)
            ->where('appointments.status', '!=', 'canceled')
            ->whereNotNull('appointments.app_type_id')
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
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'count' => (int) $row->count,
                'revenue' => (float) $row->revenue,
            ])
            ->values()
            ->all();
    }

    public function revenueAndLaborByProfessional(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $rates = ProfessionalRateEloquentModel::where('clinic_id', $clinicId)
            ->pluck('cost_per_hour', 'user_id');

        $rows = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('status', '!=', 'canceled')
            ->whereNotNull('professional_id')
            ->when($from, fn ($q) => $q->where('start_time', '>=', $from))
            ->when($to, fn ($q) => $q->where('start_time', '<=', $to))
            ->get(['professional_id', 'start_time', 'end_time', 'price']);

        $users = User::where('clinic_id', $clinicId)->pluck('name', 'id');

        $aggregate = [];

        foreach ($rows as $row) {
            $professionalId = (int) $row->professional_id;

            if (! isset($aggregate[$professionalId])) {
                $aggregate[$professionalId] = [
                    'revenue' => 0.0,
                    'labor_cost' => 0.0,
                ];
            }

            $aggregate[$professionalId]['revenue'] += (float) $row->price;

            $rate = (float) ($rates[$professionalId] ?? 0);
            if ($rate > 0 && $row->end_time && $row->start_time) {
                $minutes = max((int) $row->start_time->diffInMinutes($row->end_time), 0);
                $aggregate[$professionalId]['labor_cost'] += ($rate / 60) * $minutes;
            }
        }

        $otherPayments = Payment::query()
            ->where('clinic_id', $clinicId)
            ->where('concept', 'other')
            ->where('status', '!=', 'refunded')
            ->whereNotNull('professional_id')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->get(['professional_id', 'amount']);

        foreach ($otherPayments as $payment) {
            $professionalId = (int) $payment->professional_id;

            if (! isset($aggregate[$professionalId])) {
                $aggregate[$professionalId] = [
                    'revenue' => 0.0,
                    'labor_cost' => 0.0,
                ];
            }

            $aggregate[$professionalId]['revenue'] += (float) $payment->amount;
        }

        $result = [];

        foreach ($aggregate as $professionalId => $values) {
            $revenue = round($values['revenue'], 2);
            $laborCost = round($values['labor_cost'], 2);

            $result[] = [
                'user_id' => $professionalId,
                'user_name' => (string) ($users[$professionalId] ?? 'Profesional'),
                'revenue' => $revenue,
                'labor_cost' => $laborCost,
                'contribution' => round($revenue - $laborCost, 2),
            ];
        }

        usort($result, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $result;
    }

    public function expensesByCategory(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return ExpenseEloquentModel::query()
            ->where('expenses.clinic_id', $clinicId)
            ->when($from, fn ($q) => $q->where('expenses.date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('expenses.date', '<=', $to->format('Y-m-d')))
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->select(
                DB::raw('COALESCE(expense_categories.name, \'Sin categoría\') as name'),
                DB::raw('ROUND(COALESCE(SUM(expenses.total), 0), 2) as total'),
            )
            ->groupBy('expenses.category_id', 'name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'total' => (float) $row->total,
            ])
            ->values()
            ->all();
    }

    public function paidOperationsCount(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): int
    {
        return (int) Payment::query()
            ->where('clinic_id', $clinicId)
            ->where('status', 'completed')
            ->where('concept', '!=', 'refund')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->count();
    }

    public function revenueByPaymentMethod(int $clinicId, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $methodLabels = [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
        ];

        $rows = Payment::query()
            ->where('clinic_id', $clinicId)
            ->where('status', 'completed')
            ->where('concept', '!=', 'refund')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->select('method', DB::raw('COUNT(*) as count'), DB::raw('ROUND(COALESCE(SUM(amount), 0), 2) as total'))
            ->groupBy('method')
            ->get();

        $revenue = (float) $rows->sum('total');

        return $rows->map(function ($row) use ($methodLabels, $revenue) {
            $total = (float) $row->total;

            return [
                'method' => (string) $row->method,
                'label' => $methodLabels[$row->method] ?? $row->method,
                'count' => (int) $row->count,
                'total' => $total,
                'percentage' => $revenue > 0 ? round(($total / $revenue) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    public function pendingPaymentsCount(int $clinicId): int
    {
        return (int) Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('status', '!=', 'canceled')
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->count();
    }

    public function pendingPaymentsAmount(int $clinicId): float
    {
        $appointments = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('status', '!=', 'canceled')
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->get(['id', 'price']);

        $total = 0.0;

        foreach ($appointments as $appointment) {
            $paid = (float) Payment::where('appointment_id', $appointment->id)
                ->where('status', 'completed')
                ->sum('amount');
            $total += max((float) $appointment->price - $paid, 0);
        }

        return round($total, 2);
    }

    public function revenueEvolution(int $clinicId, int $months = 12): array
    {
        $evolution = [];
        $now = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $now->copy()->subMonths($i)->endOfMonth();

            $revenue = $this->revenueOnPeriod($clinicId, $monthStart, $monthEnd);
            $expenses = $this->expensesTotalOnPeriod($clinicId, $monthStart, $monthEnd);

            $evolution[] = [
                'month' => $monthStart->format('Y-m'),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'profit' => round($revenue - $expenses, 2),
            ];
        }

        return $evolution;
    }
}