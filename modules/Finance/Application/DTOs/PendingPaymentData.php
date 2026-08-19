<?php

declare(strict_types=1);

namespace Modules\Finance\Application\DTOs;

class PendingPaymentData
{
    public function __construct(
        public readonly int $appointmentId,
        public readonly string $patientName,
        public readonly string $professionalName,
        public readonly ?string $appointmentDate,
        public readonly string $serviceName,
        public readonly float $price,
        public readonly float $paidAmount,
        public readonly float $pendingAmount,
        public readonly string $paymentStatus,
    ) {}

    public function toArray(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'patient_name' => $this->patientName,
            'professional_name' => $this->professionalName,
            'appointment_date' => $this->appointmentDate,
            'service_name' => $this->serviceName,
            'price' => $this->price,
            'paid_amount' => $this->paidAmount,
            'pending_amount' => $this->pendingAmount,
            'payment_status' => $this->paymentStatus,
        ];
    }
}
