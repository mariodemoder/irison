<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Infrastructure\Controllers\EmailLogController;
use Modules\Notifications\Infrastructure\Controllers\ReminderController;

Route::prefix('api')->middleware(['auth:sanctum', 'clinic', 'check.subscription'])->group(function () {
    Route::get('reminders', [ReminderController::class, 'index']);
    Route::get('reminders/{reminder}', [ReminderController::class, 'show']);
    Route::post('reminders/{reminder}/resend', [ReminderController::class, 'resend']);

    Route::get('notifications', [EmailLogController::class, 'index']);
    Route::get('notifications/{log}', [EmailLogController::class, 'show']);
    Route::post('notifications/{log}/resend', [EmailLogController::class, 'resend']);
});
