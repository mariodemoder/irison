<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\EnsureClinic;

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
Route::post('/register', RegisterController::class);
// API login para clientes SPA (valida credenciales y devuelve token)
Route::post('/login', [AuthController::class, 'login']);

