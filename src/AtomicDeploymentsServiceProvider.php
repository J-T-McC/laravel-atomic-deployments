<?php

namespace JTMcC\AtomicDeployments;

use Illuminate\Support\ServiceProvider;

use JTMcC\AtomicDeployments\Commands\DeployCommand;

class AtomicDeploymentsServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->registerPublishables();
        $this->registerCommands();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/atomic-deployments.php', 'atomic-deployments');
        $this->registerCommands();
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/atomic-deployments.php' => config_path('atomic-deployments.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/' =>database_path('migrations'),
        ], 'migrations');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DeployCommand::class
            ]);
        }
    }

}
