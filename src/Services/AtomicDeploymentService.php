<?php

declare(strict_types=1);

namespace JTMcC\AtomicDeployments\Services;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Pluralizer;
use JTMcC\AtomicDeployments\Events\DeploymentFailed;
use JTMcC\AtomicDeployments\Events\DeploymentSuccessful;
use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;
use JTMcC\AtomicDeployments\Helpers\FileHelper;
use JTMcC\AtomicDeployments\Interfaces\DeploymentInterface;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;
use Throwable;

class AtomicDeploymentService
{
    protected DeploymentInterface $deployment;

    protected bool $dryRun;

    protected array $migrate;

    protected string $initialDeploymentPath = '';

    /**
     * @param  mixed  ...$args
     */
    public static function create(...$args): self
    {
        return app(static::class, $args);
    }

    public function __construct(DeploymentInterface $deployment, array $migrate = [], bool $dryRun = false)
    {
        $this->deployment = $deployment;
        $this->migrate = $migrate;
        $this->dryRun = $dryRun;

        register_shutdown_function([$this, 'shutdown']);

        $this->initialDeploymentPath = $deployment->getCurrentPath();
    }

    public function getDeployment(): DeploymentInterface
    {
        return $this->deployment;
    }

    public function getInitialDeploymentPath(): string
    {
        return $this->initialDeploymentPath;
    }

    public function deploy(?Closure $successCallback = null, ?Closure $failedCallback = null): void
    {
        try {
            if ($this->isDryRun()) {
                Output::warn('Dry run - changes will not be made');
            }

            Output::info('Checking for previous deployment');

            Output::info($this->initialDeploymentPath ?
                sprintf('Previous deployment detected at %s', $this->initialDeploymentPath) :
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

            if ($successCallback) {
                $successCallback($this);
            }
        } catch (Throwable $e) {
            $this->fail();
            Output::throwable($e);

            if ($failedCallback) {
                $failedCallback($this);
            }
        }
    }

    public function updateDeploymentStatus(DeploymentStatus $status): void
    {
        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping deployment status update');

            return;
        }
        $this->deployment->updateStatus($status);
    }

    public function linkDeployment(): void
    {
        Output::info(sprintf('Creating symbolic link: %s -> %s', $this->deployment->getLink(), $this->deployment->getPath()));
        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping symbolic link deployment');

            return;
        }
        $this->deployment->link();
        Output::info('Link created');
    }

    /**
     * @throws ExecuteFailedException
     */
    public function confirmSymbolicLink(): bool
    {
        Output::info('Confirming deployment link is correct');

        if ($this->isDryRun()) {
            Output::warn('Dry run - Skipping link comparison');

            return true;
        }

        if (! $this->deployment->isDeployed()) {
            throw new ExecuteFailedException(
                sprintf(
                    'Expected deployment link to direct to %s but found %s',
                    $this->deployment->getPath(),
                    $this->deployment->getCurrentPath()
                )
            );
        }

        Output::info('Build link confirmed');

        return true;
    }

    public function createDeploymentDirectory(): void
    {
        Output::info(sprintf('Creating directory at %s', $this->deployment->getPath()));

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
        if (! empty($this->initialDeploymentPath) && count($this->migrate)) {
            if ($this->isDryRun()) {
                Output::warn('Dry run - skipping migrations');
            }

            collect($this->migrate)->each(function (string $pattern): void {
                if (! $this->isDryRun()) {
                    Output::info(sprintf('Running migration for pattern %s', $pattern));
                }

                $rootFrom = rtrim($this->initialDeploymentPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                $rootTo = rtrim($this->deployment->getPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

                foreach (File::glob($rootFrom.$pattern) as $from) {
                    $dir = $from;

                    if (! File::isDirectory($dir)) {
                        $dir = File::dirname($dir);
                    }

                    $dir = str_replace($rootFrom, $rootTo, $dir);
                    $to = str_replace($rootFrom, $rootTo, $from);

                    if ($this->isDryRun()) {
                        Output::warn(sprintf("Dry run - migrate: \r\n - %s\r\n - %s", $from, $to));
                        Output::line();

                        continue;
                    }

                    File::ensureDirectoryExists($dir, 0755, true);

                    Exec::rsync($from, $to);
                }

                if (! $this->isDryRun()) {
                    Output::info(sprintf('Finished migration for pattern %s', $pattern));
                }
            });
        }
    }

    /**
     * @throws ExecuteFailedException
     */
    public function updateSymlinks(): void
    {
        Output::info('Correcting old symlinks that still reference the build directory');

        if ($this->isDryRun()) {
            Output::warn('Dry run - skipping symlink corrections');

            return;
        }

        FileHelper::recursivelyUpdateSymlinks(
            $this->getDeployment()->getBuildPath(),
            $this->getDeployment()->getPath()
        );

        Output::info('Finished correcting symlinks');
    }

    public function rollback(): void
    {
        Output::warn('Atomic deployment rollback has been requested');

        if (! $this->isDryRun()) {
            $currentPath = $this->deployment->getCurrentPath();

            if (
                // confirm if we need to revert the link
                $this->initialDeploymentPath &&
                $this->initialDeploymentPath !== $currentPath
            ) {
                Output::emergency(sprintf('Attempting to link deployment at %s', $this->initialDeploymentPath));

                try {
                    // attempt to revert link to our original path
                    Exec::ln($this->deployment->getLink(), $this->initialDeploymentPath);
                    if ($this->deployment->getCurrentPath() === $this->initialDeploymentPath) {
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

    public function fail(): void
    {
        $this->rollback();
        DeploymentFailed::dispatch($this, $this->deployment->getModel());
        $this->updateDeploymentStatus(DeploymentStatus::FAILED);
    }

    public function shutdown(): void
    {
        if (error_get_last()) {
            Output::error('Error detected during shutdown, requesting rollback');

            $this->fail();
        }
    }

    public function cleanBuilds(int $limit): void
    {
        Output::alert('Running Build Cleanup');
        Output::info(sprintf('Max deployment directories allowed set to %d', $limit));

        $buildIDs = AtomicDeployment::successful()
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->pluck('id');

        /* @var Collection<AtomicDeployment> $buildsToRemove */
        $buildsToRemove = AtomicDeployment::query()->whereNotIn('id', $buildIDs)->get();

        $countOfBuildsToRemove = $buildsToRemove->count();

        Output::info(sprintf('Found %d %s to be removed', $countOfBuildsToRemove, Pluralizer::plural('folder', $countOfBuildsToRemove)));

        foreach ($buildsToRemove as $deployment) {

            if ($deployment->is_currently_deployed) {
                Output::warn('Current linked path has appeared in the directory cleaning logic');
                Output::warn('This either means you currently have an old build deployed or there is a problem with your deployment data');
                Output::warn('Skipping deletion');

                return;
            }

            Output::info(sprintf('Deleting %s', $deployment->commit_hash));

            if (! $this->isDryRun()) {
                // @phpstan-ignore-next-line
                $deployment->delete();
                Output::info('Deployment deleted');
            } else {
                Output::warn('Dry run - skipped delete');
            }
        }
    }
}
