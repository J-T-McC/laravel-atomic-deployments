<?php

namespace JTMcC\AtomicDeployments\Commands;

use Illuminate\Console\Command;

use JTMcC\AtomicDeployments\Services\AtomicDeployment;

class DeployCommand extends Command
{

    protected $signature = 'atomic-deployments:deploy';

    protected $description = 'Deploy build and link web root';

    public function handle()
    {

        $webRoot = config('atomic-deployments.web-root');
        $buildPath = config('atomic-deployments.deployments-path') ?? base_path();
        $deploymentsPath = config('atomic-deployments.deployments-path') ?? base_path('../deployments');

        (new AtomicDeployment($webRoot, $deploymentsPath, $buildPath))->run();

    }


}
