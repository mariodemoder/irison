<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Application\DTOs\IncomeData;

class ListIncomePaymentsQuery
{
    public function execute(int $clinicId, array $filters = []): LengthAwarePaginator
    {
        $query = Payment::query()
            ->where('payments.clinic_id', $clinicId)
            ->whereIn('payments.status', ['completed', 'refunded'])
            ->leftJoin('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->leftJoin('patients', 'patients.id', '=', 'payments.patient_id')
            ->leftJoin('users as professionals', function ($join) {
                $join->on('professionals.id', '=', 'payments.professional_id');
            })
            ->select(
                'payments.*',
                DB::raw("COALESCE(NULLIF(patients.first_name, '') || ' ' || NULLIF(patients.last_name, ''), NULLIF(patients.first_name, ''), NULLIF(patients.last_name, ''), 'Sin nombre') as patient_name_resolved"),
                DB::raw("COALESCE(NULLIF(professionals.name, ''), 'Sin profesional') as professional_name_resolved"),
                'appointments.invoice_id',
            );

        if (! empty($filters['professional_id'])) {
            $query->where('payments.professional_id', (int) $filters['professional_id']);
        }

        if (! empty($filters['method'])) {
            $query->where('payments.method', $filters['method']);
        }

        if (! empty($filters['concept'])) {
            $query->where('payments.concept', $filters['concept']);
        }

        if (! empty($filters['from_date'])) {
            $query->where('payments.paid_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('payments.paid_at', '<=', $filters['to_date'] . ' 23:59:59');
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        $paginator = $query->orderByDesc('payments.paid_at')
            ->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($row) {
            return new IncomeData(
                id: (int) $row->id,
                patientName: $row->patient_name_resolved ?: null,
                professionalName: $row->professional_name_resolved ?: null,
                concept: (string) $row->concept,
                amount: (float) $row->amount,
                method: (string) $row->method,
                status: (string) $row->status,
                paidAt: $row->paid_at?->format('Y-m-d H:i:s'),
                refundReason: $row->refund_reason,
                refundedAt: $row->refunded_at?->format('Y-m-d H:i:s'),
                appointmentId: $row->appointment_id ? (int) $row->appointment_id : null,
                invoiceId: $row->invoice_id ? (int) $row->invoice_id : null,
            );
        });

        return new LengthAwarePaginator(
            $items->map->toArray()->all(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => request()->url()],
        );
    }
}
