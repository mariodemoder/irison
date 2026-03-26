<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function view(User $user, $model): bool
    {
        return $model instanceof Payment && $this->sameClinic($user, $model);
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function update(User $user, $model): bool
    {
        if (!$model instanceof Payment) {
            return false;
        }

        if (!$this->sameClinic($user, $model)) {
            return false;
        }

        if ((string) $model->status === 'refunded') {
            return false;
        }

        return !$this->isLockedByInvoice($model);
    }

    public function delete(User $user, $model): bool
    {
        if (!$model instanceof Payment) {
            return false;
        }

        if (!$this->sameClinic($user, $model)) {
            return false;
        }

        return !$this->isLockedByInvoice($model);
    }

    private function isLockedByInvoice(Payment $payment): bool
    {
        $payment->loadMissing([
            'appointment:id,invoice_id',
            'package:id,invoice_id',
        ]);

        return (int) ($payment->appointment?->invoice_id ?? 0) > 0
            || (int) ($payment->package?->invoice_id ?? 0) > 0;
    }
}
