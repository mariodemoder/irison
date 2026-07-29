<?php

namespace App\Rules;

use Closure;
use Carbon\Carbon;
use App\Models\UserSchedule;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidateProfessionalSchedule implements ValidationRule
{
    private ?string $date;
    private ?string $startTime;
    private ?string $endTime;

    public function __construct(?string $date = null, ?string $startTime = null, ?string $endTime = null)
    {
        $this->date = $date;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $professionalId = $value;

        if (!$professionalId || !$this->date || !$this->startTime || !$this->endTime) {
            return;
        }

        $dayOfWeek = (int) Carbon::parse($this->date)->format('w');

        $schedule = UserSchedule::where('user_id', $professionalId)
            ->where('day_of_week', $dayOfWeek)
            ->where('enabled', true)
            ->first(['start_time', 'end_time']);

        if (!$schedule) {
            $days = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
            $fail("El profesional no trabaja los {$days[$dayOfWeek]}.");
            return;
        }

        if ($this->startTime < $schedule->start_time || $this->endTime > $schedule->end_time) {
            $fail("El horario de la cita debe estar dentro del horario laboral del profesional ({$schedule->start_time} - {$schedule->end_time}).");
        }
    }
}
