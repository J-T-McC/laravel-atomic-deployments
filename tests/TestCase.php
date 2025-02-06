<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use JTMcC\AtomicDeployments\Services\AtomicDeploymentService;
use JTMcC\AtomicDeployments\Services\Deployment;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    const string TMP_FOLDER = __DIR__.'/tmp/';

    public string $buildPath;

    public string $deploymentLink;

    public string $deploymentsPath;

    public ?Filesystem $fileSystem = null;

    public $mockConsoleOutput = false;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--database' => 'sqlite']);

        $this->fileSystem = new Filesystem;
        $this->fileSystem->deleteDirectory(self::TMP_FOLDER);
        $this->fileSystem->makeDirectory(self::TMP_FOLDER);

        $config = $this->app->config->get('atomic-deployments');

        $this->buildPath = self::TMP_FOLDER.$config['build-path'];
        $this->deploymentLink = self::TMP_FOLDER.$config['deployment-link'];
        $this->deploymentsPath = self::TMP_FOLDER.$config['deployments-path'];

        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployment-link', $this->deploymentLink);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->deploymentsPath);
        $this->app['config']->set('atomic-deployments.migrate', ['migration/*']);

        $this->fileSystem->ensureDirectoryExists($this->buildPath.'/build-contents-folder');
        $this->fileSystem->ensureDirectoryExists($this->deploymentsPath);

        Event::fake();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->fileSystem->deleteDirectory(self::TMP_FOLDER);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [\JTMcC\AtomicDeployments\AtomicDeploymentsServiceProvider::class];
    }

    protected function seeInConsoleOutput(string|array $searchStrings): void
    {
        $searchStrings = (array) $searchStrings;
        $output = Artisan::output();

        foreach ($searchStrings as $searchString) {
            $this->assertStringContainsStringIgnoringCase((string) $searchString, $output);
        }
    }

    protected function dontSeeInConsoleOutput(string|array $searchStrings): void
    {
        $searchStrings = (array) $searchStrings;
        $output = Artisan::output();

        foreach ($searchStrings as $searchString) {
            $this->assertStringNotContainsString((string) $searchString, $output);
        }
    }

    public static function getAtomicDeployment(string $hash = ''): AtomicDeploymentService
    {
        $atomicDeployment = AtomicDeploymentService::create();

        if (! empty($hash)) {
            $atomicDeployment->getDeployment()->setDirectory($hash);
        }

        return $atomicDeployment;
    }

    public static function getDeployment(): Deployment
    {
        return app(Deployment::class);
    }
}
