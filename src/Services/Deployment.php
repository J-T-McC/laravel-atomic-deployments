<?php

declare(strict_types=1);

namespace JTMcC\AtomicDeployments\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Helpers\FileHelper;
use JTMcC\AtomicDeployments\Interfaces\DeploymentInterface;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;

class Deployment implements DeploymentInterface
{
    protected AtomicDeployment $model;

    protected string $buildPath;
    protected string $deploymentLink;
    protected string $deploymentsPath;
    protected string $directoryNaming;

    protected string $deploymentPath = '';
    protected string $deploymentDirectory = '';

    /**
     * Deployment constructor.
     *
     * @param AtomicDeployment $model
     */
    public function __construct(AtomicDeployment $model)
    {
        $this->deploymentLink = config('atomic-deployments.deployment-link');
        $this->deploymentsPath = config('atomic-deployments.deployments-path');
        $this->buildPath = config('atomic-deployments.build-path');
        $this->directoryNaming = config('atomic-deployments.directory-naming');
        $this->model = $model;

        if ($this->model->deployment_path) {
            $this->buildPath = $model->build_path;
            $this->deploymentLink = $model->deployment_link;
            $this->deploymentPath = $model->deployment_path;
            $this->deploymentDirectory = $model->commit_hash;
        }
    }

    /**
     * Create/Overwrite Symbolic link for live deployment.
     *
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function linkDeployment(): void
    {
        Exec::ln($this->deploymentLink, $this->getDeploymentPath());
    }

    /***
     * @param string $name
     *
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function setDeploymentDirectory(string $name = ''): void
    {
        $this->deploymentDirectory = trim($name);

        //update deployment path to use new directory
        $this->setDeploymentPath();
    }


    public function getDeploymentDirectory(): string
    {
        return $this->deploymentDirectory;
    }


    /**
     * Get the current symlinked deployment path.
     *
     * @throws ExecuteFailedException
     *
     * @return string
     */
    public function getCurrentDeploymentPath(): string
    {
        $result = Exec::readlink($this->deploymentLink);
        if ($result === $this->deploymentLink) {
            return '';
        }

        return $result;
    }

    /**
     * @return string
     * @throws ExecuteFailedException
     */
    public function getDirectoryName() {
        switch($this->directoryNaming) {
            case 'rand':
                return Str::random(5) . time();
            case 'git':
            default:
                return Exec::getGitHash();
        }
    }

    /**
     * Sets full path for deployment.
     *
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function setDeploymentPath(): void
    {
        if (empty(trim($this->deploymentDirectory))) {
            $this->setDeploymentDirectory($this->getDirectoryName());
        }

        if (strpos($this->deploymentsPath, $this->buildPath) !== false) {
            throw new InvalidPathException('Deployment folder cannot be subdirectory of build folder');
        }

        $this->deploymentPath = implode(DIRECTORY_SEPARATOR, [$this->deploymentsPath, $this->deploymentDirectory]);
    }

    /**
     * Get full path for deployment.
     *
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     *
     * @return string
     */
    public function getDeploymentPath(): string
    {
        if (empty($this->deploymentPath)) {
            $this->setDeploymentPath();
        }

        return $this->deploymentPath;
    }

    /**
     * @param int $status
     *
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function updateDeploymentStatus(int $status): void
    {
        $this->model->updateOrCreate(
            ['deployment_path' => $this->getDeploymentPath()],
            [
                'commit_hash'       => $this->deploymentDirectory,
                'build_path'        => $this->buildPath,
                'deployment_link'   => $this->deploymentLink,
                'deployment_status' => $status,
            ]
        );
    }

    /**
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function copyContents()
    {
        FileHelper::confirmPathsExist(
            $this->buildPath,
            $this->deploymentPath
        );

        Exec::rsync("{$this->buildPath}/", "{$this->deploymentPath}/");
    }

    /**
     * @return AtomicDeployment
     */
    public function getModel(): AtomicDeployment
    {
        return $this->model;
    }

    /**
     * @return string
     */
    public function getBuildPath(): string
    {
        return $this->buildPath;
    }

    /**
     * @return string
     */
    public function getDeploymentLink(): string
    {
        return $this->deploymentLink;
    }

    /**
     * @throws ExecuteFailedException
     *
     * @return bool
     */
    public function isDeployed(): bool
    {
        return $this->deploymentPath === $this->getCurrentDeploymentPath();
    }

    /**
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function createDirectory(): void
    {
        File::ensureDirectoryExists($this->getDeploymentPath(), 0755, true);
    }
}
