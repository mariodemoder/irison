<?php

use Illuminate\Support\Facades\Route;
/*
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Hash; 

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/dashboard', [DashboardController::class]);
});

require __DIR__.'/auth.php';

Route::get('/hash-test', function () { return Hash::make('HOLISholis123'); });

*/

use App\Http\Controllers\BillingController;

// Rutas públicas/auxiliares para billing (fake provider local)
Route::get('/billing/fake-success', [BillingController::class, 'fakeSuccess'])->name('billing.fake.success');
Route::get('/billing/thankyou', [BillingController::class, 'thankyou'])->name('billing.thankyou');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');


