<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Infrastructure\Persistence\ReminderRepository;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ReminderRepositoryInterface::class,
            ReminderRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../Config/notifications.php', 'notifications');
        $this->mergeConfigFrom(__DIR__ . '/../../Config/email_logs.php', 'notifications.email_logs');
    }
}
