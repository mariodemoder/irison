<?php

declare(strict_types=1);

namespace Modules\Booking\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Booking\Contracts\AvailabilityCheckerInterface;
use Modules\Booking\Services\AvailabilityEngine;

class BookingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/booking.php', 'booking'
        );
    }

    public function register(): void
    {
        $this->app->bind(
            AvailabilityCheckerInterface::class,
            AvailabilityEngine::class
        );
    }
}
