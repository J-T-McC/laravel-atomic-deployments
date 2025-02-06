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

    public function test_it_links_and_confirms_deployment()
    {
        // Collect
        $deployment = self::getDeployment();

        // Act
        $deployment->createDirectory();
        $deployment->link();

        // Assert
        $this->assertTrue($deployment->isDeployed());
    }

    public function test_it_sets_deployment_directory()
    {
        // Collect
        $deployment = self::getDeployment();

        // Act
        $deployment->setDirectory('abc123');

        // Assert
        $this->assertTrue($deployment->getDirectory() === 'abc123');
    }

    public function test_it_names_deployment_folder_using_config_directory_naming_git()
    {
        // Collect
        $gitHash = Exec::getGitHash();
        $deployment = self::getDeployment();

        // Act
        $deployment->createDirectory();

        // Assert
        $this->assertTrue($deployment->getDirectoryName() === $gitHash);
    }

    public function test_it_names_deployment_folder_using_config_directory_naming_rand()
    {
        // Collect
        $this->app['config']->set('atomic-deployments.directory-naming', 'rand');
        $gitHash = Exec::getGitHash();
        $deployment = self::getDeployment();

        // Act
        $deployment->createDirectory();

        // Assert
        $this->assertNotEmpty(trim($deployment->getDirectoryName()));
        $this->assertTrue($deployment->getDirectoryName() !== $gitHash);
    }

    public function test_it_names_deployment_folder_using_config_directory_naming_datetime()
    {
        // Collect
        $this->app['config']->set('atomic-deployments.directory-naming', 'datetime');
        $shouldFind = Carbon::now()->format('Y-m-d_H-i');
        $deployment = self::getDeployment();

        // Act
        $deployment->createDirectory();

        // Assert
        $this->assertNotEmpty(trim($deployment->getDirectoryName()));
        $this->assertStringContainsString($shouldFind, $deployment->getDirectoryName());
    }

    public function test_it_sets_deployment_path()
    {
        // Collect
        $deployment = self::getDeployment();

        // Act
        $deployment->setPath();

        // Assert
        $this->assertNotEmpty(trim($deployment->getPath()));
    }

    public function test_it_creates_a_directory()
    {
        // Collect
        $deployment = self::getDeployment();

        // Act
        $deployment->createDirectory();

        // Assert
        $this->assertTrue($this->fileSystem->exists($deployment->getPath()));
    }

    public function test_it_updates_model_status()
    {
        // Collect
        $deployment = self::getDeployment();

        // Act
        $deployment->updateStatus(DeploymentStatus::SUCCESS);

        // Assert
        $this->assertTrue((int)AtomicDeployment::first()->deployment_status === DeploymentStatus::SUCCESS);
    }
}