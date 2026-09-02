<?php

declare(strict_types=1);

namespace Modules\DataImport\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

class DataImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../Config/dataimport.php', 'dataimport');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
    }
}