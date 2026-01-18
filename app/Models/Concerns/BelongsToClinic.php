<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ClinicScope;

trait BelongsToClinic
{
    protected static function bootBelongsToClinic(): void
    {
        static::addGlobalScope(new ClinicScope);

        static::creating(function ($model) {
            if (!$model->clinic_id && app()->has('activeClinic')) {
                $model->clinic_id = app('activeClinic')->id;
            }
        });
    }
}
