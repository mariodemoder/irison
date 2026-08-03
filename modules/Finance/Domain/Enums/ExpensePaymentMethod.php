<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Enums;

enum ExpensePaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Card => 'Tarjeta',
            self::Transfer => 'Transferencia',
        };
    }
}
