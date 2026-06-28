<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function view(User $user, $model): bool
    {
        if (!$this->sameClinic($user, $model)) {
            return false;
        }

        if ($user->isViewer() && (int) $model->professional_id !== (int) $user->id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasFullAccess();
    }

    public function update(User $user, $model): bool
    {
        return $this->sameClinic($user, $model) && $user->hasFullAccess();
    }

    public function delete(User $user, $model): bool
    {
        return $this->sameClinic($user, $model) && $user->hasFullAccess();
    }

    public function issueInvoice(User $user, Appointment $appointment): bool
    {
        return $this->sameClinic($user, $appointment) && $user->hasFullAccess();
    }
}
