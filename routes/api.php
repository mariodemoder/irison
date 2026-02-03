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

Route::middleware(['auth:sanctum', 'clinic', 'clinic.active'])->group(function () {

    Route::apiResource('patients', PatientController::class);
    Route::apiResource('appointments', AppointmentController::class);
    
});
Route::post('/register', RegisterController::class);
// API login para clientes SPA (valida credenciales y devuelve token)
Route::post('/login', [AuthController::class, 'login']);

// Stripe Checkout
Route::middleware(['auth:sanctum'])->post('/stripe/checkout', \App\Http\Controllers\Api\StripeCheckoutController::class);

// Stripe webhook (no auth)
Route::post('/stripe/webhook', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handle']);

// Fake subscribe (development/testing): marca la clínica como suscrita
Route::middleware(['auth:sanctum'])->post('/subscribe/fake', \App\Http\Controllers\Api\FakeSubscribeController::class);

// Información del usuario autenticado (frontend single-point)
// `/me` debe estar disponible para UI aunque el trial esté expirado — devuelve el `status` canónico
Route::middleware(['auth:sanctum', 'clinic'])->get('/me', \App\Http\Controllers\Api\MeController::class);

