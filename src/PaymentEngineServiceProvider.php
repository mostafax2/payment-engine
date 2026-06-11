<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine;

use Illuminate\Support\ServiceProvider;
use Mostafax\PaymentEngine\Console\Commands\ReconcileCommand;
use Mostafax\PaymentEngine\Console\Commands\RecoverMissingCommand;
use Mostafax\PaymentEngine\Console\Commands\SyncPaymentsCommand;
use Mostafax\PaymentEngine\Contracts\ReconciliationInterface;
use Mostafax\PaymentEngine\Contracts\TransactionRepositoryInterface;
use Mostafax\PaymentEngine\Contracts\WebhookHandlerInterface;
use Mostafax\PaymentEngine\Repositories\TransactionRepository;
use Mostafax\PaymentEngine\Repositories\WebhookRepository;
use Mostafax\PaymentEngine\Services\BackfillEngine;
use Mostafax\PaymentEngine\Services\PaymentManager;
use Mostafax\PaymentEngine\Services\ReconciliationEngine;
use Mostafax\PaymentEngine\Services\RecoveryEngine;
use Mostafax\PaymentEngine\Services\WebhookProcessor;

final class PaymentEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payment-engine.php', 'payment-engine');

        $this->app->singleton(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->singleton(WebhookHandlerInterface::class,        WebhookProcessor::class);
        $this->app->singleton(ReconciliationInterface::class,        ReconciliationEngine::class);

        $this->app->singleton(TransactionRepository::class);
        $this->app->singleton(WebhookRepository::class);
        $this->app->singleton(PaymentManager::class);
        $this->app->singleton(WebhookProcessor::class);
        $this->app->singleton(ReconciliationEngine::class);
        $this->app->singleton(RecoveryEngine::class);
        $this->app->singleton(BackfillEngine::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-engine');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payment-engine.php' => config_path('payment-engine.php'),
            ], 'payment-engine-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'payment-engine-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/payment-engine'),
            ], 'payment-engine-views');

            $this->commands([
                SyncPaymentsCommand::class,
                ReconcileCommand::class,
                RecoverMissingCommand::class,
            ]);
        }
    }
}
