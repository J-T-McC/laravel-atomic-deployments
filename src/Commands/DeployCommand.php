<?php

namespace JTMcC\AtomicDeployments\Commands;

use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Helpers\ConsoleOutput;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Services\AtomicDeploymentService;
use JTMcC\AtomicDeployments\Services\Deployment;
use JTMcC\AtomicDeployments\Services\Output;

class DeployCommand extends BaseCommand
{
    protected $signature = 'atomic-deployments:deploy 
    {--hash= : Specify a previous deployments commit hash/deploy-dir to deploy }
    {--directory= : Define your deploy folder name. Defaults to current HEAD hash }
    {--dry-run : Test and log deployment steps }';

    protected $description = 'Deploy a clone of your latest build and attach symlink';

    public function handle(): void
    {
        Output::alert('Running Atomic Deployment');

        $migrate = config('atomic-deployments.migrate', []);
        $dryRun = $this->option('dry-run');

        if ($hash = $this->option('hash')) {
            Output::info("Updating symlink to previous build: {$hash}");

            $deploymentModel = AtomicDeployment::successful()->where('commit_hash', $hash)->first();

            /** @var AtomicDeployment $deploymentModel */
            if (! $deploymentModel || ! $deploymentModel->has_deployment) {
                Output::warn("Build not found for hash: {$hash}");
            } else {
                $atomicDeployment = AtomicDeploymentService::create(
                    new Deployment($deploymentModel),
                    $migrate,
                    $dryRun
                );

                try {
                    $atomicDeployment->getDeployment()->link();
                    $atomicDeployment->confirmSymbolicLink();
                    DeploymentSuccessful::dispatch($atomicDeployment, $deploymentModel);
                } catch (\Throwable $e) {
                    $atomicDeployment->fail();
                    Output::throwable($e);
                }
            }
        } else {
            $atomicDeployment = AtomicDeploymentService::create($migrate, $dryRun);

            Output::info('Running Deployment...');

            try {
                if ($deployDir = trim($this->option('directory'))) {
                    Output::info("Deployment directory option set - Deployment will use directory: {$deployDir} ");
                    $atomicDeployment->getDeployment()->setDirectory($deployDir);
                }
                $atomicDeployment->deploy(fn () => $atomicDeployment->cleanBuilds(config('atomic-deployments.build-limit')));
            } catch (\Throwable $e) {
                $atomicDeployment->fail();
                Output::throwable($e);
            }
        }

        Output::info('Finished');
        ConsoleOutput::line('');
    }
}
