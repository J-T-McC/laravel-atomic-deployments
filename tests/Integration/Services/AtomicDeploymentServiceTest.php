<?php

namespace Tests\Integration\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTMcC\AtomicDeployments\Events\DeploymentFailed;
use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use Tests\TestCase;

class AtomicDeploymentServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_links_deployment()
    {
        $atomicDeployment = self::getAtomicDeployment();
        $atomicDeployment->linkDeployment();
        $this->assertTrue($atomicDeployment->getDeployment()->isDeployed());
    }

    /**
     * @test
     */
    public function it_registers_previous_deployment_on_boot()
    {
        $atomicDeployment = self::getAtomicDeployment();
        $this->assertTrue(empty($atomicDeployment->getInitialDeploymentPath()));
        $atomicDeployment->linkDeployment();
        $atomicDeployment = self::getAtomicDeployment();
        $this->assertTrue(!empty($atomicDeployment->getInitialDeploymentPath()));
    }

    /**
     * @test
     */
    public function it_creates_a_deployment_directory()
    {
        $atomicDeployment = self::getAtomicDeployment();
        $atomicDeployment->createDeploymentDirectory();
        $this->assertTrue($this->fileSystem->exists($atomicDeployment->getDeployment()->getPath()));
    }

    /**
     * @test
     */
    public function it_copies_deployment_contents_to_deployment_directory()
    {
        $atomicDeployment = self::getAtomicDeployment('abc123');
        $atomicDeployment->createDeploymentDirectory();
        $atomicDeployment->copyDeploymentContents();
        $this->assertTrue($this->fileSystem->exists($atomicDeployment->getDeployment()->getPath().'/build-contents-folder'));
    }

    /**
     * @test
     */
    public function it_updates_deployment_status_record()
    {
        $hash = '123abc';
        $this->assertEmpty(AtomicDeployment::where('commit_hash', $hash)->first());
        $atomicDeployment = self::getAtomicDeployment($hash);
        $atomicDeployment->updateDeploymentStatus(DeploymentStatus::RUNNING);
        $record = AtomicDeployment::where('commit_hash', $hash)->first();
        $this->assertTrue((int) $record->deployment_status === DeploymentStatus::RUNNING);
    }

    /**
     * @test
     */
    public function it_confirms_symbolic_link()
    {
        $hash = '123abc';
        $atomicDeployment = self::getAtomicDeployment($hash);
        $atomicDeployment->linkDeployment();
        $this->assertTrue($atomicDeployment->confirmSymbolicLink());
    }

    /**
     * @test
     */
    public function it_doesnt_allow_deployments_folder_to_be_subdirectory_of_build_folder()
    {
        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->buildPath.'/deployments');
        $this->expectException(InvalidPathException::class);
        $atomicDeployment = self::getAtomicDeployment();
        $atomicDeployment->createDeploymentDirectory();
    }

    /**
     * @test
     */
    public function it_rolls_back_symbolic_link_to_deployment_detected_on_boot()
    {
        $atomicDeployment1 = self::getAtomicDeployment();
        $atomicDeployment1->createDeploymentDirectory();
        $atomicDeployment1->linkDeployment();
        $this->assertTrue($atomicDeployment1->getDeployment()->isDeployed());

        $atomicDeployment2 = self::getAtomicDeployment('abc123');
        $atomicDeployment2->createDeploymentDirectory();
        $atomicDeployment2->linkDeployment();

        $this->assertTrue($atomicDeployment2->getDeployment()->isDeployed());
        $this->assertFalse($atomicDeployment1->getDeployment()->isDeployed());

        $atomicDeployment2->rollback();

        $this->assertTrue($atomicDeployment1->getDeployment()->isDeployed());
    }

    /**
     * @test
     */
    public function it_calls_closure_on_success()
    {
        $this->expectsEvents(DeploymentSuccessful::class);
        $success = false;
        self::getAtomicDeployment()->deploy(function () use (&$success) {
            $success = true;
        });
        $this->assertTrue($success);
    }

    /**
     * @test
     */
    public function it_calls_closure_on_failure()
    {
        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->buildPath.'/deployments');
        $this->expectsEvents(DeploymentFailed::class);
        $this->expectException(InvalidPathException::class);
        $failed = false;
        $atomicDeployment = self::getAtomicDeployment();
        $atomicDeployment->deploy(fn () => '', function () use (&$failed) {
            $failed = true;
        });
        $this->assertTrue($failed);
    }
}
