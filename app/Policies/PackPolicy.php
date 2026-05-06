<?php

namespace App\Policies;

use App\Models\Bonus;
use App\Models\User;

class PackPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function issueInvoice(User $user, Bonus $bonus): bool
    {
        return $this->sameClinic($user, $bonus);
    }
}
