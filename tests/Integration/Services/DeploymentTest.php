<?php

namespace Tests\Integration\Services;

use Carbon\Carbon;
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
        $deployment = self::getDeployment();
        $deployment->createDirectory();
        $deployment->link();
        $this->assertTrue($deployment->isDeployed());
    }

    /**
     * @test
     */
    public function it_sets_deployment_directory()
    {
        $deployment = self::getDeployment();
        $deployment->setDirectory('abc123');
        $this->assertTrue($deployment->getDirectory() === 'abc123');
    }

    /**
     * @test
     */
    public function it_names_deployment_folder_using_config_directory_naming_git()
    {
        $gitHash = Exec::getGitHash();
        $deployment = self::getDeployment();
        $deployment->createDirectory();
        $this->assertTrue($deployment->getDirectoryName() === $gitHash);
    }

    /**
     * @test
     */
    public function it_names_deployment_folder_using_config_directory_naming_rand()
    {
        $this->app['config']->set('atomic-deployments.directory-naming', 'rand');
        $gitHash = Exec::getGitHash();
        $deployment = self::getDeployment();
        $deployment->createDirectory();
        $this->assertNotEmpty(trim($deployment->getDirectoryName()));
        $this->assertTrue($deployment->getDirectoryName() !== $gitHash);
    }

    /**
     * @test
     */
    public function it_names_deployment_folder_using_config_directory_naming_datetime()
    {
        $this->app['config']->set('atomic-deployments.directory-naming', 'datetime');
        $shouldFind = Carbon::now()->format('Y-m-d_H-i');
        $deployment = self::getDeployment();
        $deployment->createDirectory();
        $this->assertNotEmpty(trim($deployment->getDirectoryName()));
        $this->assertStringContainsString($shouldFind, $deployment->getDirectoryName());
    }

    /**
     * @test
     */
    public function it_sets_deployment_path()
    {
        $deployment = self::getDeployment();
        $deployment->setPath();
        $this->assertNotEmpty(trim($deployment->getPath()));
    }

    /**
     * @test
     */
    public function it_creates_a_directory()
    {
        $deployment = self::getDeployment();
        $deployment->createDirectory();
        $this->assertTrue($this->fileSystem->exists($deployment->getPath()));
    }

    /**
     * @test
     */
    public function it_updates_model_status()
    {
        $deployment = self::getDeployment();
        $deployment->updateStatus(DeploymentStatus::SUCCESS);
        $this->assertTrue((int) AtomicDeployment::first()->deployment_status === DeploymentStatus::SUCCESS);
    }
}
