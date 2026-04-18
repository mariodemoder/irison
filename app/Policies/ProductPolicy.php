<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function view(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model);
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function update(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return $model instanceof Product && $this->sameClinic($user, $model);
    }
}
