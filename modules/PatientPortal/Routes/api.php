<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Modules\PatientPortal\Infrastructure\Controllers\PatientAuthController;
use Modules\PatientPortal\Infrastructure\Controllers\PublicClinicBrandingController;
use Modules\PatientPortal\Infrastructure\Controllers\PatientDashboardController;
use Modules\PatientPortal\Infrastructure\Controllers\PatientAppointmentController;
use Modules\PatientPortal\Infrastructure\Controllers\PatientBonusController;
use Modules\PatientPortal\Infrastructure\Controllers\PatientPaymentController;
use Modules\PatientPortal\Infrastructure\Controllers\PatientConsentController;
use Modules\PatientPortal\Infrastructure\Controllers\PatientDocumentController;
use Modules\PatientPortal\Infrastructure\Controllers\PatientNotificationController;
use Modules\PatientPortal\Infrastructure\Controllers\PatientProfileController;

// Rate limiters
RateLimiter::for('patient-login', function (Request $request) {
    return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(
        $request->input('email') . '|' . $request->ip()
    );
});

RateLimiter::for('patient-forgot', function (Request $request) {
    return \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by($request->input('email'));
});

Route::prefix('patient')->group(function () {

    // ── Public (no auth required) ──
    Route::get('public/branding/{slug}', [PublicClinicBrandingController::class, 'show']);
    Route::post('auth/login', [PatientAuthController::class, 'login'])
        ->middleware('throttle:patient-login');
    Route::post('auth/forgot-password', [PatientAuthController::class, 'forgotPassword'])
        ->middleware('throttle:patient-forgot');
    Route::post('auth/reset-password', [PatientAuthController::class, 'resetPassword']);

    // ── Protected (patient auth + clinic context) ──
    // auth:patient resuelve el token Sanctum del paciente; patient.auth valida
    // que sea un paciente activo y patient.clinic fija el contexto multi-tenant.
    Route::middleware(['auth:patient', 'patient.auth', 'patient.clinic'])->group(function () {

        // Auth
        Route::post('auth/logout', [PatientAuthController::class, 'logout']);
        Route::get('auth/me', [PatientAuthController::class, 'me']);

        // Dashboard
        Route::get('dashboard', [PatientDashboardController::class, 'index']);

        // Profile
        Route::get('profile', [PatientProfileController::class, 'index']);
        Route::put('profile', [PatientProfileController::class, 'update']);

        // Appointments
        Route::get('appointments/upcoming', [PatientAppointmentController::class, 'upcoming']);
        Route::get('appointments/history', [PatientAppointmentController::class, 'history']);
        Route::get('appointments/{id}', [PatientAppointmentController::class, 'show']);
        Route::post('appointments/requests', [PatientAppointmentController::class, 'request']);
        Route::post('appointments/{id}/cancel', [PatientAppointmentController::class, 'cancel']);
        Route::post('appointments/{id}/reschedule', [PatientAppointmentController::class, 'reschedule']);

        // Bonuses
        Route::get('bonuses', [PatientBonusController::class, 'index']);
        Route::get('bonuses/{id}', [PatientBonusController::class, 'show']);

        // Payments
        Route::get('payments', [PatientPaymentController::class, 'index']);
        Route::get('payments/pending', [PatientPaymentController::class, 'pending']);

        // Consents
        Route::get('consents', [PatientConsentController::class, 'index']);
        Route::get('consents/{id}', [PatientConsentController::class, 'show']);
        Route::post('consents/{id}/sign', [PatientConsentController::class, 'sign']);

        // Documents
        Route::get('documents', [PatientDocumentController::class, 'index']);
        Route::get('documents/{id}', [PatientDocumentController::class, 'show']);
        Route::get('documents/{id}/download', [PatientDocumentController::class, 'download']);

        // Notifications
        Route::get('notifications', [PatientNotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [PatientNotificationController::class, 'markRead']);
    });
});
