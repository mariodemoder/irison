<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class ValidateTimeRange implements ValidationRule
{
    /**
     * Valida que end_time sea posterior a start_time.
     * Se usa como regla condicional: 'end_time' => ['required', new ValidateTimeRange($request->input('start_time'))]
     */
    private ?string $startTime;

    public function __construct(?string $startTime = null)
    {
        $this->startTime = $startTime;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->startTime) {
            return; // Si no hay start_time, no validamos
        }

        try {
            $start = Carbon::createFromFormat('H:i', $this->startTime);
            $end = Carbon::createFromFormat('H:i', $value);

            if ($end->lte($start)) {
                $fail("El campo {$attribute} debe ser posterior a la hora de inicio.");
            }
        } catch (\Exception $e) {
            $fail("El formato de hora no es válido (esperado: HH:mm).");
        }
    }
}
