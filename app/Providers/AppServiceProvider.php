<?php

namespace App\Providers;

use App\Models\CashierSubscription;
use App\Models\Clinic;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Clinic::class);
        Cashier::useSubscriptionModel(CashierSubscription::class);
        Schema::defaultStringLength(191);
        // Ensure route-model binding for Patient is scoped to the authenticated user's clinic
        Route::bind('patient', function ($value) {
            $user = Auth::user();
            if (! $user || ! $user->clinic_id) {
                abort(403, 'Access denied');
            }

            return \App\Models\Patient::where('clinic_id', $user->clinic_id)->findOrFail($value);
        });
    }
}
