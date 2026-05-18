<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidatePaymentAmount implements ValidationRule
{
    /**
     * Valida que el monto sea mayor a 0 y menor o igual al balance disponible.
     */
    private ?float $maxAmount;

    public function __construct(?float $maxAmount = null)
    {
        $this->maxAmount = $maxAmount;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $amount = (float) $value;

        if ($amount <= 0) {
            $fail("El campo {$attribute} debe ser mayor a 0.");
            return;
        }

        if ($this->maxAmount !== null && $amount > $this->maxAmount) {
            $fail("El campo {$attribute} no puede exceder {$this->maxAmount}.");
        }
    }
}
