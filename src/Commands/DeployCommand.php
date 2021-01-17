<?php

namespace JTMcC\AtomicDeployments\Commands;

use Illuminate\Console\Command;

use JTMcC\AtomicDeployments\Services\AtomicDeployment;

class DeployCommand extends Command
{

    protected $signature = 'atomic-deployments:deploy {--test : Test and log deployment steps }';

    protected $description = 'Deploy build and link web root';

    public function handle()
    {

        $webRoot = config('atomic-deployments.web-root');
        $buildPath = config('atomic-deployments.build-path');
        $deploymentsPath = config('atomic-deployments.deployments-path');

        (new AtomicDeployment(
            $webRoot,
            $deploymentsPath,
            $buildPath,
            $this->option('test')
        ))->run();

    }


}
