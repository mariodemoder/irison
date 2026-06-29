<?php

namespace App\Policies;

use App\Models\User;

class PatientConsentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function view(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
    }

    public function signPresential(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
    }
}
