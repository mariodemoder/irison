<?php

declare(strict_types=1);

namespace Modules\Bonus\Policies;

use App\Models\User;
use App\Policies\BasePolicy;
use Modules\Bonus\Models\BonusType;

class BonusTypePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasFullAccess();
    }

    public function create(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasFullAccess();
    }
}
