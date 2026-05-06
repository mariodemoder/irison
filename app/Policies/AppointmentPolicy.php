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

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function issueInvoice(User $user, Appointment $appointment): bool
    {
        return $this->sameClinic($user, $appointment);
    }
}
