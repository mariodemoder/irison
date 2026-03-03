<?php
declare(strict_types=1);

namespace App\Services\Appointments;

use Some\Dependency;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\BonusUsage;
use App\Models\CreditUsage;
use App\Models\Payment;
use App\Models\Patient;
use App\Services\Availability\CheckAvailability;
use App\Services\Bonus\BonusService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use DomainException;

class AppointmentService
{
    public function list(array $filters)
    {
        // Auto-complete scheduled whose end_time passed
        Appointment::where('status', 'scheduled')
            ->where('end_time', '<', Carbon::now())
            ->update(['status' => 'completed']);

        $query = Appointment::with(['patient', 'payments', 'creditUsages']);

        if (!empty($filters['date'])) {
            $date = Carbon::parse($filters['date']);
            $query->whereBetween('start_time', [
                $date->startOfDay()->toDateTimeString(),
                $date->endOfDay()->toDateTimeString(),
            ]);
        } else {
            if (!empty($filters['from'])) {
                $query->where('start_time', '>=', $filters['from']);
            }
            if (!empty($filters['to'])) {
                $query->where('end_time', '<=', $filters['to']);
            }
        }

        return $query->orderBy('start_time')->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $clinicId = $this->resolveClinic($data);

            $this->checkAvailability($clinicId, $data);

            $this->validatePaymentRules($data, $clinicId);

            $data['clinic_id'] = $clinicId;
            if (isset($data['use_bonus_id'])) {
                $data['use_bonus_id'] = $this->normalizeBonusId($data['use_bonus_id']);
                $data['bonus_id'] = $data['use_bonus_id'];
            }

            $appointment = Appointment::create($data);

            if (($data['payment_type'] ?? null) === 'bonus') {
                app(BonusService::class)
                    ->useBonusForAppointment(
                        (int) $data['use_bonus_id'],
                        $appointment,
                        $data['bonus_notes'] ?? null
                    );
            }

            $this->applyCreditUsage($appointment, $data, 'usage_on_create');
            $this->syncPendingCreditPaymentUsage($appointment, $data);

            return $appointment->load(['patient', 'bonus']);
        });
    }

    public function show(Appointment $appointment, array $params = [])
    {
        // If use_bonus_id requested, try to apply via BonusService
        if (!empty($params['use_bonus_id'])) {
            try {
                $bonusId = $this->normalizeBonusId($params['use_bonus_id']);
                $usage = app(BonusService::class)
                    ->useBonusForAppointment(
                        (int) $bonusId,
                        $appointment,
                        $params['bonus_notes'] ?? null
                    );

                $appointment->load(['bonus', 'patient']);
                return ['appointment' => $appointment, 'bonus_usage' => $usage];
            } catch (\Exception $e) {
                throw new DomainException($e->getMessage());
            }
        }

        $appointment->load(['bonus', 'patient', 'creditUsages']);
        return $appointment;
    }

    public function update(Appointment $appointment, array $data)
    {
        return DB::transaction(function () use ($appointment, $data) {
            $oldPaymentType = $appointment->payment_type;
            $newPaymentType = $data['payment_type'] ?? $oldPaymentType;

            if ($this->needsAvailabilityCheck($data)) {
                $this->checkAvailability($appointment->clinic_id, $data, $appointment->id);
            }

            // switching from bonus -> single: restore usage
            if ($oldPaymentType === 'bonus' && $newPaymentType === 'single') {
                // update appointment first (clear bonus reference)
                $appointment->update(array_merge($data, ['payment_type' => 'single', 'bonus_id' => null]));
                app(BonusService::class)->restoreBonusIfCancelled($appointment);
                $this->syncPendingCreditPaymentUsage($appointment, $data);

                return $appointment->load(['patient', 'bonus']);
            }

            // switching to bonus (consume or change)
            if ($newPaymentType === 'bonus') {
                $targetBonusId = array_key_exists('use_bonus_id', $data)
                    ? $this->normalizeBonusId($data['use_bonus_id'])
                    : ($appointment->bonus_id ? (int) $appointment->bonus_id : null);

                if (empty($targetBonusId)) {
                    throw new DomainException('Debe seleccionar un bono al cambiar a payment_type=bonus');
                }

                $this->validateBonusForAppointment($targetBonusId, $appointment);

                $bonusService = app(BonusService::class);

                // If previously bonus with different bonus -> restore old usage first
                if ($oldPaymentType === 'bonus' && $appointment->bonus_id && $appointment->bonus_id != $targetBonusId) {
                    $bonusService->restoreBonusIfCancelled($appointment);
                }

                // persist new bonus_id and payment_type before attempting consumption
                $appointment->update(array_merge($data, ['payment_type' => 'bonus', 'bonus_id' => $targetBonusId]));

                // if an existing usage exists for this appointment and bonus, reuse it
                $existing = BonusUsage::where('bonus_id', $targetBonusId)
                    ->where('appointment_id', $appointment->id)
                    ->first();

                if ($existing) {
                    $usage = $existing;
                } else {
                    $usage = $bonusService->useBonusForAppointment(
                        $targetBonusId,
                        $appointment,
                        $data['bonus_notes'] ?? null
                    );
                }

                // Bonus and pending-credit-payment cannot coexist
                $this->restorePendingCreditPaymentUsage($appointment);

                return ['appointment' => $appointment->load(['patient', 'bonus']), 'bonus_usage' => $usage];
            }

            // default: regular update
            if (isset($data['use_bonus_id'])) {
                $data['use_bonus_id'] = $this->normalizeBonusId($data['use_bonus_id']);
                $data['bonus_id'] = $data['use_bonus_id'];
            }

            $appointment->update($data);

            $this->applyCreditUsage($appointment, $data, 'usage_on_update');
            $this->syncPendingCreditPaymentUsage($appointment, $data);

            return $appointment->load(['patient', 'bonus']);
        });
    }

    public function cancel(Appointment $appointment)
    {
        return DB::transaction(function () use ($appointment) {
            $appointment->update(['status' => 'canceled']);

            if ($appointment->payment_type === 'bonus' || $appointment->bonus_id) {
                app(BonusService::class)->restoreBonusIfCancelled($appointment);

                $appointment->update([
                    'bonus_id' => null,
                    'payment_type' => 'single'
                ]);
            }

            $this->restoreCreditOnCancel($appointment);
            $this->restorePendingCreditPaymentUsage($appointment);

            return $appointment;
        });
    }

    public function delete(Appointment $appointment)
    {
        $appointment->delete();
    }

    /* ---------- helpers ---------- */

private function resolveClinic(array $data)
{
    // bound() es explícito para bindings del contenedor
    if (app()->bound('activeClinic')) {
        $clinic = app('activeClinic');
        return $clinic->id ?? null;
    }

    return $data['clinic_id'] ?? null;
}

    private function checkAvailability($clinicId, array $data, $ignoreId = null)
    {
        $checker = app(CheckAvailability::class);

        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        if (!is_numeric($clinicId)) {
            throw new DomainException('Clínica inválida para validar disponibilidad');
        }

        $patientId = null;
        if (array_key_exists('patient_id', $data) && $data['patient_id'] !== null && $data['patient_id'] !== '') {
            if (!is_numeric($data['patient_id'])) {
                throw new DomainException('Paciente inválido para validar disponibilidad');
            }
            $patientId = (int) $data['patient_id'];
        }

        $ignoreAppointmentId = null;
        if ($ignoreId !== null && $ignoreId !== '') {
            if (!is_numeric($ignoreId)) {
                throw new DomainException('Cita inválida para validar disponibilidad');
            }
            $ignoreAppointmentId = (int) $ignoreId;
        }

        $validation = $checker->validate(
            (int) $clinicId,
            $start,
            $end,
            $patientId,
            $ignoreAppointmentId
        );

        if (!$validation['valid']) {
            throw new DomainException(implode(', ', $validation['errors']));
        }
    }

    private function validatePaymentRules(array $data, $clinicId)
    {
        if (!array_key_exists('price', $data) || $data['price'] === null || $data['price'] === '') {
            throw new DomainException('Debes indicar el precio de la sesión');
        }

        if (!is_numeric($data['price']) || (float) $data['price'] <= 0) {
            throw new DomainException('El precio de la sesión debe ser mayor que cero');
        }

        $wantsCredit = $this->shouldApplyCredit($data);

        if ($wantsCredit && (($data['payment_type'] ?? 'single') === 'bonus')) {
            throw new DomainException('No puedes aplicar crédito en una cita pagada con bono');
        }

        if (!empty($data['use_credit_payment_id']) && (($data['payment_type'] ?? 'single') === 'bonus')) {
            throw new DomainException('No puedes aplicar un adelanto en una cita pagada con bono');
        }

        if (!empty($data['use_credit_payment_id']) && $wantsCredit) {
            throw new DomainException('No puedes combinar adelanto pendiente con aplicar crédito automático/manual');
        }

        if (array_key_exists('use_credit_amount', $data) && $data['use_credit_amount'] !== null && $data['use_credit_amount'] !== '') {
            if (!is_numeric($data['use_credit_amount']) || (float) $data['use_credit_amount'] <= 0) {
                throw new DomainException('El importe de la sesión debe ser mayor que cero');
            }
        }

        if (array_key_exists('apply_credit_amount', $data) && $data['apply_credit_amount'] !== null && $data['apply_credit_amount'] !== '') {
            if (!is_numeric($data['apply_credit_amount']) || (float) $data['apply_credit_amount'] <= 0) {
                throw new DomainException('El importe de crédito a aplicar debe ser mayor que cero');
            }
        }

        if (($data['payment_type'] ?? null) !== 'bonus') {
            if (!empty($data['use_credit_payment_id'])) {
                $payment = $this->validatePendingCreditPayment(
                    (int) $data['use_credit_payment_id'],
                    $clinicId,
                    isset($data['patient_id']) ? (int) $data['patient_id'] : null
                );

                if (array_key_exists('use_credit_amount', $data) && $data['use_credit_amount'] !== null && $data['use_credit_amount'] !== '') {
                    $sessionAmount = (float) $data['use_credit_amount'];
                    $pendingAmount = $this->pendingAmountForCreditPayment($payment);
                    if ($sessionAmount > $pendingAmount) {
                        throw new DomainException('El importe de la sesión no puede superar el importe a favor pendiente');
                    }
                }
            }
            return;
        }

        if (empty($data['use_bonus_id'])) {
            throw new DomainException('Debe seleccionar un bono');
        }

        $bonusId = $this->normalizeBonusId($data['use_bonus_id']);
        $bonus = Bonus::find($bonusId);

        if (!$bonus) {
            throw new DomainException('Bono no encontrado');
        }

        if ($bonus->clinic_id != $clinicId) {
            throw new DomainException('Bono no pertenece a esta clínica');
        }

        if ($bonus->remaining_sessions <= 0) {
            throw new DomainException('Bono agotado');
        }

        if ($bonus->isExpired()) {
            throw new DomainException('Bono expirado');
        }
    }

    private function validatePendingCreditPayment(int $paymentId, int $clinicId, ?int $patientId): Payment
    {
        $payment = Payment::whereKey($paymentId)->lockForUpdate()->first();
        if (!$payment) {
            throw new DomainException('Adelanto pendiente no encontrado');
        }

        if ((int) $payment->clinic_id !== (int) $clinicId) {
            throw new DomainException('El adelanto no pertenece a esta clínica');
        }

        if ($patientId !== null && (int) $payment->patient_id !== (int) $patientId) {
            throw new DomainException('El adelanto no pertenece al paciente seleccionado');
        }

        if ((string) $payment->concept !== 'credit') {
            throw new DomainException('El pago seleccionado no es un adelanto');
        }

        if ((string) $payment->status !== 'pending') {
            throw new DomainException('El adelanto seleccionado ya no está disponible');
        }

        return $payment;
    }

    private function normalizeBonusId($bonusId): ?int
    {
        if ($bonusId === null || $bonusId === '') {
            return null;
        }

        if (!is_numeric($bonusId)) {
            throw new DomainException('Bono inválido');
        }

        $normalized = (int) $bonusId;
        if ($normalized <= 0) {
            throw new DomainException('Bono inválido');
        }

        return $normalized;
    }

    private function syncPendingCreditPaymentUsage(Appointment $appointment, array $data): void
    {
        $selectedPaymentId = isset($data['use_credit_payment_id']) && $data['use_credit_payment_id'] !== ''
            ? (int) $data['use_credit_payment_id']
            : null;

        if (!$selectedPaymentId) {
            $this->restorePendingCreditPaymentUsage($appointment);
            return;
        }

        // ensure no stale linkage remains
        $this->restorePendingCreditPaymentUsage($appointment);

        $payment = $this->validatePendingCreditPayment(
            $selectedPaymentId,
            (int) $appointment->clinic_id,
            (int) $appointment->patient_id
        );

        $pendingAmount = $this->pendingAmountForCreditPayment($payment);
        if ($pendingAmount <= 0) {
            throw new DomainException('El adelanto seleccionado ya no tiene importe disponible');
        }

        $requestedAmount = array_key_exists('use_credit_amount', $data) && $data['use_credit_amount'] !== null && $data['use_credit_amount'] !== ''
            ? (float) $data['use_credit_amount']
            : $pendingAmount;

        if ($requestedAmount <= 0) {
            throw new DomainException('El importe de la sesión debe ser mayor que cero');
        }

        if ($requestedAmount > $pendingAmount) {
            throw new DomainException('El importe de la sesión supera el disponible del adelanto');
        }

        $remainingAfterUsage = max($pendingAmount - $requestedAmount, 0);

        $payment->update([
            'status' => $remainingAfterUsage <= 0 ? 'completed' : 'pending',
            'paid_at' => $payment->paid_at ?? now(),
        ]);

        CreditUsage::create([
            'clinic_id' => $appointment->clinic_id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'payment_id' => $payment->id,
            'amount' => $requestedAmount,
            'reason' => 'usage_pending_credit_payment',
        ]);

        if ($appointment->payment_status === 'pending') {
            $appointment->update(['payment_status' => 'partially_paid']);
        }
    }

    private function pendingAmountForCreditPayment(Payment $payment): float
    {
        $usedAmount = (float) CreditUsage::query()
            ->whereNull('reversed_at')
            ->where('payment_id', $payment->id)
            ->where('patient_id', $payment->patient_id)
            ->sum('amount');

        return max((float) $payment->amount - $usedAmount, 0.0);
    }

    private function restorePendingCreditPaymentUsage(Appointment $appointment): void
    {
        $paymentIds = CreditUsage::query()
            ->where('appointment_id', $appointment->id)
            ->whereNotNull('payment_id')
            ->whereNull('reversed_at')
            ->pluck('payment_id')
            ->filter()
            ->unique()
            ->values();

        if ($paymentIds->isEmpty()) {
            return;
        }

        CreditUsage::query()
            ->where('appointment_id', $appointment->id)
            ->whereNotNull('payment_id')
            ->whereNull('reversed_at')
            ->update([
                'reversed_at' => now(),
                'reversed_reason' => 'appointment_pending_credit_reset',
            ]);

        foreach ($paymentIds as $paymentId) {
            $paymentId = (int) $paymentId;
            if ($paymentId <= 0) {
                continue;
            }

            $payment = Payment::whereKey($paymentId)->lockForUpdate()->first();
            if ($payment && (string) $payment->concept === 'credit' && (int) $payment->patient_id === (int) $appointment->patient_id) {
                $pendingAmount = $this->pendingAmountForCreditPayment($payment);
                $payment->update(['status' => $pendingAmount <= 0 ? 'completed' : 'pending']);
            }
        }
    }

    private function needsAvailabilityCheck(array $data): bool
    {
        return isset($data['start_time']) || isset($data['end_time']);
    }

    private function validateBonusForAppointment($bonusId, Appointment $appointment)
    {
        $bonus = Bonus::find($bonusId);

        if (!$bonus) {
            throw new DomainException('Bono no encontrado');
        }

        if ($bonus->patient_id != $appointment->patient_id) {
            throw new DomainException('El bono no pertenece a este paciente');
        }

        $clinicId = $this->resolveClinic(['clinic_id' => $appointment->clinic_id]);
        if ($bonus->clinic_id != $clinicId) {
            throw new DomainException('El bono no pertenece a esta clínica');
        }

        if ($bonus->remaining_sessions <= 0) {
            throw new DomainException('Bono agotado');
        }

        if ($bonus->isExpired()) {
            throw new DomainException('Bono expirado');
        }
    }

    private function shouldApplyCredit(array $data): bool
    {
        $applyFlag = filter_var($data['apply_credit'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasAmount = array_key_exists('apply_credit_amount', $data)
            && $data['apply_credit_amount'] !== null
            && $data['apply_credit_amount'] !== '';

        return $applyFlag || $hasAmount;
    }

    private function applyCreditUsage(Appointment $appointment, array $data, string $reason): void
    {
        if (!$this->shouldApplyCredit($data)) {
            return;
        }

        if (($appointment->payment_type ?? 'single') === 'bonus' || $appointment->bonus_id) {
            throw new DomainException('No puedes aplicar crédito en una cita pagada con bono');
        }

        $patient = Patient::whereKey($appointment->patient_id)->lockForUpdate()->first();
        if (!$patient) {
            throw new DomainException('Paciente no encontrado para aplicar crédito');
        }

        $availableCredit = $patient->availableCredit();
        if ($availableCredit <= 0) {
            throw new DomainException('El paciente no tiene crédito disponible');
        }

        $manualAmount = array_key_exists('apply_credit_amount', $data)
            && $data['apply_credit_amount'] !== null
            && $data['apply_credit_amount'] !== ''
            ? (float) $data['apply_credit_amount']
            : null;

        $amountToApply = $manualAmount !== null ? $manualAmount : $availableCredit;

        if ($amountToApply <= 0) {
            throw new DomainException('El importe de crédito a aplicar debe ser mayor que cero');
        }

        if ($amountToApply > $availableCredit) {
            throw new DomainException('El importe supera el crédito disponible del paciente');
        }

        CreditUsage::create([
            'clinic_id' => $appointment->clinic_id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'amount' => $amountToApply,
            'reason' => $reason,
        ]);

        if ($appointment->payment_status === 'pending') {
            $appointment->update(['payment_status' => 'partially_paid']);
        }
    }

    private function restoreCreditOnCancel(Appointment $appointment): void
    {
        $appointment->creditUsages()
            ->whereNull('reversed_at')
            ->whereNull('payment_id')
            ->update([
                'reversed_at' => now(),
                'reversed_reason' => 'appointment_canceled',
            ]);
    }
}