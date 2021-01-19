<?php

namespace Tests\Integration\Commands;

use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use Tests\TestCase;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AtomicDeploymentsServiceTest extends TestCase
{

    use RefreshDatabase;


    /**
     * @test
     */
    public function it_allows_dry_run_with_no_mutations()
    {
        Artisan::call('atomic-deployments:deploy --dry-run --directory=test-dir');

        $this->seeInConsoleOutput([
            'Deployment directory option set. Deployment will use test-dir',
            'Running Deployment...',
            'Dry run - changes will not be made',
            'Dry run - Skipping deployment status update',
            'Dry run - Skipping creating deployment directory',
            'Dry run - Skipping required directory exists check for:',
            'Dry run - Skipping link comparison',
            'Dry run - Skipping directory sync',
            'Dry run - Skipping symbolic link deployment',
        ]);

        $this->dontSeeInConsoleOutput('Atomic deployment rollback has been requested');

        $this->assertFalse($this->fileSystem->exists($this->deploymentsPath . '/test-dir'));
        $this->assertFalse($this->fileSystem->exists($this->deploymentLink));
        $this->assertEmpty(AtomicDeployment::all());

    }


    /**
     * @test
     */
    public function it_allows_run_with_mutations()
    {
        Artisan::call('atomic-deployments:deploy --directory=test-dir');

        $this->seeInConsoleOutput([
            'Deployment directory option set. Deployment will use test-dir',
            'Running Deployment...',
            'No previous deployment detected for this link',
            'Build link confirmed',
        ]);

        $this->dontSeeInConsoleOutput([
            'Dry run - changes will not be made',
            'Dry run - Skipping deployment status update',
            'Dry run - Skipping creating deployment directory',
            'Dry run - Skipping required directory exists check for:',
            'Dry run - Skipping link comparison',
            'Dry run - Skipping directory sync',
            'Dry run - Skipping symbolic link deployment',
            'Atomic deployment rollback has been requested',
        ]);

        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath . '/test-dir/build-contents-folder'));
        $this->assertTrue($this->fileSystem->exists($this->deploymentLink));

        $deployment = AtomicDeployment::first();
        $this->assertNotEmpty($deployment);
        $this->assertTrue((int)$deployment->deployment_status === DeploymentStatus::SUCCESS);

    }


}

