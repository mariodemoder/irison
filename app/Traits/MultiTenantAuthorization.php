<?php

namespace App\Traits;

use App\Models\User;

trait MultiTenantAuthorization
{
    /**
     * Verifica si el usuario pertenece a la misma clínica que el modelo.
     */
    protected function sameClinic(User $user, $model): bool
    {
        return $user->clinic_id === $model->clinic_id;
    }
}
