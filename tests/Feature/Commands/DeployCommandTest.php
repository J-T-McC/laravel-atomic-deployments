<?php

namespace Tests\Integration\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use JTMcC\AtomicDeployments\Events\DeploymentFailed;
use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use Tests\TestCase;

class DeployCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_dry_run_with_no_mutations()
    {
        // Act
        Artisan::call('atomic-deployments:deploy --dry-run --directory=test-dir-1');

        // Assert
        $this->seeInConsoleOutput([
            'Deployment directory option set - Deployment will use directory: test-dir-1',
            'Running Deployment...',
            'Dry run - changes will not be made',
            'Dry run - Skipping deployment status update',
            'Dry run - Skipping creating deployment directory',
            'Dry run - Skipping link comparison',
            'Dry run - Skipping directory sync',
            'Dry run - Skipping symbolic link deployment',
        ]);

        $this->dontSeeInConsoleOutput('Atomic deployment rollback has been requested');
        $this->assertFalse($this->fileSystem->exists($this->deploymentsPath . '/test-dir-1/'));
        $this->assertFalse($this->fileSystem->exists($this->deploymentLink));
        $this->assertEmpty(AtomicDeployment::all());
    }

    public function test_it_does_not_migrate_on_dry_run()
    {
        // Collect
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        $this->fileSystem->ensureDirectoryExists($this->deploymentsPath . '/test-dir-1/migration/test-folder');

        // Act
        Artisan::call('atomic-deployments:deploy --dry-run --directory=test-dir-2');

        // Assert
        $this->seeInConsoleOutput([
            'Deployment directory option set - Deployment will use directory: test-dir-2',
            'Running Deployment...',
            'Dry run - changes will not be made',
            'Dry run - skipping migrations',
        ]);

        $this->dontSeeInConsoleOutput('Atomic deployment rollback has been requested');
        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath . '/test-dir-1/migration/test-folder'));
        $this->assertFalse($this->fileSystem->exists($this->deploymentsPath . '/test-dir-2/migration/test-folder'));
    }

    public function test_it_allows_run_with_mutations()
    {
        // Act
        Artisan::call('atomic-deployments:deploy --directory=test-dir');

        // Assert
        $this->seeInConsoleOutput([
            'Deployment directory option set - Deployment will use directory: test-dir',
            'Running Deployment...',
            'No previous deployment detected for this link',
            'Build link confirmed',
        ]);

        $this->dontSeeInConsoleOutput([
            'Dry run',
            'Atomic deployment rollback has been requested',
        ]);

        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath . '/test-dir/build-contents-folder'));
        $this->assertTrue($this->fileSystem->exists($this->deploymentLink));

        $deployment = AtomicDeployment::first();
        $this->assertNotEmpty($deployment);
        $this->assertTrue((int)$deployment->deployment_status === DeploymentStatus::SUCCESS);
    }

    public function test_it_allows_migrate_on_run()
    {
        // Collect
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        $this->fileSystem->ensureDirectoryExists($this->deploymentsPath . '/test-dir-1/migration/test-folder');
        $this->assertFalse($this->fileSystem->exists($this->deploymentsPath . '/test-dir-2/migration/test-folder'));

        // Act
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');

        // Assert
        $this->seeInConsoleOutput([
            'Deployment directory option set - Deployment will use directory: test-dir-2',
            'Running Deployment...',
            'Running migration for pattern migration/*',
            'Finished migration for pattern migration/*',
        ]);

        $this->dontSeeInConsoleOutput('Atomic deployment rollback has been requested');
        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath . '/test-dir-1/migration/test-folder'));
        $this->assertTrue($this->fileSystem->exists($this->deploymentsPath . '/test-dir-2/migration/test-folder'));
    }

    public function test_it_allows_swapping_between_deployments()
    {
        // Collect
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');
        $deployment1 = AtomicDeployment::where('commit_hash', 'test-dir-1')->first()->append(
            'isCurrentlyDeployed'
        )->toArray();
        $deployment2 = AtomicDeployment::where('commit_hash', 'test-dir-2')->first()->append(
            'isCurrentlyDeployed'
        )->toArray();

        $this->assertFalse($deployment1['isCurrentlyDeployed']);
        $this->assertTrue($deployment2['isCurrentlyDeployed']);

        // Act
        Artisan::call('atomic-deployments:deploy --hash=test-dir-fake');

        // Assert
        $this->seeInConsoleOutput([
            'Updating symlink to previous build: test-dir-fake',
            'Build not found for hash: test-dir-fake',
        ]);

        // Act
        Artisan::call('atomic-deployments:deploy --hash=test-dir-1');

        // Assert
        $this->seeInConsoleOutput([
            'Updating symlink to previous build: test-dir-1',
            'Build link confirmed',
        ]);

        $deployment1 = AtomicDeployment::where('commit_hash', 'test-dir-1')->first()->append(
            'isCurrentlyDeployed'
        )->toArray();
        $deployment2 = AtomicDeployment::where('commit_hash', 'test-dir-2')->first()->append(
            'isCurrentlyDeployed'
        )->toArray();

        $this->assertTrue($deployment1['isCurrentlyDeployed']);
        $this->assertFalse($deployment2['isCurrentlyDeployed']);
    }

    public function test_it_cleans_old_build_folders_based_on_build_limit()
    {
        // Collect
        $this->app['config']->set('atomic-deployments.build-limit', 3);

        // Act
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-3');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-4');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-5');

        // Assert
        $this->assertTrue(AtomicDeployment::all()->count() === 3);
        $this->assertTrue(AtomicDeployment::withTrashed()->get()->count() === 5);
    }

    public function test_it_dispatches_deployment_successful_event_on_build()
    {
        // Collect
        Event::fake();

        // Act
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');

        // Assert
        Event::assertDispatched(DeploymentSuccessful::class);
    }

    public function test_it_dispatches_deployment_successful_event_on_deployment_swap()
    {
        // Collect
        Event::fake();
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');
        $deployment = AtomicDeployment::where('commit_hash', 'test-dir-2')->first()->append(
            'isCurrentlyDeployed'
        )->toArray();
        $this->assertTrue($deployment['isCurrentlyDeployed']);

        // Act
        Artisan::call('atomic-deployments:deploy --hash=test-dir-1');

        // Assert
        $deployment = AtomicDeployment::where('commit_hash', 'test-dir-1')->first()->append(
            'isCurrentlyDeployed'
        )->toArray();
        $this->assertTrue($deployment['isCurrentlyDeployed']);
        Event::assertDispatched(DeploymentSuccessful::class);
    }

    public function test_it_dispatches_deployment_failed_event_on_build_fail()
    {
        // Collect
        Event::fake();
        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->buildPath . '/deployments');

        // Assert
        $this->expectException(InvalidPathException::class);

        // Act
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');

        // Assert
        Event::assertDispatched(DeploymentFailed::class);
    }
}