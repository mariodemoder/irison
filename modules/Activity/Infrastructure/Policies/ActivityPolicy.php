<?php

declare(strict_types=1);

namespace Modules\Activity\Infrastructure\Policies;

use App\Models\User;
use App\Policies\BasePolicy;

class ActivityPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasFullAccess();
    }
}
