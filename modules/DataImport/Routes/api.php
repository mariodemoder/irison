<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\DataImport\Infrastructure\Controllers\ImportController;

/**
 * Rutas de importación CSV. Solo planes PRO/Enterprise (pro.access).
 * Se registran automáticamente vía DataImportServiceProvider.
 */
Route::prefix('api')->middleware(['auth:sanctum', 'clinic', 'check.subscription', 'pro.access'])->group(function () {
    Route::post('imports/{entity}', [ImportController::class, 'import']);
    Route::get('imports/{entity}/template', [ImportController::class, 'template']);
});