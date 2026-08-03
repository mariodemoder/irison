<?php

namespace App\Policies;

use App\Models\User;

class EmailLogPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasFullAccess();
    }
}
