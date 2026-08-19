<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use App\Models\Payment;
use App\Services\Documents\InvoicingService;
use App\Services\Appointments\AppointmentPendingPaymentService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\DTOs\IncomeData;

class RefundPaymentCommand
{
    public function __construct(
        private readonly AppointmentPendingPaymentService $pendingPaymentService,
        private readonly InvoicingService $invoicingService,
    ) {}

    public function execute(int $clinicId, int $paymentId, array $validated): array
    {
        $payment = Payment::where('id', $paymentId)
            ->where('clinic_id', $clinicId)
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment' => ['El pago no existe o no pertenece a esta clínica.'],
            ]);
        }

        if ($payment->status === 'refunded') {
            throw ValidationException::withMessages([
                'payment' => ['Este pago ya ha sido reembolsado.'],
            ]);
        }

        $amount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : (float) $payment->amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['El importe debe ser mayor que cero.'],
            ]);
        }

        if ($amount > (float) $payment->amount) {
            throw ValidationException::withMessages([
                'amount' => ['El importe no puede superar el total del pago de ' . number_format((float) $payment->amount, 2, '.', '') . ' €.'],
            ]);
        }

        $payment->update([
            'status' => 'refunded',
            'refund_reason' => $validated['reason'],
            'refunded_at' => Carbon::now(),
        ]);

        if ($payment->appointment_id) {
            $appointment = $payment->appointment;
            if ($appointment) {
                $this->pendingPaymentService->syncPaymentStatus($appointment->fresh());
            }
        }

        $abonoResult = ['created' => false];

        if (! empty($validated['generate_abono'])) {
            if ($payment->appointment_id) {
                $abonoResult = $this->tryCreateAbono($payment);
            } else {
                $abonoResult = [
                    'created' => false,
                    'reason' => 'no_invoice',
                ];
            }
        }

        $payment->refresh();

        return [
            'payment' => new IncomeData(
                id: (int) $payment->id,
                patientName: $payment->patient?->name ?? null,
                professionalName: $payment->professional?->name ?? null,
                concept: $payment->concept,
                amount: (float) $payment->amount,
                method: $payment->method,
                status: $payment->status,
                paidAt: $payment->paid_at?->format('Y-m-d H:i:s'),
                refundReason: $payment->refund_reason,
                refundedAt: $payment->refunded_at?->format('Y-m-d H:i:s'),
                appointmentId: $payment->appointment_id ? (int) $payment->appointment_id : null,
                invoiceId: $payment->appointment?->invoice_id ? (int) $payment->appointment->invoice_id : null,
            ),
            'abono' => $abonoResult,
        ];
    }

    private function tryCreateAbono(Payment $payment): array
    {
        $payment->loadMissing('appointment.invoice');

        $invoice = $payment->appointment?->invoice;

        if (! $invoice) {
            return [
                'created' => false,
                'reason' => 'no_invoice',
            ];
        }

        $result = $this->invoicingService->issueAbonoForInvoice($invoice);

        return [
            'created' => $result['created'],
            'document_id' => (int) $result['document']->id,
        ];
    }
}
