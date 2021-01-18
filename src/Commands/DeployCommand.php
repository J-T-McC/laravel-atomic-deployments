<?php

namespace JTMcC\AtomicDeployments\Commands;

use JTMcC\AtomicDeployments\Services\AtomicDeployments;

use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Services\Output;

use JTMcC\AtomicDeployments\Helpers\ConsoleOutput;

class DeployCommand extends BaseCommand
{

    protected $signature = 'atomic-deployments:deploy 
    {--hash= : Specify a commit hash to deploy }
    {--dry-run : Test and log deployment steps }';

    protected $description = 'Deploy build and link web root';

    public function handle()
    {
        Output::alert("Running Atomic Deployment");

        $buildPath = config('atomic-deployments.build-path');
        $deploymentLink = config('atomic-deployments.deployment-link');
        $deploymentsPath = config('atomic-deployments.deployments-path');

        $atomicDeployment = (new AtomicDeployments(
            $deploymentLink,
            $deploymentsPath,
            $buildPath,
            $this->option('dry-run')
        ));

        $hash = $this->option('hash');

        if ($hash) {
            Output::info("Updating symlink to previous build: {$hash}");

            $deploymentModel = AtomicDeployment::successful()->where('commit_hash', $hash)->orderBy('id', 'desc')->first();

            if (!$deploymentModel || !$deploymentModel->hasDeployment) {
                Output::warn("Build not found for hash: {$hash}");
            } else {
                try {
                    $atomicDeployment->linkDeployment(
                        $deploymentModel->deployment_link,
                        $deploymentModel->deployment_path
                    );
                } catch (\Throwable $e) {
                    $atomicDeployment->rollback();
                }
            }

        } else {
            Output::info('Running Deployment...');
            $atomicDeployment->deploy(fn() => $atomicDeployment->cleanBuilds(config('atomic-deployments.build-limit')));
        }

        Output::info("Finished");
        ConsoleOutput::newLine();
    }


}
