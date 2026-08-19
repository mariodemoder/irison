<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\AppointmentType;
use App\Services\Appointments\AppointmentPendingPaymentService;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Application\DTOs\PendingPaymentData;

class ListPendingPaymentsQuery
{
    public function __construct(
        private readonly AppointmentPendingPaymentService $pendingPaymentService,
    ) {}

    public function execute(int $clinicId, array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        $query = Appointment::query()
            ->where('appointments.clinic_id', $clinicId)
            ->whereIn('appointments.payment_status', ['pending', 'partially_paid'])
            ->where('appointments.status', '!=', 'canceled')
            ->leftJoin('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoin('users', 'users.id', '=', 'appointments.professional_id')
            ->leftJoin('appointment_types', 'appointment_types.id', '=', 'appointments.app_type_id')
            ->select(
                'appointments.id as appointment_id',
                DB::raw("TRIM(COALESCE(NULLIF(patients.first_name, ''), '') || ' ' || COALESCE(NULLIF(patients.last_name, ''), '')) as patient_name"),
                DB::raw("COALESCE(NULLIF(users.name, ''), 'Sin profesional') as professional_name"),
                'appointments.start_time as appointment_date',
                DB::raw("COALESCE(NULLIF(appointment_types.description, ''), 'Sin servicio') as service_name"),
                'appointments.price',
                'appointments.payment_status',
            );

        if (! empty($filters['patient_id'])) {
            $query->where('appointments.patient_id', (int) $filters['patient_id']);
        }

        if (! empty($filters['professional_id'])) {
            $query->where('appointments.professional_id', (int) $filters['professional_id']);
        }

        if (! empty($filters['from_date'])) {
            $query->where('appointments.start_time', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('appointments.start_time', '<=', $filters['to_date'] . ' 23:59:59');
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        $rows = $query->orderByDesc('appointments.start_time')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $data = [];
        $totalPending = 0.0;

        foreach ($rows as $row) {
            $appointment = Appointment::withoutGlobalScopes()->find($row->appointment_id);

            if (! $appointment) {
                continue;
            }

            $pendingAmount = $this->pendingPaymentService->calculatePendingAmount($appointment);
            $price = (float) ($row->price ?? 0);
            $paidAmount = max($price - $pendingAmount, 0.0);

            $dto = new PendingPaymentData(
                appointmentId: (int) $row->appointment_id,
                patientName: trim((string) $row->patient_name),
                professionalName: (string) $row->professional_name,
                appointmentDate: $row->appointment_date instanceof \Carbon\Carbon
                    ? $row->appointment_date->format('Y-m-d H:i')
                    : (string) $row->appointment_date,
                serviceName: (string) $row->service_name,
                price: $price,
                paidAmount: round($paidAmount, 2),
                pendingAmount: round($pendingAmount, 2),
                paymentStatus: (string) $row->payment_status,
            );

            $data[] = $dto->toArray();
            $totalPending += $pendingAmount;
        }

        $lastPage = (int) ceil($total / $perPage);

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'summary' => [
                'count' => $total,
                'total_pending_amount' => round($totalPending, 2),
            ],
        ];
    }
}
