<?php

declare(strict_types=1);

namespace Modules\Bonus\Policies;

use App\Models\Bonus;
use App\Models\User;
use App\Policies\BasePolicy;

class BonusPolicy extends BasePolicy
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
        return $this->sameClinic($user, $bonus) && $user->hasFullAccess();
    }
}
