<?php

namespace App\Services;

use App\Models\Bonus;
use App\Models\BonusUsage;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

class BonusService
{
    /**
     * Assign a bonus to a patient (create Bonus record).
     * Returns the created Bonus.
     */
    public function assignBonusToPatient(int $clinicId, int $patientId, string $name, int $totalSessions, float $price = 0.0, ?\DateTime $expiresAt = null): Bonus
    {
        $data = [
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'name' => $name,
            'total_sessions' => $totalSessions,
            'remaining_sessions' => $totalSessions,
            'price' => $price,
            'expires_at' => $expiresAt,
        ];

        return Bonus::create($data);
    }

    /**
     * Use a bonus for an appointment.
     * Throws Exception on invalid usage (no remaining, expired, already used for this appointment).
     * Returns the created BonusUsage.
     */
    public function useBonusForAppointment(int $bonusId, Appointment $appointment, ?string $notes = null): BonusUsage
    {
        return DB::transaction(function () use ($bonusId, $appointment, $notes) {
            $bonus = Bonus::where('id', $bonusId)->lockForUpdate()->first();
            if (!$bonus) {
                throw new \Exception('Bono no encontrado');
            }

            // Check expiration (inclusive of the expiration day)
            if ($bonus->isExpired()) {
                throw new \Exception('Bono expirado');
            }

            // Check remaining
            if ($bonus->remaining_sessions <= 0) {
                throw new \Exception('No quedan sesiones en el bono');
            }

            // Prevent double use for same appointment
            $existing = BonusUsage::where('bonus_id', $bonus->id)->where('appointment_id', $appointment->id)->first();
            if ($existing) {
                throw new \Exception('El bono ya fue usado para esta cita');
            }

            // Decrement and create usage
            $bonus->remaining_sessions = $bonus->remaining_sessions - 1;
            $bonus->save();

            $usage = BonusUsage::create([
                'bonus_id' => $bonus->id,
                'appointment_id' => $appointment->id,
                'used_at' => now(),
                'notes' => $notes,
            ]);

            return $usage;
        });
    }

    /**
     * Restore a bonus usage when an appointment is cancelled.
     * If a usage exists for the appointment, increments remaining_sessions and deletes usage.
     * Returns true if restored, false if no usage found.
     */
    public function restoreBonusIfCancelled(Appointment $appointment): bool
    {
        return DB::transaction(function () use ($appointment) {
            $usage = BonusUsage::where('appointment_id', $appointment->id)->first();
            if (!$usage) return false;

            $bonus = Bonus::where('id', $usage->bonus_id)->lockForUpdate()->first();
            if (!$bonus) {
                // If bonus no longer exists, just delete usage
                $usage->delete();
                return true;
            }

            $bonus->remaining_sessions = $bonus->remaining_sessions + 1;
            $bonus->save();

            $usage->delete();

            return true;
        });
    }
}
