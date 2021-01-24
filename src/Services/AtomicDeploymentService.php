<?php

declare(strict_types=1);

namespace JTMcC\AtomicDeployments\Services;

use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Pluralizer;
use JTMcC\AtomicDeployments\Events\DeploymentFailed;
use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;
use JTMcC\AtomicDeployments\Helpers\FileHelper;
use JTMcC\AtomicDeployments\Interfaces\DeploymentInterface;
use JTMcC\AtomicDeployments\Models\AtomicDeployment as Model;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;

class AtomicDeploymentService
{
    protected DeploymentInterface $deployment;

    protected bool $dryRun;
    protected array $migrate;

    protected string $initialDeploymentPath = '';

    /**
     * @param mixed ...$args
     *
     * @return self
     */
    public static function create(...$args)
    {
        return app(static::class, $args);
    }

    /**
     * @param DeploymentInterface $deployment
     * @param array               $migrate
     * @param bool                $dryRun
     */
    public function __construct(DeploymentInterface $deployment, array $migrate = [], bool $dryRun = false)
    {
        $this->deployment = $deployment;
        $this->migrate = $migrate;
        $this->dryRun = $dryRun;

        register_shutdown_function([$this, 'shutdown']);

        $this->initialDeploymentPath = $deployment->getCurrentDeploymentPath();
    }

    /**
     * @return DeploymentInterface
     */
    public function getDeployment()
    {
        return $this->deployment;
    }

    /**
     * @return string
     */
    public function getInitialDeploymentPath(): string
    {
        return $this->initialDeploymentPath;
    }

    /**
     * Run full deployment.
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

            Output::info('Checking for previous deployment');

            Output::info($this->initialDeploymentPath ?
                "Previous deployment detected at {$this->initialDeploymentPath}" :
                'No previous deployment detected for this link');

            $this->updateDeploymentStatus(DeploymentStatus::RUNNING);

            $this->createDeploymentDirectory();
            $this->copyDeploymentContents();
            $this->copyMigrationContents();
            $this->updateSymlinks();
            $this->linkDeployment();
            $this->confirmSymbolicLink();

            $this->updateDeploymentStatus(DeploymentStatus::SUCCESS);

            DeploymentSuccessful::dispatch($this, $this->deployment->getModel());

            if ($success) {
                $success($this);
            }
        } catch (\Throwable $e) {
            $this->failed();
            Output::throwable($e);
            if ($failed) {
                $failed($this);
            }
        }
    }

    /**
     * @param int $status
     */
    public function updateDeploymentStatus(int $status): void
    {
        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping deployment status update');

            return;
        }
        $this->deployment->updateDeploymentStatus($status);
    }

    public function linkDeployment(): void
    {
        Output::info("Creating symbolic link: {$this->deployment->getDeploymentLink()} -> {$this->deployment->getDeploymentPath()}");
        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping symbolic link deployment');

            return;
        }
        $this->deployment->linkDeployment();
        Output::info('Link created');
    }

    /**
     * @throws ExecuteFailedException
     *
     * @return bool
     */
    public function confirmSymbolicLink(): bool
    {
        Output::info('Confirming deployment link is correct');

        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping link comparison');

            return true;
        }

        if (!$this->deployment->isDeployed()) {
            throw new ExecuteFailedException(
                'Expected deployment link to direct to '.
                $this->deployment->getDeploymentPath().' but found '.
                $this->deployment->getCurrentDeploymentPath()
            );
        }

        Output::info('Build link confirmed');

        return true;
    }

    public function createDeploymentDirectory(): void
    {
        Output::info("Creating directory at {$this->deployment->getDeploymentPath()}");

        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping creating deployment directory');

            return;
        }

        $this->deployment->createDirectory();

        Output::info('Created deployment directory');
    }

    public function copyDeploymentContents(): void
    {
        Output::info('Copying build files to deployment folder...');

        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping directory sync');

            return;
        }

        $this->deployment->copyContents();

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

                $rootFrom = rtrim($this->initialDeploymentPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                $rootTo = rtrim($this->deployment->getDeploymentPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

                foreach (File::glob($rootFrom.$pattern) as $from) {
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
     * @throws ExecuteFailedException
     */
    public function updateSymlinks()
    {
        Output::info('Correcting old symlinks that still reference the build directory');

        if ($this->isDryRun()) {
            Output::warn('Dry run - skipping symlink corrections');

            return;
        }

        FileHelper::recursivelyUpdateSymlinks(
            $this->getDeployment()->getBuildPath(),
            $this->getDeployment()->getDeploymentPath()
        );

        Output::info('Finished correcting symlinks');
    }

    public function rollback(): void
    {
        Output::warn('Atomic deployment rollback has been requested');

        if (!$this->isDryRun()) {
            $currentPath = $this->deployment->getCurrentDeploymentPath();

            if (
                //confirm if we need to revert the link
                $this->initialDeploymentPath &&
                $this->initialDeploymentPath !== $currentPath
            ) {
                Output::emergency("Attempting to link deployment at {$this->initialDeploymentPath}");

                try {
                    //attempt to revert link to our original path
                    Exec::ln($this->deployment->getDeploymentLink(), $this->initialDeploymentPath);
                    if ($this->deployment->getCurrentDeploymentPath() === $this->initialDeploymentPath) {
                        Output::info('Successfully rolled back symbolic link');

                        return;
                    }
                } catch (ExecuteFailedException $e) {
                    Output::throwable($e);
                }

                Output::emergency('Failed to roll back symbolic link');

                return;
            }
        }

        Output::info('Rollback not required');
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function failed(): void
    {
        $this->rollback();
        DeploymentFailed::dispatch($this, $this->deployment->getModel());
        $this->updateDeploymentStatus(DeploymentStatus::FAILED);
    }

    public function shutdown(): void
    {
        if ($error = error_get_last()) {
            Output::error('Error detected during shutdown, requesting rollback');
            $this->failed();
        }
    }

    public function cleanBuilds(int $limit): void
    {
        Output::alert('Running Build Cleanup');
        Output::info("Max deployment directories allowed set to {$limit}");

        $buildIDs = Model::successful()
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->pluck('id');

        $buildsToRemove = Model::whereNotIn('id', $buildIDs)->get();

        $found = $buildsToRemove->count();

        Output::info('Found '.$found.' '.Pluralizer::plural('folder', $found).' to be removed');

        foreach ($buildsToRemove as $deployment) {
            if ($deployment->isCurrentlyDeployed) {
                Output::warn('Current linked path has appeared in the directory cleaning logic');
                Output::warn('This either means you currently have an old build deployed or there is a problem with your deployment data');
                Output::warn('Skipping deletion');

                return;
            }

            Output::info("Deleting {$deployment->commit_hash}");

            if (!$this->isDryRun()) {
                $deployment->delete();
                Output::info('Deployment deleted');
            } else {
                Output::warn('Dry run - skipped delete');
            }
        }
    }
}
