<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

class SubscriptionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../Routes/api.php');
        $this->mergeConfigFrom(__DIR__.'/../../Config/billing.php', 'billing');
    }
}
