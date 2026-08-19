<?php

declare(strict_types=1);

namespace Modules\Finance\Application\DTOs;

class IncomeData
{
    private const CONCEPT_LABELS = [
        'appointment' => 'Cita',
        'package' => 'Bono',
        'credit' => 'Crédito',
        'other' => 'Manual',
    ];

    public function __construct(
        public readonly int $id,
        public readonly ?string $patientName,
        public readonly ?string $professionalName,
        public readonly string $concept,
        public readonly float $amount,
        public readonly string $method,
        public readonly string $status,
        public readonly ?string $paidAt,
        public readonly ?string $refundReason = null,
        public readonly ?string $refundedAt = null,
        public readonly ?int $appointmentId = null,
        public readonly ?int $invoiceId = null,
    ) {}

    public function conceptLabel(): string
    {
        return self::CONCEPT_LABELS[$this->concept] ?? $this->concept;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'patient_name' => $this->patientName,
            'professional_name' => $this->professionalName,
            'concept' => $this->concept,
            'concept_label' => $this->conceptLabel(),
            'amount' => $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'paid_at' => $this->paidAt,
            'refund_reason' => $this->refundReason,
            'refunded_at' => $this->refundedAt,
            'appointment_id' => $this->appointmentId,
            'invoice_id' => $this->invoiceId,
        ];
    }
}
