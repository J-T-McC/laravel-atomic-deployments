<?php

namespace JTMcC\AtomicDeployments\Commands;

use JTMcC\AtomicDeployments\Helpers\ConsoleOutput;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;

class ListCommand extends BaseCommand
{
    protected $signature = 'atomic-deployments:list';

    protected $description = 'List currently available deployments';

    public function handle(): void
    {
        ConsoleOutput::line('');
        ConsoleOutput::alert('Available Deployments');

        $deployments = AtomicDeployment::query()->select([
            'id',
            'commit_hash',
            'deployment_path',
            'deployment_link',
            'deployment_status',
            'created_at',
        ])->get()->map(function (AtomicDeployment $deployment) {
            $deployment->append('is_currently_deployed');
            $deployment->deployment_status = DeploymentStatus::getNameFromValue($deployment->deployment_status);

            return $deployment;
        });

        if (! $deployments->count()) {
            ConsoleOutput::info('No deployments found');

            return;
        }

        $titles = ['ID', 'Commit Hash', 'Path', 'SymLink', 'Status', 'Created', 'Live'];

        ConsoleOutput::table($titles, $deployments);
        ConsoleOutput::line('');
    }
}
