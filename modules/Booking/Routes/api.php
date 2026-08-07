<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\AvailabilityController;
use Modules\Booking\Http\Controllers\BookingAppointmentController;
use Modules\Booking\Http\Controllers\BookingProfessionalController;
use Modules\Booking\Http\Controllers\BookingServiceController;
use Modules\Booking\Http\Controllers\BookingSettingsController;
use Modules\Booking\Http\Controllers\ExceptionController;
use Modules\Booking\Http\Controllers\PublicBookingController;
use Modules\Booking\Http\Controllers\PublicBookingPageController;
use Modules\Booking\Http\Controllers\ScheduleController;

/*
|--------------------------------------------------------------------------
| Booking Module Routes
|--------------------------------------------------------------------------
|
| Rutas del módulo de reserva online.
| Se registran automáticamente vía BookingServiceProvider.
| Prefijo /api: se aplica aquí porque loadRoutesFrom() no hereda el
| prefijo automático de routes/api.php (Laravel 12).
|
*/

Route::prefix('api')->group(function () {

    // -----------------------------
    // RUTAS PÚBLICAS (sin auth)
    // -----------------------------

    // Reserva online — rutas públicas (sin auth, con throttle)
    // NOTA: /booking/availability, /booking/slots, /booking/confirm/{token} y /booking/cancel/{token}
    // deben definirse ANTES de /booking/{slug} para evitar que {slug} las capture.
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/booking/availability', [AvailabilityController::class, 'dates']);
        Route::get('/booking/slots', [AvailabilityController::class, 'slots']);
        Route::post('/booking', [PublicBookingController::class, 'store']);
        Route::get('/booking/confirm/{token}', [PublicBookingController::class, 'show']);
        Route::post('/booking/cancel/{token}', [PublicBookingController::class, 'cancel']);
    });

    // Reserva online — rutas de configuración (admin, auth). Deben ir ANTES de /booking/{slug}
    Route::middleware(['auth:sanctum', 'clinic', 'check.subscription'])->group(function () {
        Route::get('/booking/settings', [BookingSettingsController::class, 'show']);
        Route::put('/booking/settings', [BookingSettingsController::class, 'update']);
        Route::get('/booking/slug-check', [BookingSettingsController::class, 'checkSlug']);
        Route::get('/booking/services', [BookingServiceController::class, 'index']);
        Route::post('/booking/services', [BookingServiceController::class, 'store']);
        Route::put('/booking/services/{id}', [BookingServiceController::class, 'update']);
        Route::delete('/booking/services/{id}', [BookingServiceController::class, 'destroy']);
        Route::get('/booking/professionals', [BookingProfessionalController::class, 'index']);
        Route::post('/booking/professionals', [BookingProfessionalController::class, 'store']);
        Route::put('/booking/professionals/{id}', [BookingProfessionalController::class, 'update']);
        Route::delete('/booking/professionals/{id}', [BookingProfessionalController::class, 'destroy']);
        Route::get('/booking/professionals/{professionalId}/schedules', [ScheduleController::class, 'index']);
        Route::post('/booking/professionals/{professionalId}/schedules/bulk', [ScheduleController::class, 'bulkUpdate']);
        Route::post('/booking/professionals/{professionalId}/schedules', [ScheduleController::class, 'store']);
        Route::put('/booking/professionals/{professionalId}/schedules/{scheduleId}', [ScheduleController::class, 'update']);
        Route::delete('/booking/professionals/{professionalId}/schedules/{scheduleId}', [ScheduleController::class, 'destroy']);
        Route::get('/booking/professionals/{professionalId}/exceptions', [ExceptionController::class, 'index']);
        Route::post('/booking/professionals/{professionalId}/exceptions', [ExceptionController::class, 'store']);
        Route::put('/booking/professionals/{professionalId}/exceptions/{exceptionId}', [ExceptionController::class, 'update']);
        Route::delete('/booking/professionals/{professionalId}/exceptions/{exceptionId}', [ExceptionController::class, 'destroy']);
        Route::get('/booking/appointments', [BookingAppointmentController::class, 'index']);
    });

    // Debe ir al final para no capturar las rutas admin (settings, services, professionals, etc.)
    Route::middleware('throttle:30,1')->get('/booking/{slug}', [PublicBookingPageController::class, 'show']);

});
