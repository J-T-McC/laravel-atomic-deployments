<?php

namespace JTMcC\AtomicDeployments\Commands;

use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Helpers\ConsoleOutput;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Services\AtomicDeployments;
use JTMcC\AtomicDeployments\Services\Output;

class DeployCommand extends BaseCommand
{
    protected $signature = 'atomic-deployments:deploy 
    {--hash= : Specify a previous deployments commit hash/deploy-dir to deploy }
    {--directory= : Define your deploy folder name. Defaults to current HEAD hash }
    {--dry-run : Test and log deployment steps }';

    protected $description = 'Deploy a clone of your latest build and attach symlink';

    public function handle()
    {
        Output::alert('Running Atomic Deployment');

        $buildPath = config('atomic-deployments.build-path');
        $deploymentLink = config('atomic-deployments.deployment-link');
        $deploymentsPath = config('atomic-deployments.deployments-path');
        $migrate = config('atomic-deployments.migrate', []);

        $atomicDeployment = (new AtomicDeployments(
            $deploymentLink,
            $deploymentsPath,
            $buildPath,
            $migrate,
            $this->option('dry-run')
        ));

        if ($hash = $this->option('hash')) {
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
                    $atomicDeployment->confirmSymbolicLink($deploymentModel->deployment_path);
                    DeploymentSuccessful::dispatch($atomicDeployment, $deploymentModel);
                } catch (\Throwable $e) {
                    Output::throwable($e);
                    $atomicDeployment->rollback();
                }
            }
        } else {
            Output::info('Running Deployment...');

            if ($deployDir = trim($this->option('directory'))) {
                Output::info("Deployment directory option set. Deployment will use {$deployDir}");
                $atomicDeployment->setDeploymentDirectory($deployDir);
            }

            $atomicDeployment->deploy(fn () => $atomicDeployment->cleanBuilds(config('atomic-deployments.build-limit')));
        }

        Output::info('Finished');
        ConsoleOutput::line('');
    }
}
