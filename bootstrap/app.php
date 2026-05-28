<?php

use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\SetActiveClinic;
use App\Console\Commands\ProcessTrialLifecycle;
use App\Console\Commands\PurgeExpiredClinics;
use App\Jobs\SendAppointmentReminder24h;
use App\Jobs\SendAppointmentReminder2h;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prependToGroup('api', SetActiveClinic::class);

        $middleware->alias([
            'clinic' => EnsureClinic::class,
            'clinic.active' => EnsureClinicIsActive::class,
            'check.subscription' => CheckSubscriptionAccess::class,
            'admin.active' => EnsureAdminIsActive::class,
            'admin.role' => EnsureAdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule
            ->call(fn () => app(SendAppointmentReminder24h::class)->handle())
            ->name('appointments:reminders-24h')
            ->hourly()
            ->withoutOverlapping();

        $schedule
            ->call(fn () => app(SendAppointmentReminder2h::class)->handle())
            ->name('appointments:reminders-2h')
            ->everyThirtyMinutes()
            ->withoutOverlapping();

        $schedule
            ->command(PurgeExpiredClinics::class)
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule
            ->command(ProcessTrialLifecycle::class)
            ->everyThirtyMinutes()
            ->withoutOverlapping();
    })
    ->withProviders([
        App\Providers\AuthServiceProvider::class,
    ])->create();
