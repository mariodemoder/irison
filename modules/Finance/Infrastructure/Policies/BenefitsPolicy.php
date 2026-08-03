<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Policies;

use App\Models\User;
use App\Policies\BasePolicy;

class BenefitsPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id && $user->hasFullAccess();
    }
}
