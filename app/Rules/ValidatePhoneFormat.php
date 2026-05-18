<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidatePhoneFormat implements ValidationRule
{
    /**
     * Valida que el teléfono tenga un formato válido.
     * Acepta formatos: +34 XXX XXX XXX, +34XXXXXXXXX, 6XX XXX XXX, etc.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Patrón: +XX (país) seguido de 8-14 dígitos, con espacios opcionales
        $pattern = '/^(\+\d{1,3})?[\s]?\d{6,14}$/';

        if (!preg_match($pattern, trim($value))) {
            $fail("El campo {$attribute} no tiene un formato de teléfono válido.");
        }
    }
}
