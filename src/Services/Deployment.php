<?php

declare(strict_types=1);

namespace JTMcC\AtomicDeployments\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
    public function link(): void
    {
        Exec::ln($this->deploymentLink, $this->getPath());
    }

    /***
     * @param string $name
     *
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function setDirectory(string $name = ''): void
    {
        $this->deploymentDirectory = trim($name);

        // update deployment path to use new directory
        $this->setPath();
    }

    public function getDirectory(): string
    {
        return $this->deploymentDirectory;
    }

    /**
     * Get the current symlinked deployment path.
     *
     * @throws ExecuteFailedException
     */
    public function getCurrentPath(): string
    {
        $result = Exec::readlink($this->deploymentLink);
        if ($result === $this->deploymentLink) {
            return '';
        }

        return $result;
    }

    /**
     * @throws ExecuteFailedException
     */
    public function getDirectoryName(): string
    {
        return match ($this->directoryNaming) {
            'datetime' => Carbon::now()->format('Y-m-d_H-i-s'),
            'rand' => Str::random(5).time(),
            default => Exec::getGitHash(),
        };
    }

    /**
     * Sets full path for deployment.
     *
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function setPath(): void
    {
        if (empty(trim($this->deploymentDirectory))) {
            $this->setDirectory($this->getDirectoryName());
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
     */
    public function getPath(): string
    {
        if (empty($this->deploymentPath)) {
            $this->setPath();
        }

        return $this->deploymentPath;
    }

    /**
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function updateStatus(int $status): void
    {
        $this->model->updateOrCreate(
            ['deployment_path' => $this->getPath()],
            [
                'commit_hash' => $this->deploymentDirectory,
                'build_path' => $this->buildPath,
                'deployment_link' => $this->deploymentLink,
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

    public function getModel(): AtomicDeployment
    {
        return $this->model;
    }

    public function getBuildPath(): string
    {
        return $this->buildPath;
    }

    public function getLink(): string
    {
        return $this->deploymentLink;
    }

    /**
     * @throws ExecuteFailedException
     */
    public function isDeployed(): bool
    {
        return $this->deploymentPath === $this->getCurrentPath();
    }

    /**
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function createDirectory(): void
    {
        File::ensureDirectoryExists($this->getPath(), 0755, true);
    }
}
