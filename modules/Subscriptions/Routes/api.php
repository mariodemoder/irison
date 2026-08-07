<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscriptions\Infrastructure\Controllers\Api\FakeSubscribeController;
use Modules\Subscriptions\Infrastructure\Controllers\Api\PricingController;
use Modules\Subscriptions\Infrastructure\Controllers\Api\StripeCheckoutController;
use Modules\Subscriptions\Infrastructure\Controllers\Api\SubscribeController;
use Modules\Subscriptions\Infrastructure\Controllers\Api\SubscriptionController;
use Modules\Subscriptions\Infrastructure\Controllers\Api\SubscriptionRequestController;
use Modules\Subscriptions\Infrastructure\Controllers\BillingController;

/*
|--------------------------------------------------------------------------
| Subscriptions Module Routes
|--------------------------------------------------------------------------
|
| Rutas del módulo de suscripciones.
| Se registran automáticamente vía SubscriptionsServiceProvider.
| Prefijo /api: se aplica aquí porque loadRoutesFrom() no hereda el
| prefijo automático de routes/api.php (Laravel 12).
|
*/

Route::prefix('api')->group(function () {

    // -----------------------------
    // RUTAS PÚBLICAS (sin auth)
    // -----------------------------

    // Precios de planes (público para la landing, se usa cacheable)
    Route::get('/pricing', [PricingController::class, 'index']);

    // Billing webhook (único para el proveedor de pagos, se verifica por firma)
    Route::post('/billing/webhook', [BillingController::class, 'webhook']);

    // -----------------------------
    // RUTAS PROTEGIDAS (auth + tenant activo)
    // -----------------------------

    Route::middleware(['auth:sanctum', 'clinic', 'check.subscription'])->group(function () {
        // Checkout con Stripe (inicia flujo de pago desde UI autenticada)
        Route::post('/stripe/checkout', StripeCheckoutController::class);

        // Endpoint de testing para marcar clínica como suscrita (dev)
        Route::post('/subscribe/fake', FakeSubscribeController::class);

        // Suscripción: plan actual, historial y solicitud de upgrade
        Route::get('/settings/subscription', [SubscriptionController::class, 'show']);
        Route::get('/settings/subscription/history', [SubscriptionController::class, 'history']);
        Route::post('/settings/subscription/request', [SubscriptionRequestController::class, 'store']);
        Route::post('/settings/subscription/confirm-upgrade', [SubscriptionRequestController::class, 'confirmUpgrade']);

        // Billing: iniciar checkout desde la app (usuario autenticado)
        Route::post('/billing/checkout', [BillingController::class, 'createCheckout']);

        // Suscripción manual (legacy Stripe/Cashier)
        Route::post('/subscribe', SubscribeController::class);
    });

    // Disponibles aunque el trial haya expirado (sin check.subscription)
    Route::middleware(['auth:sanctum', 'clinic'])->group(function () {
        Route::post('/billing/confirm', [BillingController::class, 'confirmCheckout']);
        Route::post('/billing/cancel', [BillingController::class, 'cancelSubscription']);
    });
});
