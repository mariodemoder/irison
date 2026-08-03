<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Infrastructure\Controllers\BenefitsController;
use Modules\Finance\Infrastructure\Controllers\ExpenseCategoryController;
use Modules\Finance\Infrastructure\Controllers\ExpenseController;
use Modules\Finance\Infrastructure\Controllers\ProfessionalRateController;

Route::prefix('api')->middleware(['auth:sanctum', 'clinic', 'check.subscription', 'pro.access'])->group(function () {
    Route::get('finance/expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::post('finance/expense-categories', [ExpenseCategoryController::class, 'store']);
    Route::put('finance/expense-categories/{category}', [ExpenseCategoryController::class, 'update']);
    Route::delete('finance/expense-categories/{category}', [ExpenseCategoryController::class, 'destroy']);

    Route::get('finance/expenses', [ExpenseController::class, 'index']);
    Route::post('finance/expenses', [ExpenseController::class, 'store']);
    Route::get('finance/expenses/{expense}', [ExpenseController::class, 'show']);
    Route::put('finance/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('finance/expenses/{expense}', [ExpenseController::class, 'destroy']);

    Route::get('finance/professional-rates', [ProfessionalRateController::class, 'index']);
    Route::put('finance/professional-rates/{user}', [ProfessionalRateController::class, 'update']);

    Route::get('finance/benefits', [BenefitsController::class, 'show']);
});
