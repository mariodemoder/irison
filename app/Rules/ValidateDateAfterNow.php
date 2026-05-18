<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class ValidateDateAfterNow implements ValidationRule
{
    /**
     * Valida que la fecha sea posterior a la fecha actual.
     * Útil para: Appointment.date, Bonus.expires_at, ClinicalRecord.date
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $date = Carbon::parse($value)->startOfDay();

            if ($date->lt(Carbon::today())) {
                $fail("El campo {$attribute} debe ser una fecha futura.");
            }
        } catch (\Exception $e) {
            $fail("El campo {$attribute} no es una fecha válida.");
        }
    }
}
