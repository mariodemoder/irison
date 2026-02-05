<?php

namespace App\Policies;

use App\Models\User;
use App\Traits\MultiTenantAuthorization;

abstract class BasePolicy
{
    use MultiTenantAuthorization;

    public function view(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function update(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
    }

    public function restore(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
    }

    public function forceDelete(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
    }
}
