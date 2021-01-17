<?php

namespace JTMcC\AtomicDeployments\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Models\AtomicDeployment as Model;
use JTMcC\AtomicDeployments\Models\Enums\DeploymentStatus;

class AtomicDeployment
{

    protected bool $testRun;
    protected ?Model $model = null;
    protected string $webRoot;
    protected string $buildPath;
    protected string $deploymentPath;
    protected string $deploymentsPath;
    protected string $deploymentDirectory;
    protected string $initialDeploymentPath = '';

    public function __construct(string $webRoot, string $deploymentsPath, string $buildPath, bool $testRun = false)
    {
        $this->testRun = $testRun;
        $this->webRoot = $webRoot;
        $this->deploymentsPath = $deploymentsPath;
        $this->buildPath = $buildPath;

        register_shutdown_function([$this, 'shutdown']);
    }

    public function run()
    {
        Log::info('Running Deployment...');
        Log::info("Checking for previous deployment");

        try {

            if ($this->initialDeploymentPath = $this->getCurrentDeploymentPath()) {
                Log::info("Previous deployment detected at {$this->initialDeploymentPath}");
                Log::info("Storing path for rollback");
            } else {
                Log::info("No previous deployment for this web root");
            }

            $this->setDeploymentDirectoryName();
            $this->setDeploymentPath();
            $this->setDeploymentStatus(DeploymentStatus::RUNNING);
            $this->createDeploymentDirectory();
            $this->confirmPathsExist(
                $this->buildPath,
                $this->deploymentPath
            );
            $this->copyDeploymentContents();
            $this->linkWebRoot();
            $this->setDeploymentStatus(DeploymentStatus::SUCCESS);

        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            $this->rollback();
        }

        Log::info("Done");

    }

    public function setDeploymentStatus(int $status)
    {
        if($this->testRun) {
            return;
        }

        if ($this->model) {
            $this->model->update(['deployment_status' => $status]);
        } else {
            $this->model = Model::create([
                'commit_hash' => $this->deploymentDirectory,
                'build_path' => $this->buildPath,
                'deployment_path' => $this->deploymentPath,
                'web_root' => $this->webRoot,
                'deployment_status' => $status,
            ]);
        }
    }

    /**
     * @param string ...$paths
     * @throws InvalidPathException
     */
    private function confirmPathsExist(string ...$paths): void
    {
        if (!$this->testRun) {
            foreach ($paths as $path) {
                if (empty(realpath($path))) {
                    throw new InvalidPathException("{$path} is not a real path");
                }
            }
        }
    }

    public function createDeploymentDirectory(): void
    {
        $this->createDirectory(
            $this->deploymentPath,
            $mode = 0775,
            $recursive = true
        );

        Log::info('Created deployment directory');
    }

    /**
     * @throws ExecuteFailedException
     */
    public function copyDeploymentContents(): void
    {
        Log::info('Copying build files to production folder');
        $this->executeCommand("rsync -aW --no-compress {$this->buildPath}/ {$this->deploymentPath}/", false);
        Log::info('Done copying');
    }

    /**
     * @throws ExecuteFailedException
     */
    public function linkWebRoot(): void
    {
        Log::info("Creating web root symbolic link: {$this->webRoot} -> {$this->deploymentPath}");
        $this->executeCommand("ln -sf {$this->deploymentPath} {$this->webRoot}", false);
        Log::info("Link created");
    }

    /**
     * @throws ExecuteFailedException
     */
    public function setDeploymentDirectoryName(): void
    {
        $this->deploymentDirectory = $this->executeCommand('git log --pretty="%h" -n1 HEAD', true);
        Log::info("Set deployment directory to {$this->deploymentDirectory}");
    }

    /**
     * @return string
     * @throws ExecuteFailedException
     */
    public function getCurrentDeploymentPath()
    {
        return $this->executeCommand("readlink -f {$this->webRoot}");
    }

    private function setDeploymentPath(): void
    {
        $this->deploymentPath = implode(DIRECTORY_SEPARATOR, [$this->deploymentsPath, $this->deploymentDirectory]);
        Log::info("Set deployment path to {$this->deploymentPath}");
    }

    /**
     * @param $path
     * @param int $mode
     * @param false $recursive
     */
    private function createDirectory($path, $mode = 0775, $recursive = false): void
    {
        if ($this->testRun) {
            Log::info("Create directory at {$path}");
            return;
        }

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, $mode, $recursive);
        }
    }


    public function rollback(): void
    {
        Log::info('Atomic deployment rollback has been requested');

        $currentPath = $this->getCurrentDeploymentPath();

        //confirm if we detected a path on boot and if its not what is currently set
        if (
            $this->initialDeploymentPath &&
            $this->initialDeploymentPath !== $currentPath
        ) {

            Log::critical('Atomic deployment rollback has been requested');

            Log::critical("Attempting to link web root to {$this->initialDeploymentPath}");

            try {

                //attempt to revert out symbolic link to our original path
                $this->executeCommand("ln -sf {$this->initialDeploymentPath} {$this->webRoot}");

                if ($this->getCurrentDeploymentPath() === $this->initialDeploymentPath) {
                    Log::info('Successfully rolled back symbolic web root');
                    return;
                }

            } catch (ExecuteFailedException $e) {
                Log::emergency($e->getMessage());
            }

            Log::emergency('Failed to roll back symbolic web root');

            return;
        }

        $this->setDeploymentStatus(DeploymentStatus::FAILED);

        Log::info('Rollback not required');
    }

    /**
     * @param string $command
     * @param bool $ignoreTestMode
     * @return string
     * @throws ExecuteFailedException
     */
    private function executeCommand(string $command, bool $ignoreTestMode = false)
    {

        if ($this->testRun && !$ignoreTestMode) {
            Log::info($command);
            return '';
        }

        $output = [];
        $status = null;
        $result = trim(exec(escapeshellcmd($command), $output, $status));

        //non zero status means execution failed
        //see https://www.linuxtopia.org/online_books/advanced_bash_scripting_guide/exitcodes.html
        if ($status) {
            throw new ExecuteFailedException("resulted in exit code {$status}");
        }

        return $result;
    }

    public function shutdown()
    {
        if ($error = error_get_last()) {
            Log::critical("Error detected during shutdown, requesting rollback");
            $this->rollback();
        }
    }
}
