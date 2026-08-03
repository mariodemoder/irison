<?php

declare(strict_types=1);

namespace Modules\Activity\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Activity\Domain\Contracts\ActivityRepositoryInterface;
use Modules\Activity\Infrastructure\Persistence\ActivityRepository;

class ActivityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityRepositoryInterface::class, ActivityRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
    }
}