<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Policies;

use App\Models\User;
use App\Policies\BasePolicy;
use Modules\Notifications\Infrastructure\Persistence\ReminderEloquentModel;

class ReminderPolicy extends BasePolicy
{
    protected string $model = ReminderEloquentModel::class;

    public function viewAny(User $user): bool
    {
        return $user->hasFullAccess();
    }
}
