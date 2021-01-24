<?php

namespace Tests\Integration\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use JTMcC\AtomicDeployments\Services\Exec;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_links_and_confirms_deployment()
    {
        $atomicDeployment = self::getDeployment();
        $atomicDeployment->createDirectory();
        $atomicDeployment->linkDeployment();
        $this->assertTrue($atomicDeployment->isDeployed());
    }

    /**
     * @test
     */
    public function it_sets_deployment_directory() {
        $atomicDeployment = self::getDeployment();
        $atomicDeployment->setDeploymentDirectory('abc123');
        $this->assertTrue( $atomicDeployment->getDeploymentDirectory() === 'abc123');
    }

    /**
     * @test
     */
    public function it_names_deployment_folder_using_config_directory_naming_git() {
        $gitHash = Exec::getGitHash();
        $atomicDeployment = self::getDeployment();
        $atomicDeployment->createDirectory();
        $this->assertTrue($atomicDeployment->getDirectoryName() === $gitHash);
    }

    /**
     * @test
     */
    public function it_names_deployment_folder_using_config_directory_naming_rand() {
        $this->app['config']->set('atomic-deployments.directory-naming', 'rand');
        $gitHash = Exec::getGitHash();
        $atomicDeployment = self::getDeployment();
        $atomicDeployment->createDirectory();
        $this->assertNotEmpty(trim($atomicDeployment->getDirectoryName()));
        $this->assertTrue($atomicDeployment->getDirectoryName() !== $gitHash);
    }

    /**
     * @test
     */
    public function it_sets_deployment_path() {
        $atomicDeployment = self::getDeployment();
        $atomicDeployment->setDeploymentPath();
        $this->assertNotEmpty(trim($atomicDeployment->getDeploymentPath()));
    }

    /**
     * @test
     */
    public function it_creates_a_directory() {
        $atomicDeployment = self::getDeployment();
        $atomicDeployment->createDirectory();
        $this->assertTrue($this->fileSystem->exists($atomicDeployment->getDeploymentPath()));
    }

    /**
     * @test
     */
    public function it_updates_model_status() {
        $atomicDeployment = self::getDeployment();
        $atomicDeployment->updateDeploymentStatus(DeploymentStatus::SUCCESS);
        $this->assertTrue((int)AtomicDeployment::first()->deployment_status === DeploymentStatus::SUCCESS);
    }

}
