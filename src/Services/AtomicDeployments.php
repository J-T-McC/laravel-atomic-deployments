<?php
declare(strict_types=1);

namespace JTMcC\AtomicDeployments\Services;

use Closure;

use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Events\DeploymentFailed;
use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Helpers\FileHelper;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;

use Illuminate\Support\Pluralizer;
use Illuminate\Support\Facades\File;

class AtomicDeployments
{

    protected ?AtomicDeployment $model = null;

    protected bool $dryRun;
    protected array $migrate;

    protected string $buildPath;
    protected string $deploymentLink;
    protected string $deploymentPath;
    protected string $deploymentsPath;
    protected string $initialDeploymentPath = '';
    protected string $deploymentDirectory = '';


    /**
     * @param string $deploymentLink
     * @param string $deploymentsPath
     * @param string $buildPath
     * @param array $migrate
     * @param bool $dryRun
     *
     * @throws ExecuteFailedException
     */
    public function __construct(
        string $deploymentLink,
        string $deploymentsPath,
        string $buildPath,
        array $migrate = [],
        bool $dryRun = false)
    {
        $this->deploymentLink = $deploymentLink;
        $this->deploymentsPath = $deploymentsPath;
        $this->buildPath = $buildPath;
        $this->migrate = $migrate;
        $this->dryRun = $dryRun;

        register_shutdown_function([$this, 'shutdown']);

        $this->initialDeploymentPath = $this->getCurrentDeploymentPath();
    }


    /**
     * Run full deployment
     *
     * @param Closure|null $success
     * @param Closure|null $failed
     */
    public function deploy(?Closure $success = null, ?Closure $failed = null): void
    {
        try {

            if ($this->isDryRun()) {
                Output::warn('Dry run - changes will not be made');
            }

            Output::info("Checking for previous deployment");

            Output::info($this->initialDeploymentPath ?
                "Previous deployment detected at {$this->initialDeploymentPath}" :
                "No previous deployment detected for this link");

            if (empty(trim($this->deploymentDirectory))) {
                $this->setDeploymentDirectory(Exec::getGitHash());
            }

            $this->setDeploymentPath();
            $this->updateDeploymentStatus(DeploymentStatus::RUNNING);
            $this->createDeploymentDirectory();
            $this->confirmRequiredDirectoriesExist();
            $this->copyDeploymentContents();
            $this->copyMigrationContents();
            $this->linkDeployment($this->deploymentLink, $this->deploymentPath);
            $this->confirmSymbolicLink($this->deploymentPath);
            $this->updateDeploymentStatus(DeploymentStatus::SUCCESS);

            if ($success) {
                DeploymentSuccessful::dispatch($this, $this->model);
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


    /**
     * Create | Update deployment status database record for current deployment
     *
     * @param int $status
     */
    public function updateDeploymentStatus(int $status): void
    {
        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping deployment status update');
            return;
        }

        $this->model = AtomicDeployment::updateOrCreate(
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
     *
     * @param string $deploymentPath
     *
     * @return bool
     *
     * @throws ExecuteFailedException
     */
    public function confirmSymbolicLink(string $deploymentPath): bool
    {
        Output::info('Confirming deployment link is correct');
        $currentDeploymentPath = $this->getCurrentDeploymentPath();

        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping link comparison');
            return true;
        }

        if ($deploymentPath !== $currentDeploymentPath) {
            throw new ExecuteFailedException('Expected deployment link to direct to ' . $deploymentPath . ' but found ' . $currentDeploymentPath);
        }

        Output::info('Build link confirmed');
        return true;
    }


    /**
     * @throws InvalidPathException
     */
    public function confirmRequiredDirectoriesExist(): void
    {
        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping required directory exists check for:');
            Output::warn($this->buildPath);
            Output::warn($this->deploymentPath);
            return;
        }

        FileHelper::confirmPathsExist(
            $this->buildPath,
            $this->deploymentPath
        );
    }

    /**
     * @throws InvalidPathException
     */
    public function createDeploymentDirectory(): void
    {
        Output::info("Creating directory at {$this->deploymentPath}");

        if (strpos($this->deploymentPath, $this->buildPath) !== false) {
            throw new InvalidPathException('Deployments folder cannot be subdirectory of build folder');
        }

        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping creating deployment directory');
            return;
        }

        FileHelper::createDirectory($this->deploymentPath);
        Output::info('Created deployment directory');
    }


    /**
     * Clone our build into our deployment folder
     *
     * @throws ExecuteFailedException
     */
    public function copyDeploymentContents(): void
    {
        Output::info('Copying build files to deployment folder...');

        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping directory sync');
            return;
        }

        Exec::rsync("{$this->buildPath}/", "{$this->deploymentPath}/");
        Output::info('Copying complete');
    }


    /**
     * @throws ExecuteFailedException
     */
    public function copyMigrationContents(): void
    {
        if (!empty($this->initialDeploymentPath) && count($this->migrate)) {

            if ($this->isDryRun()) {
                Output::warn('Dry run - skipping migrations');
            }

            collect($this->migrate)->each(function ($pattern) {

                if (!$this->isDryRun()) {
                    Output::info("Running migration for pattern {$pattern}");
                }

                $rootFrom = rtrim($this->initialDeploymentPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $rootTo = rtrim($this->deploymentPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                foreach (File::glob($rootFrom . $pattern) as $from) {

                    $dir = $from;

                    if (!File::isDirectory($dir)) {
                        $dir = File::dirname($dir);
                    }

                    $dir = str_replace($rootFrom, $rootTo, $dir);
                    $to = str_replace($rootFrom, $rootTo, $from);

                    if ($this->isDryRun()) {
                        Output::warn("Dry run - migrate: \r\n - {$from}\r\n - {$to}");
                        Output::line();
                        continue;
                    }

                    File::ensureDirectoryExists($dir, 0755, true);

                    Exec::rsync($from, $to);

                }

                if (!$this->isDryRun()) {
                    Output::info("Finished migration for pattern {$pattern}");
                }

            });
        }
    }


    /**
     * Create Symbolic link for live deployment
     * Will overwrite previous link
     *
     * @param string $deploymentLink
     * @param string $deploymentPath
     *
     * @throws ExecuteFailedException
     */
    public function linkDeployment(string $deploymentLink, string $deploymentPath): void
    {
        Output::info("Creating web root symbolic link: {$deploymentLink} -> {$deploymentPath}");
        if ($this->isDryRun()) {
            Output::warn("Dry run - Skipping symbolic link deployment");
            return;
        }
        Exec::ln($deploymentLink, $deploymentPath);
        Output::info("Link created");
    }


    /**
     * Sets the directory name for this deployment
     *
     * @param string $name
     */
    public function setDeploymentDirectory(string $name): void
    {
        $this->deploymentDirectory = trim($name);
        Output::info("Set deployment directory to {$this->deploymentDirectory}");
    }


    /**
     * Get the current symlinked deployment path
     *
     * @return string
     *
     * @throws ExecuteFailedException
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
     * Sets full deployment path for this deployment
     */
    public function setDeploymentPath(): void
    {
        $this->deploymentPath = implode(DIRECTORY_SEPARATOR, [$this->deploymentsPath, $this->deploymentDirectory]);
        Output::info("Set deployment path to {$this->deploymentPath}");
    }


    /**
     * Get full deployment path for this deployment
     *
     * @return string
     *
     * @see getCurrentDeploymentPath() to get the path currently in use
     */
    public function getDeploymentPath(): string
    {
        return $this->deploymentsPath;
    }


    /**
     * Attempt to rollback the deployment to the deployment path detected on run
     *
     * @throws ExecuteFailedException
     */
    public function rollback(): void
    {
        Output::warn('Atomic deployment rollback has been requested');

        if (!$this->isDryRun()) {

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
     *
     * @param $limit
     *
     * @throws ExecuteFailedException
     * @throws InvalidPathException
     */
    public function cleanBuilds($limit): void
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

            if (!$this->isDryRun()) {
                $deployment->delete();
            }

            Output::info("Deployment deleted");
        }
    }


    /**
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }


    /**
     * @throws ExecuteFailedException
     */
    public function failed(): void
    {
        $this->rollback();
        $this->updateDeploymentStatus(DeploymentStatus::FAILED);
        DeploymentFailed::dispatch($this, $this->model);
    }


    /**
     * @throws ExecuteFailedException
     */
    public function shutdown(): void
    {
        if ($error = error_get_last()) {
            Output::error("Error detected during shutdown, requesting rollback");
            $this->failed();
        }
    }
}
