<?php

declare(strict_types=1);

namespace Modules\Finance\Application\UseCases;

use App\Models\Payment;
use Carbon\Carbon;
use Modules\Finance\Application\DTOs\IncomeData;

class RegisterManualIncomeCommand
{
    public function execute(int $clinicId, array $validated): IncomeData
    {
        $payment = Payment::create([
            'clinic_id' => $clinicId,
            'patient_id' => $validated['patient_id'] ?? null,
            'professional_id' => $validated['professional_id'] ?? null,
            'concept' => 'other',
            'amount' => round((float) $validated['amount'], 2),
            'method' => $validated['method'],
            'status' => 'completed',
            'paid_at' => Carbon::parse($validated['date']),
            'notes' => $this->buildNotes($validated),
        ]);

        return new IncomeData(
            id: (int) $payment->id,
            patientName: $payment->patient?->name ?? null,
            professionalName: $payment->professional?->name ?? null,
            concept: $payment->concept,
            amount: (float) $payment->amount,
            method: $payment->method,
            status: $payment->status,
            paidAt: $payment->paid_at?->format('Y-m-d H:i:s'),
            appointmentId: null,
            invoiceId: null,
        );
    }

    private function buildNotes(array $validated): ?string
    {
        $parts = [];

        if (! empty($validated['description'])) {
            $parts[] = $validated['description'];
        }

        if (! empty($validated['notes'])) {
            $parts[] = $validated['notes'];
        }

        return $parts ? implode(' — ', $parts) : null;
    }
}
