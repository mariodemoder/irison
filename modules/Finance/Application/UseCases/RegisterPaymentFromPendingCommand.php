<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Appointments\AppointmentPendingPaymentService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class RegisterPaymentFromPendingCommand
{
    public function __construct(
        private readonly AppointmentPendingPaymentService $pendingPaymentService,
    ) {}

    public function execute(int $clinicId, int $appointmentId, array $validated): array
    {
        $appointment = Appointment::where('clinic_id', $clinicId)
            ->where('id', $appointmentId)
            ->first();

        if (! $appointment) {
            throw ValidationException::withMessages([
                'appointment' => ['La cita no existe o no pertenece a esta clínica.'],
            ]);
        }

        if ($appointment->status === 'canceled') {
            throw ValidationException::withMessages([
                'appointment' => ['No se puede registrar un pago para una cita cancelada.'],
            ]);
        }

        $pendingAmount = $this->pendingPaymentService->calculatePendingAmount($appointment);

        if ($pendingAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Esta cita no tiene saldo pendiente.'],
            ]);
        }

        $amount = round((float) $validated['amount'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['El importe debe ser mayor que cero.'],
            ]);
        }

        if ($amount > $pendingAmount) {
            throw ValidationException::withMessages([
                'amount' => ['El importe no puede superar el saldo pendiente de ' . number_format($pendingAmount, 2, '.', '') . ' €.'],
            ]);
        }

        $payment = Payment::create([
            'clinic_id' => $clinicId,
            'patient_id' => $appointment->patient_id,
            'professional_id' => $appointment->professional_id,
            'appointment_id' => $appointment->id,
            'concept' => 'appointment',
            'amount' => $amount,
            'method' => $validated['method'],
            'status' => 'completed',
            'paid_at' => Carbon::now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->pendingPaymentService->syncPaymentStatus($appointment->fresh());

        return [
            'id' => $payment->id,
            'clinic_id' => $payment->clinic_id,
            'patient_id' => $payment->patient_id,
            'professional_id' => $payment->professional_id,
            'appointment_id' => $payment->appointment_id,
            'concept' => $payment->concept,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'notes' => $payment->notes,
        ];
    }
}
