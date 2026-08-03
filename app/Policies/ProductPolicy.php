<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasOperationalAccess();
    }

    public function view(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model) && $user->hasOperationalAccess();
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasOperationalAccess();
    }

    public function update(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model) && $user->hasOperationalAccess();
    }

    public function delete(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model) && $user->hasOperationalAccess();
    }
}
