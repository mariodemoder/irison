<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Models\Appointment;

class AppointmentPendingPaymentService
{
    public function calculatePendingAmount(Appointment $appointment): float
    {
        if (!empty($appointment->bonus_id)) {
            return 0.0;
        }

        $price = (float) ($appointment->price ?? 0);
        if ($price <= 0) {
            return 0.0;
        }

        $creditUsageAmount = $this->creditUsageAmountForAppointment($appointment);
        $simpleCreditAmount = $this->simpleCreditAmountForAppointment($appointment);

        return (float) number_format(max($price - $creditUsageAmount - $simpleCreditAmount, 0), 2, '.', '');
    }

    public function syncPaymentStatus(Appointment $appointment): void
    {
        if ($appointment->payment_type === 'bonus' || !empty($appointment->bonus_id)) {
            if ($appointment->payment_status !== 'covered_by_pack') {
                $appointment->update(['payment_status' => 'covered_by_pack']);
            }
            return;
        }

        $price = (float) ($appointment->price ?? 0);
        $pendingAmount = $this->calculatePendingAmount($appointment);
        $coveredAmount = max($price - $pendingAmount, 0.0);

        if ($coveredAmount <= 0) {
            if ($appointment->payment_status !== 'pending') {
                $appointment->update(['payment_status' => 'pending']);
            }
            return;
        }

        if ($pendingAmount > 0) {
            if ($appointment->payment_status !== 'partially_paid') {
                $appointment->update(['payment_status' => 'partially_paid']);
            }
            return;
        }

        if ($appointment->payment_status !== 'paid') {
            $appointment->update(['payment_status' => 'paid']);
        }
    }

    private function creditUsageAmountForAppointment(Appointment $appointment): float
    {
        if ($appointment->relationLoaded('creditUsages')) {
            $sum = $appointment->creditUsages
                ->filter(fn ($usage) => empty($usage->reversed_at))
                ->sum(fn ($usage) => (float) ($usage->amount ?? 0));

            return (float) $sum;
        }

        return (float) $appointment->creditUsages()
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    private function simpleCreditAmountForAppointment(Appointment $appointment): float
    {
        if ($appointment->relationLoaded('payments')) {
            $sum = $appointment->payments
                ->filter(function ($payment) {
                    $concept = (string) ($payment->concept ?? '');
                    $status = (string) ($payment->status ?? '');

                    return ($concept === '' || $concept === 'appointment') && $status === 'completed';
                })
                ->sum(fn ($payment) => (float) ($payment->amount ?? 0));

            return (float) $sum;
        }

        return (float) $appointment->payments()
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->where('concept', 'appointment')
                    ->orWhereNull('concept');
            })
            ->sum('amount');
    }
}
