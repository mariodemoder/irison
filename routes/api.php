<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\API\ActivateAccountController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardSummaryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas de datos (JSON).
| Protegidas por auth + clinic (multi-tenant).
|
*/

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas de datos (JSON). Reorganizadas en: rutas públicas, webhooks,
| y rutas protegidas por middleware (auth + tenant).
|
*/

// -----------------------------
// RUTAS PÚBLICAS (sin auth)
// -----------------------------

// Registro de usuario (mobile/SPA) — crea usuario y tenant inicial
Route::post('/register', RegisterController::class);
Route::get('/register/activate/{user}', ActivateAccountController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('api.register.activate');

// Login para clientes SPA — valida credenciales y devuelve token
Route::post('/login', [AuthController::class, 'login']);

// Webhooks (Stripe, billing) deben ser públicos y verificarse por firma
// Stripe webhook (recibe eventos desde Stripe)
Route::post('/stripe/webhook', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handle']);

// Billing webhook (desde proveedor de pagos)
Route::post('/billing/webhook', [\App\Http\Controllers\BillingController::class, 'webhook']);


// -----------------------------
// RUTAS PROTEGIDAS (auth + tenant activo)
// -----------------------------

Route::middleware(['auth:sanctum', 'clinic', 'clinic.active'])->group(function () {

    // Pacientes: CRUD multitenant
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/history/pdf', [\App\Http\Controllers\Api\PatientHistoryPdfController::class, 'pdf']);
    Route::get('patients/{patient}/images', [\App\Http\Controllers\Api\PatientImageController::class, 'index']);
    Route::post('patients/{patient}/images', [\App\Http\Controllers\Api\PatientImageController::class, 'storeBatch']);
    Route::put('patients/{patient}/images/{image}', [\App\Http\Controllers\Api\PatientImageController::class, 'update']);
    Route::delete('patients/{patient}/images/{image}', [\App\Http\Controllers\Api\PatientImageController::class, 'destroy']);

    // Bonos: endpoints mínimos (list/create for patient + resource actions)
    Route::get('patients/{patient}/bonuses', [\App\Http\Controllers\Api\BonusController::class, 'forPatient']);
    Route::post('patients/{patient}/bonuses', [\App\Http\Controllers\Api\BonusController::class, 'storeForPatient']);
    // Listado compacto para UI: pacientes con bonos con 1 sesión restante
    Route::get('bonuses/expiring', [\App\Http\Controllers\Api\BonusController::class, 'expiring']);
    Route::apiResource('bonuses', \App\Http\Controllers\Api\BonusController::class)->only(['index','show','update','destroy']);
    Route::post('bonuses/{bonus}/invoice', [\App\Http\Controllers\Api\BonusController::class, 'issueInvoice']);

    // Citas: CRUD multitenant
    Route::get('appointments/form-bootstrap', [AppointmentController::class, 'formBootstrap']);
    Route::apiResource('appointments', AppointmentController::class);

    // Dashboard: resumen agregado para minimizar llamadas del frontend
    Route::get('dashboard/summary', DashboardSummaryController::class);

    // Pagos de clientes: listado, creación y detalle
    Route::get('payments/appointment-options', [\App\Http\Controllers\Api\PaymentController::class, 'appointmentOptions']);
    Route::get('payments/package-options', [\App\Http\Controllers\Api\PaymentController::class, 'packageOptions']);
    Route::apiResource('payments', \App\Http\Controllers\Api\PaymentController::class)
        ->only(['index', 'store', 'show', 'update']);

    Route::get('reminders', [\App\Http\Controllers\Api\ReminderController::class, 'index']);
    Route::get('reminders/{reminder}', [\App\Http\Controllers\Api\ReminderController::class, 'show']);
    Route::post('reminders/{reminder}/resend', [\App\Http\Controllers\Api\ReminderController::class, 'resend']);

    // Facturación (solo lectura): listado y detalle de facturas/documentos
    Route::get('documents/{document}/pdf', [\App\Http\Controllers\Api\DocumentController::class, 'pdf']);
    Route::post('documents/{document}/abono', [\App\Http\Controllers\Api\DocumentController::class, 'issueAbono']);
    Route::apiResource('documents', \App\Http\Controllers\Api\DocumentController::class)
        ->only(['index', 'show']);

    // Checkout con Stripe (inicia flujo de pago desde UI autenticada)
    Route::post('/stripe/checkout', \App\Http\Controllers\Api\StripeCheckoutController::class);

    // Endpoint de testing para marcar clínica como suscrita (dev)
    Route::post('/subscribe/fake', \App\Http\Controllers\Api\FakeSubscribeController::class);

    // Logout: revoca el token actual
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);

    // Cambiar contraseña del usuario autenticado
    Route::post('/me/password', [\App\Http\Controllers\Api\ProfilePasswordController::class, 'update']);

    // Billing: iniciar checkout desde la app (usuario autenticado)
    Route::post('/billing/checkout', [\App\Http\Controllers\BillingController::class, 'createCheckout']);

    // Cancelar cita (acción sobre recurso protegido)
    Route::post('appointments/{appointment}/cancel', [\App\Http\Controllers\Api\AppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/invoice', [\App\Http\Controllers\Api\AppointmentController::class, 'issueInvoice']);

});


// -----------------------------
// RUTAS CON CASOS ESPECIALES
// -----------------------------

// Información del usuario autenticado (`/me`): debe estar disponible
// aunque el trial haya expirado, por eso no incluimos `clinic.active`.
Route::middleware(['auth:sanctum', 'clinic'])->get('/me', \App\Http\Controllers\Api\MeController::class);
Route::middleware(['auth:sanctum', 'clinic'])->put('/me', [\App\Http\Controllers\Api\MeController::class, 'update']);
Route::middleware(['auth:sanctum', 'clinic'])->post('/me/invoice-background', [\App\Http\Controllers\Api\MeController::class, 'uploadInvoiceBackground']);
Route::middleware(['auth:sanctum', 'clinic'])->delete('/me/invoice-background', [\App\Http\Controllers\Api\MeController::class, 'deleteInvoiceBackground']);
Route::middleware(['auth:sanctum', 'clinic'])->post('/me/invoice-background/preview-pdf', [\App\Http\Controllers\Api\MeController::class, 'previewInvoiceBackgroundPdf']);


