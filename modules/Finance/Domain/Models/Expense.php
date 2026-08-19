<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Carbon\CarbonInterface;
use Modules\Finance\Domain\Enums\ExpensePaymentMethod;

class Expense
{
    public function __construct(
        public readonly int $id,
        public readonly int $clinicId,
        public readonly string $concept,
        public readonly float $amount,
        public readonly float $taxRate,
        public readonly float $total,
        public readonly ?int $categoryId = null,
        public readonly ?int $providerId = null,
        public readonly ?string $supplier = null,
        public readonly ?CarbonInterface $date = null,
        public readonly ?ExpensePaymentMethod $paymentMethod = null,
        public readonly ?string $receiptNumber = null,
        public readonly ?string $notes = null,
        public readonly ?CarbonInterface $createdAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'concept' => $this->concept,
            'category_id' => $this->categoryId,
            'provider_id' => $this->providerId,
            'supplier' => $this->supplier,
            'amount' => number_format($this->amount, 2, '.', ''),
            'tax_rate' => number_format($this->taxRate, 2, '.', ''),
            'total' => number_format($this->total, 2, '.', ''),
            'date' => $this->date?->format('Y-m-d'),
            'payment_method' => $this->paymentMethod?->value,
            'payment_method_label' => $this->paymentMethod?->label(),
            'receipt_number' => $this->receiptNumber,
            'notes' => $this->notes,
            'created_at' => $this->createdAt?->toDateTimeString(),
        ];
    }
}
