<?php
declare(strict_types=1);

namespace App\Services\Appointments;

use Some\Dependency;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\CreditUsage;
use App\Models\Payment;
use App\Models\Patient;
use App\Services\Availability\CheckAvailability;
use Modules\Bonus\Services\BonusAppointmentOrchestrator;
use App\Services\Appointments\AppointmentPendingPaymentService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use DomainException;

class AppointmentService
{
    public function __construct(
        private readonly AppointmentPendingPaymentService $appointmentPendingPaymentService,
        private readonly BonusAppointmentOrchestrator $bonusOrchestrator,
    )
    {
    }

    public function list(array $filters)
    {
        // Auto-complete scheduled whose end_time passed
        Appointment::where('status', 'scheduled')
            ->where('end_time', '<', Carbon::now())
            ->update(['status' => 'completed']);

        $query = Appointment::with(['patient', 'payments', 'creditUsages', 'appointmentType', 'professional']);

        $user = auth()->user();
        if ($user && $user->isViewer()) {
            $query->where('professional_id', $user->id);
        }

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

        if (!empty($filters['professional_id'])) {
            $query->where('professional_id', $filters['professional_id']);
        } elseif (!empty($filters['no_professional'])) {
            $query->whereNull('professional_id');
        }

        $appointments = $query->orderBy('start_time')->get();

        return $appointments->map(function (Appointment $appointment) {
            return $this->attachPendingPaymentAmount($appointment);
        });
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $clinicId = $this->resolveClinic($data);

            $data = $this->normalizeDateTimePayload($data);

            $this->validateClinicScheduleConstraints((int) $clinicId, $data, null);

            $this->checkAvailability($clinicId, $data);

            $this->validatePaymentRules($data, $clinicId);

            $data['clinic_id'] = $clinicId;

            if (isset($data['custom_type'])) {
                $customType = trim((string) $data['custom_type']);
                $data['custom_type'] = $customType !== '' ? $customType : null;
            }
            
            // Si se proporciona app_type_id, sugerir precio y tipo de pago del tipo
            if (!empty($data['app_type_id']) && is_numeric($data['app_type_id'])) {
                $appType = \App\Models\AppointmentType::query()
                    ->where('clinic_id', $clinicId)
                    ->find($data['app_type_id']);
                
                if ($appType) {
                    if (!isset($data['price']) || $data['price'] === '' || $data['price'] === null) {
                        $data['price'] = $appType->price;
                    }

                    if (!isset($data['payment_type']) || $data['payment_type'] === '' || $data['payment_type'] === null) {
                        $supportsBonus = $appType->bonusTypes()->exists();
                        $data['payment_type'] = $supportsBonus ? 'bonus' : 'single';
                    }

                    // Si el tipo viene tipificado, limpiar cualquier texto libre
                    $data['custom_type'] = null;
                }
            } else {
                $data['app_type_id'] = null;
            }
            
            if (isset($data['use_bonus_id'])) {
                $data['use_bonus_id'] = $this->bonusOrchestrator->normalizeBonusId($data['use_bonus_id']);
                $data['bonus_id'] = $data['use_bonus_id'];
            }

            $appointment = Appointment::create($data);

            $this->bonusOrchestrator->consumeOnCreate($appointment, $data);

            $this->applyCreditUsage($appointment, $data, 'usage_on_create');
            $this->syncPendingCreditPaymentUsage($appointment, $data);
            $this->appointmentPendingPaymentService->syncPaymentStatus($appointment);

            return $appointment->load(['patient', 'bonus', 'appointmentType']);
        });
    }

    public function show(Appointment $appointment, array $params = [])
    {
        // If use_bonus_id requested, try to apply via BonusService
        if (!empty($params['use_bonus_id'])) {
            try {
                $bonusId = $this->bonusOrchestrator->normalizeBonusId($params['use_bonus_id']);
                $usage = app(\Modules\Bonus\Services\BonusService::class)
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

        $appointment->load(['bonus', 'patient', 'creditUsages', 'payments', 'appointmentType', 'professional']);

        $bonusPayments = $this->bonusOrchestrator->getBonusPaymentsForAppointment($appointment);

        $appointment->setAttribute('bonus_payments', $bonusPayments->values());
        return $this->attachPendingPaymentAmount($appointment);
    }

    public function update(Appointment $appointment, array $data)
    {
        return DB::transaction(function () use ($appointment, $data) {
            $data = $this->normalizeDateTimePayload($data, $appointment);

            $oldPaymentType = $appointment->payment_type;
            $newPaymentType = $data['payment_type'] ?? $oldPaymentType;

            if ($this->needsAvailabilityCheck($data)) {
                $this->validateClinicScheduleConstraints((int) $appointment->clinic_id, $data, $appointment);
                $this->checkAvailability($appointment->clinic_id, $data, $appointment->id);
            }

            // switching from bonus -> single: restore usage
            if ($oldPaymentType === 'bonus' && $newPaymentType === 'single') {
                $appointment->update(array_merge($data, ['payment_type' => 'single', 'bonus_id' => null]));
                $this->bonusOrchestrator->restoreAndDetachBonus($appointment);
                $this->syncPendingCreditPaymentUsage($appointment, $data);
                $this->appointmentPendingPaymentService->syncPaymentStatus($appointment);

                return $appointment->load(['patient', 'bonus']);
            }

            // switching to bonus (consume or change)
            if ($newPaymentType === 'bonus') {
                $this->validateBonusForUpdate($appointment, $data);

                $usage = $this->bonusOrchestrator->consumeOrChangeBonus($appointment, $data);

                // Bonus and pending-credit-payment cannot coexist
                $this->restorePendingCreditPaymentUsage($appointment);
                $this->appointmentPendingPaymentService->syncPaymentStatus($appointment);

                return ['appointment' => $appointment->load(['patient', 'bonus']), 'bonus_usage' => $usage];
            }

            // default: regular update
            if (isset($data['use_bonus_id'])) {
                $data['use_bonus_id'] = $this->bonusOrchestrator->normalizeBonusId($data['use_bonus_id']);
                $data['bonus_id'] = $data['use_bonus_id'];
            }

            $appointment->update($data);

            $this->applyCreditUsage($appointment, $data, 'usage_on_update', true);
            $this->syncPendingCreditPaymentUsage($appointment, $data);
            $this->appointmentPendingPaymentService->syncPaymentStatus($appointment);

            return $appointment->load(['patient', 'bonus']);
        });
    }

    public function cancel(Appointment $appointment)
    {
        return DB::transaction(function () use ($appointment) {
            $appointment->update(['status' => 'canceled']);

            if ($this->bonusOrchestrator->isCoveredByBonus($appointment)) {
                $this->bonusOrchestrator->restoreOnCancel($appointment);
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
    $clinicId = currentClinicId();

    if ($clinicId) {
        return $clinicId;
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

        $professionalId = null;
        if (array_key_exists('professional_id', $data) && $data['professional_id'] !== null && $data['professional_id'] !== '') {
            $professionalId = (int) $data['professional_id'];
        }

        $validation = $checker->validate(
            (int) $clinicId,
            $start,
            $end,
            $patientId,
            $ignoreAppointmentId,
            $professionalId
        );

        if (!$validation['valid']) {
            throw new DomainException(implode(', ', $validation['errors']));
        }
    }

    private function validatePaymentRules(array $data, $clinicId)
    {
        $targetStatus = strtolower(trim((string) ($data['status'] ?? 'scheduled')));

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

        $this->bonusOrchestrator->validateBonusPaymentRule($data, $clinicId);
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

    private function validateBonusForUpdate(Appointment $appointment, array $data): void
    {
        $targetStatus = array_key_exists('status', $data)
            ? (string) $data['status']
            : (string) ($appointment->status ?? '');

        $targetBonusId = array_key_exists('use_bonus_id', $data)
            ? $this->bonusOrchestrator->normalizeBonusId($data['use_bonus_id'])
            : ($appointment->bonus_id ? (int) $appointment->bonus_id : null);

        if (empty($targetBonusId)) {
            throw new DomainException('Debe seleccionar un bono al cambiar a payment_type=bonus');
        }

        $this->bonusOrchestrator->validateForAppointment($targetBonusId, $appointment, $targetStatus);
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
            'counter' => $payment->counter,
            'reason' => 'usage_pending_credit_payment',
        ]);

        $this->appointmentPendingPaymentService->syncPaymentStatus($appointment);
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
            ->where('reason', 'usage_pending_credit_payment')
            ->pluck('payment_id')
            ->filter()
            ->unique()
            ->values();

        if ($paymentIds->isEmpty()) {
            return;
        }

        CreditUsage::query()
            ->where('appointment_id', '=', $appointment->id)
            ->whereNotNull('payment_id')
            ->whereNull('reversed_at')
            ->where('reason', '=', 'usage_pending_credit_payment')
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

    private function normalizeDateTimePayload(array $data, ?Appointment $appointment = null): array
    {
        $date = isset($data['date']) && $data['date'] !== ''
            ? (string) $data['date']
            : null;

        if (!$date && $appointment?->start_time) {
            $date = Carbon::parse($appointment->start_time)->toDateString();
        }

        if ($date && !empty($data['start_time']) && is_string($data['start_time']) && preg_match('/^\d{2}:\d{2}$/', $data['start_time'])) {
            $data['start_time'] = $date . ' ' . $data['start_time'] . ':00';
        }

        if ($date && !empty($data['end_time']) && is_string($data['end_time']) && preg_match('/^\d{2}:\d{2}$/', $data['end_time'])) {
            $data['end_time'] = $date . ' ' . $data['end_time'] . ':00';
        }

        unset($data['date']);

        return $data;
    }

    private function validateClinicScheduleConstraints(int $clinicId, array $data, ?Appointment $currentAppointment = null): void
    {
        $startValue = $data['start_time'] ?? ($currentAppointment?->start_time ? (string) $currentAppointment->start_time : null);
        $endValue = $data['end_time'] ?? ($currentAppointment?->end_time ? (string) $currentAppointment->end_time : null);

        if (!$startValue || !$endValue) {
            return;
        }

        $clinic = Clinic::query()->find($clinicId);
        if (!$clinic) {
            return;
        }

        $start = Carbon::parse($startValue);
        $end = Carbon::parse($endValue);
        $dateIso = $start->toDateString();

        if ($this->isDateClosedForClinic($dateIso, (array) ($clinic->closed_days ?? []))) {
            throw new DomainException('La clínica está cerrada en la fecha seleccionada');
        }

        $businessHours = (array) ($clinic->business_hours ?? []);
        if (empty($businessHours)) {
            return;
        }

        $dayKey = strtolower($start->englishDayOfWeek);
        $dayConfig = collect($businessHours)->first(function ($item) use ($dayKey) {
            return strtolower((string) ($item['day'] ?? '')) === $dayKey;
        });

        if (!$dayConfig) {
            return;
        }

        if (!filter_var($dayConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new DomainException('La clínica está cerrada en la fecha seleccionada');
        }

        $openStart = trim((string) ($dayConfig['start'] ?? ''));
        $openEnd = trim((string) ($dayConfig['end'] ?? ''));
        if (!preg_match('/^\d{2}:\d{2}$/', $openStart) || !preg_match('/^\d{2}:\d{2}$/', $openEnd)) {
            return;
        }

        $startHm = $start->format('H:i');
        $endHm = $end->format('H:i');
        if ($startHm < $openStart || $endHm > $openEnd) {
            throw new DomainException('La cita está fuera del horario de atención de la clínica');
        }
    }

    private function isDateClosedForClinic(string $dateIso, array $rules): bool
    {
        foreach ($rules as $ruleRaw) {
            $rule = trim((string) $ruleRaw);
            if ($rule === '') {
                continue;
            }

            if (!str_contains($rule, '..')) {
                if ($rule === $dateIso) {
                    return true;
                }
                continue;
            }

            [$from, $to] = array_pad(explode('..', $rule, 2), 2, null);
            $from = trim((string) $from);
            $to = trim((string) $to);
            if ($from !== '' && $to !== '' && $from <= $dateIso && $dateIso <= $to) {
                return true;
            }
        }

        return false;
    }


    private function shouldApplyCredit(array $data): bool
    {
        $applyFlag = filter_var($data['apply_credit'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasAmount = array_key_exists('apply_credit_amount', $data)
            && $data['apply_credit_amount'] !== null
            && $data['apply_credit_amount'] !== '';

        return $applyFlag || $hasAmount;
    }

    private function applyCreditUsage(Appointment $appointment, array $data, string $reason, bool $resetExisting = false): void
    {
        if ($resetExisting) {
            $this->restoreAppliedCreditUsage($appointment, 'appointment_credit_usage_replaced');
        }

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

        $pendingAppointmentAmount = $this->appointmentPendingPaymentService->calculatePendingAmount($appointment);
        if ($pendingAppointmentAmount <= 0) {
            return;
        }

        $manualAmount = array_key_exists('apply_credit_amount', $data)
            && $data['apply_credit_amount'] !== null
            && $data['apply_credit_amount'] !== ''
            ? (float) $data['apply_credit_amount']
            : null;

        $requestedAmount = $manualAmount !== null ? $manualAmount : $availableCredit;
        $amountToApply = min($requestedAmount, $availableCredit, $pendingAppointmentAmount);

        if ($amountToApply <= 0) {
            throw new DomainException('El importe de crédito a aplicar debe ser mayor que cero');
        }

        if ($requestedAmount > $availableCredit) {
            throw new DomainException('El importe supera el crédito disponible del paciente');
        }

        $remainingToApply = $amountToApply;

        $creditPayments = Payment::query()
            ->where('clinic_id', $appointment->clinic_id)
            ->where('patient_id', $appointment->patient_id)
            ->where('concept', 'credit')
            ->where('status', '!=', 'refunded')
            ->orderByRaw('COALESCE(paid_at, created_at) ASC')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($creditPayments as $payment) {
            if ($remainingToApply <= 0) {
                break;
            }

            $paymentPendingAmount = $this->pendingAmountForCreditPayment($payment);
            if ($paymentPendingAmount <= 0) {
                continue;
            }

            $chunkAmount = min($remainingToApply, $paymentPendingAmount);
            if ($chunkAmount <= 0) {
                continue;
            }

            CreditUsage::create([
                'clinic_id' => $appointment->clinic_id,
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'payment_id' => $payment->id,
                'amount' => $chunkAmount,
                'counter' => $payment->counter,
                'reason' => $reason,
            ]);

            $remainingToApply -= $chunkAmount;
        }

        $appliedAmount = $amountToApply - $remainingToApply;
        if ($appliedAmount <= 0.0001) {
            throw new DomainException('No se pudo aplicar crédito disponible a la cita. Intenta de nuevo.');
        }

        $this->appointmentPendingPaymentService->syncPaymentStatus($appointment);
    }

    private function restoreCreditOnCancel(Appointment $appointment): void
    {
        $paymentIds = CreditUsage::query()
            ->where('appointment_id', $appointment->id)
            ->whereNotNull('payment_id')
            ->whereNull('reversed_at')
            ->pluck('payment_id')
            ->filter()
            ->unique()
            ->values();

        $appointment->creditUsages()
            ->whereNull('reversed_at')
            ->update([
                'reversed_at' => now(),
                'reversed_reason' => 'appointment_canceled',
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

    private function restoreAppliedCreditUsage(Appointment $appointment, string $reversedReason): void
    {
        $activeUsageQuery = CreditUsage::query()
            ->where('appointment_id', $appointment->id)
            ->whereNull('reversed_at')
            ->whereIn('reason', ['usage_on_create', 'usage_on_update']);

        $paymentIds = (clone $activeUsageQuery)
            ->whereNotNull('payment_id')
            ->pluck('payment_id')
            ->filter()
            ->unique()
            ->values();

        $activeUsageQuery->update([
            'reversed_at' => now(),
            'reversed_reason' => $reversedReason,
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

    private function attachPendingPaymentAmount(Appointment $appointment): Appointment
    {
        $appointment->setAttribute(
            'pending_payment_amount',
            $this->appointmentPendingPaymentService->calculatePendingAmount($appointment)
        );

        return $appointment;
    }
}