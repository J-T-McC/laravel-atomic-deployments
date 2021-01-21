<?php

namespace Tests\Integration\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use JTMcC\AtomicDeployments\Services\AtomicDeployments;
use Tests\TestCase;

class AtomicDeploymentsServiceTest extends TestCase
{
    use RefreshDatabase;

    public ?AtomicDeployments $atomicDeployments = null;

    public function getAtomicDeployment($dryRun = false)
    {
        return new AtomicDeployments(
            $this->deploymentLink,
            $this->deploymentsPath,
            $this->buildPath,
            [],
            $dryRun
        );
    }

    public function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
    }

    /**
     * @test
     */
    public function it_sets_and_gets_symbolic_deploymeny_link()
    {
        $atomicDeployment = $this->getAtomicDeployment();
        $atomicDeployment->linkDeployment($this->deploymentLink, $this->deploymentsPath);
        $this->assertTrue($atomicDeployment->getCurrentDeploymentPath() === $this->deploymentsPath);
    }

    /**
     * @test
     */
    public function it_registers_previous_deployment_on_boot()
    {
        $atomicDeployment = $this->getAtomicDeployment();
        $atomicDeployment->linkDeployment($this->deploymentLink, $this->deploymentsPath);
        $atomicDeployment = null;
        $atomicDeployment = $this->getAtomicDeployment();
        $this->assertTrue($atomicDeployment->getCurrentDeploymentPath() === $this->deploymentsPath);
    }

    /**
     * @test
     */
    public function it_creates_atomic_deployment_database_record()
    {
        $hash = '123abc';
        $atomicDeployment = $this->getAtomicDeployment();
        $atomicDeployment->setDeploymentDirectory($hash);
        $atomicDeployment->setDeploymentPath();
        $atomicDeployment->updateDeploymentStatus(DeploymentStatus::RUNNING);
        $record = AtomicDeployment::where('commit_hash', $hash)->first();
        $this->assertTrue((int) $record->deployment_status === DeploymentStatus::RUNNING);
    }

    /**
     * @test
     */
    public function it_updates_deployment_status_record()
    {
        $hash = '123abc';
        $atomicDeployment = $this->getAtomicDeployment();
        $atomicDeployment->setDeploymentDirectory($hash);
        $atomicDeployment->setDeploymentPath();
        $atomicDeployment->updateDeploymentStatus(DeploymentStatus::RUNNING);
        $record = AtomicDeployment::where('commit_hash', $hash)->first();
        $this->assertTrue((int) $record->deployment_status === DeploymentStatus::RUNNING);
        $atomicDeployment->updateDeploymentStatus(DeploymentStatus::SUCCESS);
        $record = AtomicDeployment::where('commit_hash', $hash)->first();
        $this->assertTrue((int) $record->deployment_status === DeploymentStatus::SUCCESS);
    }

    /**
     * @test
     */
    public function it_confirms_symbolic_link()
    {
        $hash = '123abc';
        $atomicDeployment = $this->getAtomicDeployment();
        $atomicDeployment->setDeploymentDirectory($hash);
        $atomicDeployment->setDeploymentPath();
        $atomicDeployment->linkDeployment($this->deploymentLink, $this->deploymentsPath);
        $this->expectException(ExecuteFailedException::class);
        $atomicDeployment->confirmSymbolicLink('this-should-fail');
        $this->assertTrue($atomicDeployment->confirmSymbolicLink($atomicDeployment->getDeploymentPath()));
    }

    /**
     * @test
     */
    public function it_doesnt_allow_deployments_folder_to_be_subdirectory_of_build_folder()
    {
        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->buildPath.'/deployments');

        $this->expectException(InvalidPathException::class);

        $hash = '123abc';
        $atomicDeployment = new AtomicDeployments(
            $this->deploymentLink,
            $this->buildPath.'/deployments',
            $this->buildPath,
            [],
            true
        );
        $atomicDeployment->setDeploymentDirectory($hash);
        $atomicDeployment->setDeploymentPath();
        $atomicDeployment->createDeploymentDirectory();
    }
}
