<?php

use Illuminate\Support\Facades\Route;
use Modules\Activity\Infrastructure\Controllers\ActivityController;

Route::prefix('api')->middleware(['auth:sanctum', 'clinic', 'check.subscription', 'pro.access'])->group(function () {
    Route::get('activity', [ActivityController::class, 'index']);
});