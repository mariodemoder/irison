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

    public function view(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
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
}
