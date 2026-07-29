<?php

declare(strict_types=1);

namespace Modules\Bonus\Services;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\BonusUsage;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BonusAppointmentOrchestrator
{
    public function __construct(
        private readonly BonusService $bonusService,
    ) {}

    public function normalizeBonusId(mixed $bonusId): ?int
    {
        if ($bonusId === null || $bonusId === '') {
            return null;
        }

        if (!is_numeric($bonusId)) {
            throw new \DomainException('Bono inválido');
        }

        $normalized = (int) $bonusId;
        if ($normalized <= 0) {
            throw new \DomainException('Bono inválido');
        }

        return $normalized;
    }

    public function shouldValidateAvailability(string $status): bool
    {
        return !in_array($status, ['completed', 'canceled', 'cancelled'], true);
    }

    public function isCoveredByBonus(Appointment $appointment): bool
    {
        return $appointment->payment_type === 'bonus' || !empty($appointment->bonus_id);
    }

    public function validateForAppointment(int $bonusId, Appointment $appointment, ?string $targetStatus = null): Bonus
    {
        $bonus = Bonus::find($bonusId);

        if (!$bonus) {
            throw new \DomainException('Bono no encontrado');
        }

        if ($bonus->patient_id != $appointment->patient_id) {
            throw new \DomainException('El bono no pertenece a este paciente');
        }

        if ($bonus->clinic_id != (int) ($appointment->clinic_id ?? currentClinicId())) {
            throw new \DomainException('El bono no pertenece a esta clínica');
        }

        $normalizedStatus = strtolower(trim((string) ($targetStatus ?? $appointment->status ?? '')));
        if ($this->shouldValidateAvailability($normalizedStatus)) {
            if ($bonus->remaining_sessions <= 0) {
                throw new \DomainException('El bono seleccionado no está disponible');
            }

            if ($bonus->isExpired()) {
                throw new \DomainException('Bono expirado');
            }
        }

        return $bonus;
    }

    public function validateBonusPaymentRule(array $data, int $clinicId): void
    {
        if (empty($data['use_bonus_id'])) {
            throw new \DomainException('Debe seleccionar un bono');
        }

        $bonusId = $this->normalizeBonusId($data['use_bonus_id']);
        $bonus = Bonus::find($bonusId);

        if (!$bonus) {
            throw new \DomainException('Bono no encontrado');
        }

        if ($bonus->clinic_id != $clinicId) {
            throw new \DomainException('Bono no pertenece a esta clínica');
        }

        $targetStatus = strtolower(trim((string) ($data['status'] ?? 'scheduled')));
        if ($this->shouldValidateAvailability($targetStatus)) {
            if ($bonus->remaining_sessions <= 0) {
                throw new \DomainException('El bono seleccionado no está disponible');
            }

            if ($bonus->isExpired()) {
                throw new \DomainException('Bono expirado');
            }
        }
    }

    public function consumeOnCreate(Appointment $appointment, array $data): void
    {
        if (empty($data['use_bonus_id']) && empty($data['bonus_id'])) {
            return;
        }

        $bonusId = $this->normalizeBonusId($data['use_bonus_id'] ?? $data['bonus_id']);
        if (!$bonusId) {
            return;
        }

        $appointment->bonus_id = $bonusId;
        $appointment->saveQuietly();

        $this->bonusService->useBonusForAppointment(
            $bonusId,
            $appointment,
            $data['bonus_notes'] ?? null
        );
    }

    public function restoreAndDetachBonus(Appointment $appointment): void
    {
        $this->bonusService->restoreBonusIfCancelled($appointment);
    }

    public function consumeOrChangeBonus(Appointment $appointment, array $data): BonusUsage
    {
        $targetBonusId = array_key_exists('use_bonus_id', $data)
            ? $this->normalizeBonusId($data['use_bonus_id'])
            : ($appointment->bonus_id ? (int) $appointment->bonus_id : null);

        if (empty($targetBonusId)) {
            throw new \DomainException('Debe seleccionar un bono al cambiar a payment_type=bonus');
        }

        $oldPaymentType = $appointment->payment_type;

        // If previously bonus with different bonus -> restore old usage first
        if ($oldPaymentType === 'bonus' && $appointment->bonus_id && $appointment->bonus_id != $targetBonusId) {
            $this->bonusService->restoreBonusIfCancelled($appointment);
        }

        $appointment->bonus_id = $targetBonusId;
        $appointment->payment_type = 'bonus';
        $appointment->saveQuietly();

        // If an existing usage exists for this appointment and bonus, reuse it
        $existing = BonusUsage::where('bonus_id', $targetBonusId)
            ->where('appointment_id', $appointment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->bonusService->useBonusForAppointment(
            $targetBonusId,
            $appointment,
            $data['bonus_notes'] ?? null
        );
    }

    public function restoreOnCancel(Appointment $appointment): void
    {
        $this->bonusService->restoreBonusIfCancelled($appointment);

        $appointment->bonus_id = null;
        $appointment->payment_type = 'single';
        $appointment->saveQuietly();
    }

    public function getBonusPaymentsForAppointment(Appointment $appointment): Collection
    {
        if ($appointment->payment_status !== 'covered_by_pack' || empty($appointment->bonus_id)) {
            return collect();
        }

        return Payment::query()
            ->where('clinic_id', (int) $appointment->clinic_id)
            ->where('patient_id', (int) $appointment->patient_id)
            ->where('concept', 'package')
            ->where('package_id', (int) $appointment->bonus_id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();
    }

    public function getPatientBonuses(int $patientId, int $clinicId): Collection
    {
        $paidPackages = Payment::query()
            ->select('package_id')
            ->whereNotNull('package_id')
            ->where('clinic_id', $clinicId)
            ->groupBy('package_id');

        $bonuses = Bonus::with('sessionLines.appointmentType')
            ->select('bonuses.*')
            ->addSelect(DB::raw('paid_packages.package_id as package_payment_id'))
            ->leftJoinSub($paidPackages, 'paid_packages', function ($join) {
                $join->on('paid_packages.package_id', '=', 'bonuses.id');
            })
            ->where('bonuses.clinic_id', $clinicId)
            ->where('bonuses.patient_id', $patientId)
            ->orderByDesc('bonuses.id')
            ->get();

        return $bonuses->map(function (Bonus $bonus) {
            $isPaid = !is_null($bonus->getAttribute('package_payment_id'));

            return [
                'id' => $bonus->id,
                'patient_id' => $bonus->patient_id,
                'name' => $bonus->name,
                'total_sessions' => (int) $bonus->total_sessions,
                'remaining_sessions' => (int) $bonus->remaining_sessions,
                'price' => (float) ($bonus->price ?? 0),
                'invoice_id' => $bonus->invoice_id ? (int) $bonus->invoice_id : null,
                'expires_at' => $bonus->expires_at?->toDateString(),
                'is_paid' => $isPaid,
                'session_lines' => $bonus->sessionLines->map(fn ($line) => [
                    'id' => $line->id,
                    'appointment_type_id' => $line->appointment_type_id,
                    'appointment_type_name' => $line->appointmentType?->description ?? '—',
                    'quantity' => $line->quantity,
                    'remaining_quantity' => $line->remaining_quantity,
                ])->values()->toArray(),
            ];
        })->values();
    }
}
