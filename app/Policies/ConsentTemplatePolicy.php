<?php

namespace App\Policies;

use App\Models\User;

class ConsentTemplatePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function view(User $user, $model): bool
    {
        return $this->sameClinic($user, $model);
    }
}
