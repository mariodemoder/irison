<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Infrastructure\Controllers\BenefitsController;
use Modules\Finance\Infrastructure\Controllers\ExpenseCategoryController;
use Modules\Finance\Infrastructure\Controllers\ExpenseController;
use Modules\Finance\Infrastructure\Controllers\FinanceReportController;
use Modules\Finance\Infrastructure\Controllers\FinanceSummaryController;
use Modules\Finance\Infrastructure\Controllers\IncomeController;
use Modules\Finance\Infrastructure\Controllers\PendingPaymentController;
use Modules\Finance\Infrastructure\Controllers\ProfessionalRateController;
use Modules\Finance\Infrastructure\Controllers\ProviderController;

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

    Route::get('finance/providers', [ProviderController::class, 'index']);
    Route::post('finance/providers', [ProviderController::class, 'store']);
    Route::put('finance/providers/{provider}', [ProviderController::class, 'update']);
    Route::delete('finance/providers/{provider}', [ProviderController::class, 'destroy']);

    Route::get('finance/summary', [FinanceSummaryController::class, 'show']);
    Route::get('finance/benefits', [BenefitsController::class, 'show']);

    Route::get('finance/reports/{type}', [FinanceReportController::class, 'show'])->whereIn('type', ['income', 'expenses', 'profit', 'professional', 'service']);
    Route::get('finance/reports/{type}/export', [FinanceReportController::class, 'export'])->whereIn('type', ['income', 'expenses', 'profit', 'professional', 'service']);

    Route::get('finance/pending-payments', [PendingPaymentController::class, 'index']);
    Route::post('finance/pending-payments/{appointment}/register-payment', [PendingPaymentController::class, 'registerPayment'])->whereNumber('appointment');

    Route::get('finance/income', [IncomeController::class, 'index']);
    Route::post('finance/income', [IncomeController::class, 'store']);
    Route::post('finance/payments/{payment}/refund', [IncomeController::class, 'refund'])->whereNumber('payment');
});
