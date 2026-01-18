<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PatientController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas de datos (JSON).
| Protegidas por auth + clinic (multi-tenant).
|
*/

Route::middleware(['auth:sanctum', 'clinic'])->group(function () {

    Route::apiResource('patients', PatientController::class);
    Route::apiResource('appointments', AppointmentController::class);

});
