<?php

namespace App\Services\Availability;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\Carbon;

/**
 * Servicio simple para comprobar disponibilidad de una franja horaria.
 * - `validate(...)` realiza las validaciones solicitadas y devuelve errores si los hay.
 * - `check(...)` devuelve 'disponible' o 'ocupado' según solapamiento.
 */
class CheckAvailability
{
    /**
     * Valida reglas: start < end, paciente existe y pertenece a la clínica,
     * y detecta solapamiento con citas existentes.
     *
     * @param int $clinicId
     * @param Carbon $start
     * @param Carbon $end
     * @param int|null $patientId
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(int $clinicId, Carbon $start, Carbon $end, ?int $patientId = null): array
    {
        $errors = [];

        if ($start->gte($end)) {
            $errors[] = 'La hora de inicio debe ser anterior a la hora de fin.';
        }

        if ($patientId !== null) {
            $patient = Patient::find($patientId);
            if (! $patient) {
                $errors[] = 'Paciente no encontrado.';
            } elseif ($patient->clinic_id !== $clinicId) {
                $errors[] = 'El paciente no pertenece a esta clínica.';
            }
        }

        $overlap = Appointment::where('clinic_id', $clinicId)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                  ->where('end_time', '>', $start);
            })
            ->exists();

        if ($overlap) {
            $errors[] = 'La franja horaria se solapa con otra cita.';
        }

        return empty($errors)
            ? ['valid' => true, 'errors' => []]
            : ['valid' => false, 'errors' => $errors];
    }

    /**
     * Comprueba disponibilidad estrictamente por solapamiento.
     * Devuelve la string 'disponible' o 'ocupado'.
     *
     * Nota: validations como paciente existe o start<end deben llamarse
     * por separado usando `validate()` si se requieren.
     *
     * @param int $clinicId
     * @param Carbon $start
     * @param Carbon $end
     * @return string
     */
    public function check(int $clinicId, Carbon $start, Carbon $end): string
    {
        $conflict = Appointment::where('clinic_id', $clinicId)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                  ->where('end_time', '>', $start);
            })
            ->exists();

        return $conflict ? 'ocupado' : 'disponible';
    }
}
