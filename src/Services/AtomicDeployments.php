<?php
declare(strict_types=1);

namespace JTMcC\AtomicDeployments\Services;

use Closure;

use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Helpers\FileHelper;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;

use Illuminate\Support\Pluralizer;

class AtomicDeployments
{

    protected ?AtomicDeployment $model = null;

    protected bool $dryRun;

    protected string $buildPath;
    protected string $deploymentLink;
    protected string $deploymentPath;
    protected string $deploymentsPath;
    protected string $deploymentDirectory;
    protected string $initialDeploymentPath = '';

    public function __construct(string $deploymentLink, string $deploymentsPath, string $buildPath, bool $dryRun = false)
    {
        $this->deploymentLink = $deploymentLink;
        $this->deploymentsPath = $deploymentsPath;
        $this->buildPath = $buildPath;
        $this->dryRun = $dryRun;

        register_shutdown_function([$this, 'shutdown']);

        $this->initialDeploymentPath = $this->getCurrentDeploymentPath();
    }

    /**
     * Run full deployment
     * @param Closure|null $success
     * @param Closure|null $failed
     */
    public function deploy(?Closure $success = null, ?Closure $failed = null)
    {
        try {

            if ($this->dryRun) {
                Output::warn('Dry run - changes will not be made');
            }

            Output::info("Checking for previous deployment");

            Output::info($this->initialDeploymentPath ?
                "Previous deployment detected at {$this->initialDeploymentPath}" :
                "No previous deployment detected for this link");

            $this->setDeploymentDirectory(Exec::getGitHash());
            $this->setDeploymentPath();
            $this->updateDeploymentStatus(DeploymentStatus::RUNNING);
            $this->createDeploymentDirectory();

            FileHelper::confirmPathsExist(
                $this->buildPath,
                $this->deploymentPath
            );

            $this->copyDeploymentContents();
            $this->linkDeployment($this->deploymentLink, $this->deploymentPath);
            $this->confirmSymbolicLink($this->deploymentPath);
            $this->updateDeploymentStatus(DeploymentStatus::SUCCESS);

            if ($success) {
                $success($this);
            }

        } catch (\Throwable $e) {
            Output::throwable($e);
            $this->failed();
            if ($failed) {
                $failed($this);
            }
        }

    }

    public function updateDeploymentStatus(int $status)
    {
        if ($this->dryRun) {
            return;
        }

        AtomicDeployment::updateOrCreate(
            ['deployment_path' => $this->deploymentPath],
            [
                'commit_hash' => $this->deploymentDirectory,
                'build_path' => $this->buildPath,
                'deployment_link' => $this->deploymentLink,
                'deployment_status' => $status,
            ]
        );
    }


    /**
     * Test a path against our symbolic links destination
     * @param string $link
     * @return bool
     * @throws ExecuteFailedException
     */
    public function confirmSymbolicLink(string $link)
    {
        Output::info('Confirming deployment link is correct');
        $currentDeploymentPath = $this->getCurrentDeploymentPath();
        if ($link !== $currentDeploymentPath) {
            throw new ExecuteFailedException('Expected deployment link to direct to ' . $this->deploymentPath . ' but found ' . $currentDeploymentPath);
        }
        Output::info('Build link confirmed');
        return true;
    }

    private function createDeploymentDirectory(): void
    {
        if (!$this->dryRun) {
            FileHelper::createDirectory($this->deploymentPath);
        }
        Output::info('Created deployment directory');
    }

    /**
     * Clone our build into our deployment folder
     * @throws ExecuteFailedException
     */
    private function copyDeploymentContents(): void
    {
        Output::info('Copying build files to deployment folder...');
        if (!$this->dryRun) {
            Exec::rsyncDir("{$this->buildPath}/", "{$this->deploymentPath}/");
            Output::info('Copying complete');
        }
    }

    /**
     * Create Symbolic link for live deployment
     * Will overwrite previous link
     * @param string $deploymentLink
     * @param string $deploymentPath
     * @throws ExecuteFailedException
     */
    public function linkDeployment(string $deploymentLink, string $deploymentPath): void
    {
        Output::info("Creating web root symbolic link: {$deploymentLink} -> {$deploymentPath}");
        if (!$this->dryRun) {
            Exec::ln($deploymentLink, $deploymentPath);
            Output::info("Link created");
        }
    }

    /**
     * Sets the directory name for this deployment
     * @param string $name
     */
    public function setDeploymentDirectory(string $name): void
    {
        $this->deploymentDirectory = trim($name);
        Output::info("Set deployment directory to {$this->deploymentDirectory}");
    }

    /**
     * Get the current symlinked deployment path
     * @return string
     * @throws ExecuteFailedException
     */
    public function getCurrentDeploymentPath()
    {
        return Exec::readlink($this->deploymentLink);
    }

    /**
     * Sets full deployment path for this deployment
     */
    public function setDeploymentPath(): void
    {
        $this->deploymentPath = implode(DIRECTORY_SEPARATOR, [$this->deploymentsPath, $this->deploymentDirectory]);
        Output::info("Set deployment path to {$this->deploymentPath}");
    }

    /**
     * Get full deployment path for this deployment
     * @see getCurrentDeploymentPath() to get the path currently in use
     * @return string
     */
    public function getDeploymentPath() {
        return $this->deploymentsPath;
    }

    /**
     * Attempt to rollback the deployment to the deployment path detected on run
     * @throws ExecuteFailedException
     */
    public function rollback(): void
    {
        Output::warn('Atomic deployment rollback has been requested');

        if (!$this->dryRun) {

            $currentPath = $this->getCurrentDeploymentPath();

            if (
                //confirm if we need to revert the link
                $this->initialDeploymentPath &&
                $this->initialDeploymentPath !== $currentPath
            ) {

                Output::emergency('Atomic deployment rollback has been requested');
                Output::emergency("Attempting to link web root to {$this->initialDeploymentPath}");

                try {
                    //attempt to revert out symbolic link to our original path
                    Exec::ln($this->initialDeploymentPath, $this->deploymentLink);
                    if ($this->getCurrentDeploymentPath() === $this->initialDeploymentPath) {
                        Output::info('Successfully rolled back symbolic web root');
                        return;
                    }

                } catch (ExecuteFailedException $e) {
                    Output::throwable($e);
                }

                Output::emergency('Failed to roll back symbolic web root');
                return;
            }
        }

        Output::info('Rollback not required');
    }

    /**
     * Remove old build folders beyond of the allowed build count range set in config
     * @param $limit
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function cleanBuilds($limit)
    {
        Output::alert('Running Build Cleanup');
        Output::info("Max deployment directories allowed set to {$limit}");

        $buildIDs = AtomicDeployment::successful()
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->pluck('id');

        $buildsToRemove = AtomicDeployment::whereNotIn('id', $buildIDs)->get();

        $found = $buildsToRemove->count();

        Output::info('Found ' . $found . ' ' . Pluralizer::plural('folder', $found) . ' to be removed');

        foreach ($buildsToRemove as $deployment) {

            if ($this->getCurrentDeploymentPath() === $deployment->deployment_path) {
                Output::error('Current linked path has appeared in the directory cleaning logic');
                Output::error('This should not happen. Please confirm your atomic_deployments table has not been corrupted');
                throw new InvalidPathException("Attempted to clean current linked path {$deployment->deployment_path}");
            }

            Output::info("Deleting {$deployment->commit_hash}");

            if (!$this->dryRun) {
                $deployment->delete();
            }

            Output::info("Deployment deleted");
        }

    }

    public function failed()
    {
        $this->rollback();
        $this->updateDeploymentStatus(DeploymentStatus::FAILED);
    }

    public function shutdown()
    {
        if ($error = error_get_last()) {
            Output::error("Error detected during shutdown, requesting rollback");
            $this->failed();
        }
    }
}
