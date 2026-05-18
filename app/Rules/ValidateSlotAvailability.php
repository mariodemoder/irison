<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Appointment;
use Carbon\Carbon;

class ValidateSlotAvailability implements ValidationRule
{
    /**
     * Valida que el horario esté disponible (sin conflictos con otras citas).
     * 
     * Uso: 'start_time' => [new ValidateSlotAvailability($request->input('date'), $clinicId, $appointmentId)]
     */
    private ?string $date;
    private ?int $clinicId;
    private ?int $appointmentId; // Para updates, excluir la cita actual
    private ?string $startTime;

    public function __construct(?string $date = null, ?int $clinicId = null, ?int $appointmentId = null, ?string $startTime = null)
    {
        $this->date = $date;
        $this->clinicId = $clinicId;
        $this->appointmentId = $appointmentId;
        $this->startTime = $startTime;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->date || !$this->clinicId) {
            return; // Sin contexto, no validamos
        }

        try {
            $startValue = $this->startTime ?: (is_string($value) ? $value : null);
            if (!$startValue || !is_string($value)) {
                return;
            }

            $startTime = Carbon::createFromFormat('H:i', $startValue);
            $endTime = Carbon::createFromFormat('H:i', $value);

            // Buscar conflictos
            $query = Appointment::where('clinic_id', $this->clinicId)
                ->whereDate('start_time', $this->date)
                ->where(function ($q) use ($startTime, $endTime) {
                    // Conflicto: la nueva cita comienza antes de que termine la existente
                    // y termina después de que comience la existente.
                    $q->whereTime('start_time', '<', $endTime->format('H:i'))
                        ->whereTime('end_time', '>', $startTime->format('H:i'));
                });

            if ($this->appointmentId) {
                $query->where('id', '!=', $this->appointmentId);
            }

            if ($query->exists()) {
                $fail("El horario {$value} no está disponible. Existe un conflicto con otra cita.");
            }
        } catch (\Exception $e) {
            $fail("Error al validar disponibilidad de horario: {$e->getMessage()}");
        }
    }
}
