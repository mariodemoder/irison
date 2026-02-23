<?php
declare(strict_types=1);

namespace App\Services\Bonus;

use App\Models\Bonus;
use App\Models\BonusUsage;
use App\Models\Appointment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use DomainException;

class BonusService
{
    public function forPatient(int $patientId, ?int $clinicId = null, bool $activeOnly = false): Collection
    {
        $query = Bonus::where('patient_id', $patientId);

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        if ($activeOnly) {
            $query->where('remaining_sessions', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
        }

        $list = $query->orderBy('created_at', 'desc')->get();

        return $list->map(function (Bonus $bonus) {
            return [
                'id' => $bonus->id,
                'name' => $bonus->name,
                'total_sessions' => (int) $bonus->total_sessions,
                'remaining_sessions' => (int) $bonus->remaining_sessions,
                'price' => $bonus->price ?? 0,
                'expires_at' => $bonus->expires_at ? $bonus->expires_at->toDateString() : null,
                'status' => $bonus->status,
            ];
        });
    }

    public function createForPatient(int $patientId, array $data, ?int $clinicId = null): Bonus
    {
        if (!$clinicId) {
            throw new DomainException('No se encontró clínica activa para crear el bono');
        }

        return $this->assignBonusToPatient(
            $clinicId,
            $patientId,
            $data['name'],
            (int) $data['total_sessions'],
            (float) ($data['price'] ?? 0),
            isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null
        );
    }

    public function updateBonus(Bonus $bonus, array $data): Bonus
    {
        $bonus->fill($data);
        $bonus->save();

        return $bonus;
    }

    public function deleteBonus(Bonus $bonus): void
    {
        $bonus->delete();
    }

    public function expiring(?int $clinicId = null): Collection
    {
        $query = Bonus::with('patient')
            ->where('remaining_sessions', 1)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        $list = $query->orderBy('remaining_sessions', 'asc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return $list->map(function (Bonus $bonus) {
            return [
                'id' => $bonus->patient ? $bonus->patient->id : null,
                'patient_name' => $bonus->patient ? $bonus->patient->name : '—',
                'sessions_left' => (int) $bonus->remaining_sessions,
                'bonus_id' => $bonus->id,
                'bonus_name' => $bonus->name ?? null,
                'expires_at' => $bonus->expires_at ? $bonus->expires_at->toDateString() : null,
            ];
        })->filter(function (array $item) {
            return $item['id'] !== null;
        })->values();
    }

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
