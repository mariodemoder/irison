<?php

namespace App\Providers;

use App\Http\View\Composers\BackofficeNotificationsComposer;
use App\Models\CashierSubscription;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
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

        View::composer('backoffice.layout', BackofficeNotificationsComposer::class);

        ResetPassword::createUrlUsing(function (object $user, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return $frontendUrl
                . '/reset-password?token=' . urlencode($token)
                . '&email=' . urlencode((string) ($user->email ?? ''));
        });

        // Gate: acceso al módulo Team para administradores, gestores y dueños de clínica
        Gate::define('team-access', function (User $user) {
            return ($user->profile && in_array($user->profile->slug, ['admin', 'manager'], true))
                || $user->role === 'owner';
        });

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
