<?php

namespace App\Services\Payments;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class PaymentService
{
    public function appointmentOptionsForPatient(int $patientId, int $clinicId, ?int $currentAppointmentId = null): array
    {
        $appointments = Appointment::query()
            ->with(['payments'])
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->where('status', '!=', 'canceled')
            ->orderByDesc('start_time')
            ->get();

        return $appointments->map(function (Appointment $appointment) use ($currentAppointmentId) {
            $pendingAmount = (float) $appointment->payments
                ->where('status', 'pending')
                ->sum('amount');

            $refundedAmount = (float) $appointment->payments
                ->where('status', 'refunded')
                ->sum('amount');

            $debtAmount = $pendingAmount + $refundedAmount;

            return [
                'id' => $appointment->id,
                'start_time' => $appointment->start_time,
                'end_time' => $appointment->end_time,
                'status' => $appointment->status,
                'payment_status' => $appointment->payment_status,
                'pending_amount' => $pendingAmount,
                'refunded_amount' => $refundedAmount,
                'debt_amount' => $debtAmount,
                '_include' => $debtAmount > 0 || ($currentAppointmentId && (int) $appointment->id === (int) $currentAppointmentId),
            ];
        })->filter(function (array $item) {
            return $item['_include'] === true;
        })->map(function (array $item) {
            unset($item['_include']);
            return $item;
        })->values()->toArray();
    }

    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));
        $q = trim((string) ($filters['q'] ?? ''));

        $query = Payment::with(['patient']);

        if (!empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['completed', 'pending', 'refunded'], true)) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['method']) && in_array($filters['method'], ['cash', 'card', 'transfer'], true)) {
            $query->where('method', $filters['method']);
        }

        if ($q !== '') {
            $like = '%' . strtolower($q) . '%';
            $query->whereHas('patient', function ($sub) use ($like) {
                $sub->whereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", [$like])
                    ->orWhereRaw('LOWER(nif) LIKE ?', [$like]);
            });
        }

        $summaryQuery = clone $query;

        $summary = [
            'count' => (int) $summaryQuery->count(),
            'total_amount' => (float) $summaryQuery->sum('amount'),
        ];

        $paginator = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => $this->mapPaginatorItems($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => $summary,
        ];
    }

    public function store(array $input, int $clinicId): array
    {
        $data = Validator::make($input, [
            'patient_id' => 'required|integer|exists:patients,id',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'pack_id' => 'nullable|integer|exists:packs,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,card,transfer',
            'status' => 'required|in:completed,pending,refunded',
            'notes' => 'nullable|string',
            'paid_at' => 'nullable|date',
        ])->validate();

        $patient = Patient::find($data['patient_id']);
        if (!$patient || (int) $patient->clinic_id !== $clinicId) {
            throw new DomainException('Paciente inválido para esta clínica');
        }

        $appointment = null;
        if (!empty($data['appointment_id'])) {
            $appointment = Appointment::find((int) $data['appointment_id']);

            if (!$appointment || (int) $appointment->clinic_id !== $clinicId) {
                throw new DomainException('La cita no pertenece a esta clínica');
            }

            if ((int) $appointment->patient_id !== (int) $patient->id) {
                throw new DomainException('La cita no pertenece al paciente seleccionado');
            }
        }

        $payment = Payment::create([
            'patient_id' => (int) $data['patient_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
            'pack_id' => $data['pack_id'] ?? null,
            'amount' => (float) $data['amount'],
            'method' => $data['method'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
        ]);

        if ($appointment) {
            $this->syncAppointmentPaymentStatus($appointment);
        }

        return [
            'status' => 201,
            'payload' => $this->mapPayment($payment->load('patient')),
        ];
    }

    public function show(Payment $payment): array
    {
        return $this->mapPayment($payment->load('patient'));
    }

    public function update(Payment $payment, array $input, int $clinicId): array
    {
        $data = Validator::make($input, [
            'patient_id' => 'required|integer|exists:patients,id',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'pack_id' => 'nullable|integer|exists:packs,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,card,transfer',
            'status' => 'required|in:completed,pending,refunded',
            'notes' => 'nullable|string',
            'paid_at' => 'nullable|date',
        ])->validate();

        $previousAppointmentId = $payment->appointment_id;

        $patient = Patient::find($data['patient_id']);
        if (!$patient || (int) $patient->clinic_id !== $clinicId) {
            throw new DomainException('Paciente inválido para esta clínica');
        }

        $appointment = null;
        if (!empty($data['appointment_id'])) {
            $appointment = Appointment::find((int) $data['appointment_id']);

            if (!$appointment || (int) $appointment->clinic_id !== $clinicId) {
                throw new DomainException('La cita no pertenece a esta clínica');
            }

            if ((int) $appointment->patient_id !== (int) $patient->id) {
                throw new DomainException('La cita no pertenece al paciente seleccionado');
            }
        }

        $payment->update([
            'patient_id' => (int) $data['patient_id'],
            'appointment_id' => $data['appointment_id'] ?? null,
            'pack_id' => $data['pack_id'] ?? null,
            'amount' => (float) $data['amount'],
            'method' => $data['method'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['paid_at'] ?? $payment->paid_at ?? now(),
        ]);

        if ($previousAppointmentId && (int) $previousAppointmentId !== (int) ($data['appointment_id'] ?? 0)) {
            $previousAppointment = Appointment::find((int) $previousAppointmentId);
            if ($previousAppointment) {
                $this->syncAppointmentPaymentStatus($previousAppointment);
            }
        }

        if ($appointment) {
            $this->syncAppointmentPaymentStatus($appointment);
        }

        return [
            'status' => 200,
            'payload' => $this->mapPayment($payment->load('patient')),
        ];
    }

    private function mapPaginatorItems(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()->transform(function (Payment $payment) {
            return $this->mapPayment($payment);
        })->toArray();
    }

    private function mapPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'patient_id' => $payment->patient_id,
            'appointment_id' => $payment->appointment_id,
            'pack_id' => $payment->pack_id,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'status' => $payment->status,
            'notes' => $payment->notes,
            'paid_at' => $payment->paid_at,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
            'patient' => $payment->patient ? [
                'id' => $payment->patient->id,
                'name' => $payment->patient->name,
                'nif' => $payment->patient->nif,
            ] : null,
        ];
    }

    private function syncAppointmentPaymentStatus(Appointment $appointment): void
    {
        if ($appointment->payment_type === 'bonus' || $appointment->bonus_id) {
            $appointment->update(['payment_status' => 'covered_by_pack']);
            return;
        }

        $completedTotal = (float) $appointment->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $hasPending = $appointment->payments()
            ->where('status', 'pending')
            ->exists();

        if ($completedTotal <= 0.0) {
            $appointment->update(['payment_status' => 'pending']);
            return;
        }

        if ($hasPending) {
            $appointment->update(['payment_status' => 'partially_paid']);
            return;
        }

        $appointment->update(['payment_status' => 'paid']);
    }
}
