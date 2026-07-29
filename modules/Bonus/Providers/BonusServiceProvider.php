<?php

declare(strict_types=1);

namespace Modules\Bonus\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Bonus\Contracts\BonusConsumableInterface;
use Modules\Bonus\Services\BonusService;

class BonusServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/bonus.php', 'bonus'
        );
    }

    public function register(): void
    {
        $this->app->bind(
            BonusConsumableInterface::class,
            BonusService::class
        );
    }
}
