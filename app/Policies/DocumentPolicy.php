<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->clinic_id;
    }

    public function view(User $user, $model): bool
    {
        return $model instanceof Document && $this->sameClinic($user, $model);
    }
}