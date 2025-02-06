<?php

namespace Tests\Integration\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use JTMcC\AtomicDeployments\Events\DeploymentFailed;
use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use Tests\TestCase;

class AtomicDeploymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_deployment()
    {
        // Collect
        $atomicDeployment = self::getAtomicDeployment();

        // Act
        $atomicDeployment->linkDeployment();

        // Assert
        $this->assertTrue($atomicDeployment->getDeployment()->isDeployed());
    }

    public function test_it_registers_previous_deployment_on_boot()
    {
        // Collect
        $atomicDeployment = self::getAtomicDeployment();
        $this->assertTrue(empty($atomicDeployment->getInitialDeploymentPath()));

        // Act
        $atomicDeployment->linkDeployment();
        $atomicDeployment = self::getAtomicDeployment();

        // Assert
        $this->assertTrue(! empty($atomicDeployment->getInitialDeploymentPath()));
    }

    public function test_it_creates_a_deployment_directory()
    {
        // Collect
        $atomicDeployment = self::getAtomicDeployment();

        // Act
        $atomicDeployment->createDeploymentDirectory();

        // Assert
        $this->assertTrue($this->fileSystem->exists($atomicDeployment->getDeployment()->getPath()));
    }

    public function test_it_copies_deployment_contents_to_deployment_directory()
    {
        // Collect
        $atomicDeployment = self::getAtomicDeployment('abc123');
        $atomicDeployment->createDeploymentDirectory();

        // Act
        $atomicDeployment->copyDeploymentContents();

        // Assert
        $this->assertTrue($this->fileSystem->exists($atomicDeployment->getDeployment()->getPath().'/build-contents-folder'));
    }

    public function test_it_updates_deployment_status_record()
    {
        // Collect
        $hash = '123abc';
        $this->assertEmpty(AtomicDeployment::where('commit_hash', $hash)->first());

        // Act
        $atomicDeployment = self::getAtomicDeployment($hash);
        $atomicDeployment->updateDeploymentStatus(DeploymentStatus::RUNNING);

        // Assert
        $record = AtomicDeployment::where('commit_hash', $hash)->first();
        $this->assertTrue($record->deployment_status === DeploymentStatus::RUNNING);
    }

    public function test_it_confirms_symbolic_link()
    {
        // Collect
        $hash = '123abc';

        // Act
        $atomicDeployment = self::getAtomicDeployment($hash);
        $atomicDeployment->linkDeployment();

        // Assert
        $this->assertTrue($atomicDeployment->confirmSymbolicLink());
    }

    public function test_it_doesnt_allow_deployments_folder_to_be_subdirectory_of_build_folder()
    {
        // Collect
        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->buildPath.'/deployments');
        $atomicDeployment = self::getAtomicDeployment();

        // Act
        $this->expectException(InvalidPathException::class);

        // Act
        $atomicDeployment->createDeploymentDirectory();
    }

    public function test_it_rolls_back_symbolic_link_to_deployment_detected_on_boot()
    {
        // Collect
        $atomicDeployment1 = self::getAtomicDeployment();
        $atomicDeployment1->createDeploymentDirectory();
        $atomicDeployment1->linkDeployment();

        $this->assertTrue($atomicDeployment1->getDeployment()->isDeployed());

        $atomicDeployment2 = self::getAtomicDeployment('abc123');
        $atomicDeployment2->createDeploymentDirectory();
        $atomicDeployment2->linkDeployment();

        $this->assertTrue($atomicDeployment2->getDeployment()->isDeployed());
        $this->assertFalse($atomicDeployment1->getDeployment()->isDeployed());

        // Act
        $atomicDeployment2->rollback();

        // Assert
        $this->assertTrue($atomicDeployment1->getDeployment()->isDeployed());
    }

    public function test_it_calls_closure_on_success()
    {
        // Collect
        Event::fake();
        $success = false;

        // Act
        self::getAtomicDeployment()->deploy(function () use (&$success) {
            $success = true;
        });

        // Assert
        $this->assertTrue($success);
        Event::assertDispatched(DeploymentSuccessful::class);
    }

    public function test_it_calls_closure_on_failure()
    {
        // Collect
        Event::fake();
        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->buildPath.'/deployments');
        $failed = false;
        $atomicDeployment = self::getAtomicDeployment();

        $this->expectException(InvalidPathException::class);

        // Act
        $atomicDeployment->deploy(fn () => '', function () use (&$failed) {
            $failed = true;
        });

        // Assert
        $this->assertTrue($failed);
        Event::assertDispatched(DeploymentFailed::class);
    }
}
