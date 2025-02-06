<?php

namespace JTMcC\AtomicDeployments;

use Illuminate\Support\ServiceProvider;
use JTMcC\AtomicDeployments\Commands\DeployCommand;
use JTMcC\AtomicDeployments\Commands\ListCommand;
use JTMcC\AtomicDeployments\Interfaces\DeploymentInterface;
use JTMcC\AtomicDeployments\Services\AtomicDeploymentService;

class AtomicDeploymentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atomic-deployments.php', 'atomic-deployments');
        $this->registerPublishables();
        $this->registerCommands();

        $this->app->bind(DeploymentInterface::class, config('atomic-deployments.deployment-class'));

        $this->app->bind(AtomicDeploymentService::class, function ($app, $params) {
            if (empty($params) || (count($params) && ! is_a($params[0], DeploymentInterface::class))) {
                array_unshift($params, $app->make(DeploymentInterface::class));
            }

            return new AtomicDeploymentService(...$params);
        });
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__.'/../config/atomic-deployments.php' => config_path('atomic-deployments.php'),
        ], 'atm-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'atm-migrations');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DeployCommand::class,
                ListCommand::class,
            ]);
        }
    }
}
