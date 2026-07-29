<?php

use Illuminate\Support\Facades\Route;
use Modules\Bonus\Http\Controllers\BonusController;
use Modules\Bonus\Http\Controllers\BonusTypeController;
use Modules\Bonus\Http\Controllers\PatientBonusController;

/*
|--------------------------------------------------------------------------
| Bonus Module Routes
|--------------------------------------------------------------------------
|
| Rutas del módulo de bonos.
| Se registran automáticamente vía BonusServiceProvider.
| Prefijo /api: se aplica aquí porque loadRoutesFrom() no hereda el
| prefijo automático de routes/api.php (Laravel 12).
|
*/

Route::prefix('api')->group(function () {

    Route::middleware(['auth:sanctum', 'clinic', 'check.subscription', \Illuminate\Routing\Middleware\SubstituteBindings::class])->group(function () {

        // Bonos por paciente
        Route::get('patients/{patient}/bonuses', [PatientBonusController::class, 'index']);
        Route::post('patients/{patient}/bonuses', [PatientBonusController::class, 'store']);

        // Bonos expirando (1 sesión restante)
        Route::get('bonuses/expiring', [BonusController::class, 'expiring']);

        // Bonos CRUD
        Route::apiResource('bonuses', BonusController::class)->only(['index', 'show', 'update', 'destroy']);

        // Facturar bono
        Route::post('bonuses/{bonus}/invoice', [BonusController::class, 'issueInvoice']);

        // Tipos de bono (templates)
        Route::apiResource('bonus-types', BonusTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    });

});
