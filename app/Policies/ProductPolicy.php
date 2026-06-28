<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasFullAccess();
    }

    public function view(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model) && $user->hasFullAccess();
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasFullAccess();
    }

    public function update(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model) && $user->hasFullAccess();
    }

    public function delete(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model) && $user->hasFullAccess();
    }
}
