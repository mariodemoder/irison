<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Finance\Domain\Contracts\BenefitsDataProviderInterface;
use Modules\Finance\Domain\Contracts\ExpenseCategoryRepositoryInterface;
use Modules\Finance\Domain\Contracts\ExpenseRepositoryInterface;
use Modules\Finance\Domain\Contracts\ProfessionalRateRepositoryInterface;
use Modules\Finance\Domain\Services\MarginCalculator;
use Modules\Finance\Infrastructure\Persistence\BenefitsDataProvider;
use Modules\Finance\Infrastructure\Persistence\ExpenseCategoryRepository;
use Modules\Finance\Infrastructure\Persistence\ExpenseRepository;
use Modules\Finance\Infrastructure\Persistence\ProfessionalRateRepository;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExpenseRepositoryInterface::class, ExpenseRepository::class);
        $this->app->bind(ExpenseCategoryRepositoryInterface::class, ExpenseCategoryRepository::class);
        $this->app->bind(ProfessionalRateRepositoryInterface::class, ProfessionalRateRepository::class);
        $this->app->bind(BenefitsDataProviderInterface::class, BenefitsDataProvider::class);
        $this->app->singleton(MarginCalculator::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../Config/finance.php', 'finance');
    }
}