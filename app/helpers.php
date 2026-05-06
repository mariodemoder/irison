<?php

use App\Models\Clinic;
use Illuminate\Support\Facades\Auth;

if (! function_exists('currentClinic')) {
    function currentClinic(): ?Clinic
    {
        if (app()->bound('activeClinic')) {
            $clinic = app('activeClinic');

            return $clinic instanceof Clinic ? $clinic : null;
        }

        return Auth::user()?->clinic;
    }
}

if (! function_exists('currentClinicId')) {
    function currentClinicId(): ?int
    {
        return currentClinic()?->id;
    }
}