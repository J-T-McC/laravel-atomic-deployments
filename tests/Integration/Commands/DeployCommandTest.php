<?php

namespace Tests\Integration\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use JTMcC\AtomicDeployments\Events\DeploymentFailed;
use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use Tests\TestCase;

class DeployCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_allows_dry_run_with_no_mutations()
    {
        Artisan::call('atomic-deployments:deploy --dry-run --directory=test-dir-1');

        $this->seeInConsoleOutput([
            'Deployment directory option set. Deployment will use test-dir-1',
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

        $this->assertFalse($this->fileSystem->exists($this->deploymentsPath.'/test-dir-1/'));
        $this->assertFalse($this->fileSystem->exists($this->deploymentLink));
        $this->assertEmpty(AtomicDeployment::all());
    }

    /**
     * @test
     */
    public function it_does_not_migrate_on_dry_run()
    {
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');

        //add file to 'deployment' that does not exist in 'build' for migrate test
        $this->fileSystem->ensureDirectoryExists($this->deploymentsPath.'/test-dir-1/migration/test-folder');

        Artisan::call('atomic-deployments:deploy --dry-run --directory=test-dir-2');

        $this->seeInConsoleOutput([
            'Deployment directory option set. Deployment will use test-dir-2',
            'Running Deployment...',
            'Dry run - changes will not be made',
            'Dry run - skipping migrations',
        ]);

        $this->dontSeeInConsoleOutput('Atomic deployment rollback has been requested');

        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath.'/test-dir-1/migration/test-folder'));
        $this->assertFalse($this->fileSystem->exists($this->deploymentsPath.'/test-dir-2/migration/test-folder'));
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
            'Dry run',
            'Atomic deployment rollback has been requested',
        ]);

        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath.'/test-dir/build-contents-folder'));
        $this->assertTrue($this->fileSystem->exists($this->deploymentLink));

        $deployment = AtomicDeployment::first();
        $this->assertNotEmpty($deployment);
        $this->assertTrue((int) $deployment->deployment_status === DeploymentStatus::SUCCESS);
    }

    /**
     * @test
     */
    public function it_allows_migrate_on_run()
    {
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');

        //add file to 'deployment' that does not exist in 'build' for migrate test
        $this->fileSystem->ensureDirectoryExists($this->deploymentsPath.'/test-dir-1/migration/test-folder');

        $this->assertFalse($this->fileSystem->exists($this->deploymentsPath.'/test-dir-2/migration/test-folder'));

        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');

        $this->seeInConsoleOutput([
            'Deployment directory option set. Deployment will use test-dir-2',
            'Running Deployment...',
            'Running migration for pattern migration/*',
            'Finished migration for pattern migration/*',
        ]);

        $this->dontSeeInConsoleOutput('Atomic deployment rollback has been requested');

        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath.'/test-dir-1/migration/test-folder'));

        //confirm migrate logic copied test folder
        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath.'/test-dir-2/migration/test-folder'));
    }

    /**
     * @test
     */
    public function it_allows_swapping_between_deployments()
    {

        //create two builds
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');

        $deployment1 = AtomicDeployment::where('commit_hash', 'test-dir-1')->first()->append('isCurrentlyDeployed')->toArray();
        $deployment2 = AtomicDeployment::where('commit_hash', 'test-dir-2')->first()->append('isCurrentlyDeployed')->toArray();

        //confirm our last build is currently deployed
        $this->assertFalse($deployment1['isCurrentlyDeployed']);
        $this->assertTrue($deployment2['isCurrentlyDeployed']);

        Artisan::call('atomic-deployments:deploy --hash=test-dir-fake');

        //confirm build must exist when attempting to swap
        $this->seeInConsoleOutput([
            'Updating symlink to previous build: test-dir-fake',
            'Build not found for hash: test-dir-fake',
        ]);

        Artisan::call('atomic-deployments:deploy --hash=test-dir-1');

        //swap build to our first deployment
        $this->seeInConsoleOutput([
            'Updating symlink to previous build: test-dir-1',
            'Link created',
        ]);

        $deployment1 = AtomicDeployment::where('commit_hash', 'test-dir-1')->first()->append('isCurrentlyDeployed')->toArray();
        $deployment2 = AtomicDeployment::where('commit_hash', 'test-dir-2')->first()->append('isCurrentlyDeployed')->toArray();

        //confirm first deployment is now live and second is not
        $this->assertTrue($deployment1['isCurrentlyDeployed']);
        $this->assertFalse($deployment2['isCurrentlyDeployed']);
    }

    /**
     * @test
     */
    public function it_cleans_old_build_folders_based_on_build_limit()
    {
        $this->app['config']->set('atomic-deployments.build-limit', 1);

        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');

        $this->assertTrue(AtomicDeployment::all()->count() === 1);
        $this->assertTrue(AtomicDeployment::withTrashed()->get()->count() === 2);

        AtomicDeployment::truncate();

        $this->app['config']->set('atomic-deployments.build-limit', 3);

        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-3');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-4');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-5');

        $this->assertTrue(AtomicDeployment::all()->count() === 3);
        $this->assertTrue(AtomicDeployment::withTrashed()->get()->count() === 5);
    }

    /**
     * @test
     */
    public function it_dispatches_deployment_successful_event_on_build()
    {
        $this->expectsEvents(DeploymentSuccessful::class);
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
    }

    /**
     * @test
     */
    public function it_dispatches_deployment_successful_event_on_deployment_swap()
    {
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');

        $deployment = AtomicDeployment::where('commit_hash', 'test-dir-2')->first()->append('isCurrentlyDeployed')->toArray();
        $this->assertTrue($deployment['isCurrentlyDeployed']);

        $this->expectsEvents(DeploymentSuccessful::class);

        Artisan::call('atomic-deployments:deploy --hash=test-dir-1');
        $deployment = AtomicDeployment::where('commit_hash', 'test-dir-1')->first()->append('isCurrentlyDeployed')->toArray();
        $this->assertTrue($deployment['isCurrentlyDeployed']);
    }

    /**
     * @test
     */
    public function it_dispatches_deployment_failed_event_on_build_fail()
    {
        //force invalid path exception
        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->buildPath.'/deployments');
        $this->expectsEvents(DeploymentFailed::class);
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
    }
}
