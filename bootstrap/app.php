<?php

use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureProAccess;
use App\Http\Middleware\SetActiveClinic;
use App\Console\Commands\ProcessTrialLifecycle;
use App\Console\Commands\PurgeExpiredClinics;
use Modules\Notifications\Application\Jobs\SendAppointmentReminder24hJob;
use Modules\Notifications\Application\Jobs\SendAppointmentReminder2hJob;
use App\Support\ActivityLogger;
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
            'pro.access' => EnsureProAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $exception): void {
            $statusCode = method_exists($exception, 'getStatusCode')
                ? (int) $exception->getStatusCode()
                : 500;

            if ($statusCode < 500) {
                return;
            }

            try {
                $tenantId = (int) (currentClinicId() ?? 0);
                if ($tenantId <= 0) {
                    return;
                }

                $request = request();
                $path = $request ? (string) $request->path() : '';

                ActivityLogger::log(
                    tenantId: $tenantId,
                    userId: $request?->user()?->id,
                    event: 'system_error_500',
                    description: 'Error interno 500 en la aplicacion',
                    metadata: [
                        'status_code' => $statusCode,
                        'exception' => class_basename($exception),
                        'path' => $path,
                    ],
                    ip: $request?->ip(),
                );
            } catch (Throwable) {
                // Nunca romper el manejo de excepciones por auditoria.
            }
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule
            ->job(new SendAppointmentReminder24hJob())
            ->name('appointments:reminders-24h')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule
            ->job(new SendAppointmentReminder2hJob())
            ->name('appointments:reminders-2h')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule
            ->command(PurgeExpiredClinics::class)
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule
            ->command(ProcessTrialLifecycle::class)
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withEvents(false)
    ->withProviders([
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
    ])->create();
