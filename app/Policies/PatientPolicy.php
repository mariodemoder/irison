<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id;
    }
}
