<?php

namespace App\Services\Payments;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\CreditUsage;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\Bonus\BonusService;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

class PaymentService
{
    public function __construct(private readonly BonusService $bonusService)
    {
    }

    public function appointmentOptionsForPatient(int $patientId, int $clinicId, ?int $currentAppointmentId = null): array
    {
        $appointments = Appointment::query()
            ->with(['payments'])
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->where('status', '!=', 'canceled')
            ->where(function ($query) use ($currentAppointmentId) {
                $query->where(function ($subQuery) {
                    $subQuery->whereIn('payment_status', ['pending', 'partially_paid'])
                        ->where(function ($paymentTypeQuery) {
                            $paymentTypeQuery->where('payment_type', '!=', 'bonus')
                                ->orWhereNull('payment_type');
                        })
                        ->whereNull('bonus_id');
                });

                if ($currentAppointmentId) {
                    $query->orWhere('id', (int) $currentAppointmentId);
                }
            })
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
                'notes' => $appointment->notes,
                'status' => $appointment->status,
                'payment_status' => $appointment->payment_status,
                'pending_amount' => $pendingAmount,
                'refunded_amount' => $refundedAmount,
                'debt_amount' => $debtAmount,
                '_include' => ($currentAppointmentId && (int) $appointment->id === (int) $currentAppointmentId)
                    || in_array($appointment->payment_status, ['pending', 'partially_paid'], true),
            ];
        })->filter(function (array $item) {
            return $item['_include'] === true;
        })->map(function (array $item) {
            unset($item['_include']);
            return $item;
        })->values()->toArray();
    }

    public function packageOptionsForPatient(
        int $patientId,
        int $clinicId,
        ?int $currentPackageId = null,
        bool $onlyUnpaid = true
    ): array 
    {
        $bonuses = $this->bonusService->packageCandidatesForPatient(
            $patientId,
            $clinicId,
            $onlyUnpaid,
            $currentPackageId
        );

        return $bonuses->map(function (Bonus $bonus) use ($clinicId, $patientId) {
            $completedAmount = (float) Payment::query()
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->where('package_id', $bonus->id)
                ->where('status', 'completed')
                ->sum('amount');

            $pendingAmount = (float) Payment::query()
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->where('package_id', $bonus->id)
                ->where('status', 'pending')
                ->sum('amount');

            $price = (float) ($bonus->price ?? 0);
            $outstandingAmount = max($price - $completedAmount, 0);

            return [
                'id' => $bonus->id,
                'status' => $bonus->status,
                'price' => $price,
                'bonus_price' => $price,
                'completed_amount' => $completedAmount,
                'pending_amount' => $pendingAmount,
                'outstanding_amount' => $outstandingAmount,
                'name' => $bonus->name,
                'total_sessions' => (int) ($bonus->total_sessions ?? 0),
                'expires_at' => $bonus->expires_at,
            ];
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

        if (!empty($filters['concept']) && in_array($filters['concept'], ['appointment', 'package', 'credit'], true)) {
            $query->where('concept', $filters['concept']);
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
        $data = $this->validatePaymentInput($input);

        $patient = Patient::find($data['patient_id']);
        if (!$patient || (int) $patient->clinic_id !== $clinicId) {
            throw new DomainException('Paciente inválido para esta clínica');
        }

        $appointment = $this->resolveAppointmentForConcept(
            $data['concept'],
            isset($data['appointment_id']) ? (int) $data['appointment_id'] : null,
            $clinicId,
            (int) $patient->id
        );

        $package = $this->resolvePackageForConcept(
            $data['concept'],
            isset($data['package_id']) ? (int) $data['package_id'] : null,
            $clinicId,
            (int) $patient->id
        );

        $payment = Payment::create([
            'patient_id' => (int) $data['patient_id'],
            'concept' => $data['concept'],
            'appointment_id' => $appointment?->id,
            'package_id' => $package?->id,
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
        $data = $this->validatePaymentInput($input);

        $previousAppointmentId = $payment->appointment_id;

        $patient = Patient::find($data['patient_id']);
        if (!$patient || (int) $patient->clinic_id !== $clinicId) {
            throw new DomainException('Paciente inválido para esta clínica');
        }

        $appointment = $this->resolveAppointmentForConcept(
            $data['concept'],
            isset($data['appointment_id']) ? (int) $data['appointment_id'] : null,
            $clinicId,
            (int) $patient->id
        );

        $package = $this->resolvePackageForConcept(
            $data['concept'],
            isset($data['package_id']) ? (int) $data['package_id'] : null,
            $clinicId,
            (int) $patient->id
        );

        $payment->update([
            'patient_id' => (int) $data['patient_id'],
            'concept' => $data['concept'],
            'appointment_id' => $appointment?->id,
            'package_id' => $package?->id,
            'amount' => (float) $data['amount'],
            'method' => $data['method'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['paid_at'] ?? $payment->paid_at ?? now(),
        ]);

        if ($previousAppointmentId && (int) $previousAppointmentId !== (int) ($appointment?->id ?? 0)) {
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

    private function validatePaymentInput(array $input): array
    {
        $validator = Validator::make($input, [
            'patient_id' => 'required|integer|exists:patients,id',
            'concept' => 'required|in:appointment,package,credit',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'package_id' => 'nullable|integer|exists:bonuses,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,card,transfer',
            'status' => 'required|in:completed,pending,refunded',
            'notes' => 'nullable|string',
            'paid_at' => 'nullable|date',
        ]);

        $validator->after(function (ValidationValidator $validator) use ($input) {
            $concept = $input['concept'] ?? null;
            $hasAppointment = !empty($input['appointment_id']);
            $hasPackage = !empty($input['package_id']);

            if ($concept === 'appointment') {
                if (!$hasAppointment) {
                    $validator->errors()->add('appointment_id', 'Debes seleccionar una cita para este tipo de pago.');
                }

                if ($hasPackage) {
                    $validator->errors()->add('package_id', 'Un pago de cita no puede asociarse a un bono.');
                }
            }

            if ($concept === 'package') {
                if (!$hasPackage) {
                    $validator->errors()->add('package_id', 'Debes seleccionar un bono para este tipo de pago.');
                }

                if ($hasAppointment) {
                    $validator->errors()->add('appointment_id', 'Un pago de bono no puede asociarse a una cita.');
                }
            }

            if ($concept === 'credit' && ($hasAppointment || $hasPackage)) {
                $validator->errors()->add('concept', 'Un adelanto no puede asociarse ni a cita ni a bono.');
            }
        });

        return $validator->validate();
    }

    private function resolveAppointmentForConcept(string $concept, ?int $appointmentId, int $clinicId, int $patientId): ?Appointment
    {
        if ($concept !== 'appointment' || !$appointmentId) {
            return null;
        }

        $appointment = Appointment::find($appointmentId);

        if (!$appointment || (int) $appointment->clinic_id !== $clinicId) {
            throw new DomainException('La cita no pertenece a esta clínica');
        }

        if ((int) $appointment->patient_id !== $patientId) {
            throw new DomainException('La cita no pertenece al paciente seleccionado');
        }

        if (!in_array($appointment->payment_status, ['pending', 'partially_paid'], true)) {
            throw new DomainException('Solo se permiten citas impagas o parcialmente pagadas.');
        }

        if ($appointment->payment_type === 'bonus' || $appointment->bonus_id) {
            throw new DomainException('La cita seleccionada ya está asociada a un bono.');
        }

        return $appointment;
    }

    private function resolvePackageForConcept(string $concept, ?int $packageId, int $clinicId, int $patientId): ?Bonus
    {
        if ($concept !== 'package' || !$packageId) {
            return null;
        }

        $package = Bonus::find($packageId);

        if (!$package || (int) $package->clinic_id !== $clinicId) {
            throw new DomainException('El bono no pertenece a esta clínica');
        }

        if ((int) $package->patient_id !== $patientId) {
            throw new DomainException('El bono no pertenece al paciente seleccionado');
        }

        $completedAmount = (float) Payment::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->where('package_id', $package->id)
            ->where('status', 'completed')
            ->sum('amount');

        $pendingAmount = (float) Payment::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->where('package_id', $package->id)
            ->where('status', 'pending')
            ->sum('amount');

        $price = (float) ($package->price ?? 0);
        $outstandingAmount = max($price - $completedAmount, 0);

        if ($outstandingAmount <= 0 && $pendingAmount <= 0) {
            throw new DomainException('Solo se permiten bonos con pago incompleto o parcial.');
        }

        return $package;
    }

    private function mapPaginatorItems(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()->transform(function (Payment $payment) {
            return $this->mapPayment($payment);
        })->toArray();
    }

    private function mapPayment(Payment $payment): array
    {
        $creditUsedAmount = (float) CreditUsage::query()
            ->whereNull('reversed_at')
            ->where('payment_id', $payment->id)
            ->where('patient_id', $payment->patient_id)
            ->sum('amount');

        $creditPendingAmount = max(((float) $payment->amount) - $creditUsedAmount, 0);

        return [
            'id' => $payment->id,
            'patient_id' => $payment->patient_id,
            'concept' => $payment->concept,
            'appointment_id' => $payment->appointment_id,
            'package_id' => $payment->package_id,
            'amount' => (float) $payment->amount,
            'credit_used_amount' => $creditUsedAmount,
            'credit_pending_amount' => $creditPendingAmount,
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
