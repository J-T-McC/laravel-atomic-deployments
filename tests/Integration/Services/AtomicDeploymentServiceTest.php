<?php

namespace Tests\Integration\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertTrue($this->fileSystem->exists($atomicDeployment->getDeployment()->getDeploymentPath()));
    }

    /**
     * @test
     */
    public function it_copies_deployment_contents_to_deployment_directory()
    {
        $atomicDeployment = self::getAtomicDeployment('abc123');
        $atomicDeployment->createDeploymentDirectory();
        $atomicDeployment->copyDeploymentContents();
        $this->assertTrue($this->fileSystem->exists($atomicDeployment->getDeployment()->getDeploymentPath().'/build-contents-folder'));
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
}
