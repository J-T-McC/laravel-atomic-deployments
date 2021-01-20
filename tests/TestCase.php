<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

use Illuminate\Filesystem\Filesystem;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

abstract class TestCase extends BaseTestCase
{

    const tmpFolder = __DIR__ . '/tmp/';
    public $buildPath;
    public $deploymentLink;
    public $deploymentsPath;

    public ?Filesystem $fileSystem = null;

    public $mockConsoleOutput = false;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--database' => 'sqlite']);

        $this->fileSystem = new Filesystem();
        $this->fileSystem->deleteDirectory(self::tmpFolder);
        $this->fileSystem->makeDirectory(self::tmpFolder);

        $config = $this->app->config->get('atomic-deployments');

        $this->buildPath = static::tmpFolder . $config['build-path'];
        $this->deploymentLink = static::tmpFolder. $config['deployment-link'];
        $this->deploymentsPath = static::tmpFolder. $config['deployments-path'];

        $this->app['config']->set('atomic-deployments.build-path', $this->buildPath);
        $this->app['config']->set('atomic-deployments.deployment-link', $this->deploymentLink);
        $this->app['config']->set('atomic-deployments.deployments-path', $this->deploymentsPath);

        $this->fileSystem->ensureDirectoryExists($this->buildPath . '/build-contents-folder');
        $this->fileSystem->ensureDirectoryExists($this->deploymentsPath);

        Event::fake();
    }


    public function tearDown(): void
    {
        parent::tearDown();
        $this->fileSystem->deleteDirectory(self::tmpFolder);
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

    }

    protected function getPackageProviders($app)
    {
        return [\JTMcC\AtomicDeployments\AtomicDeploymentsServiceProvider::class];
    }

    /**
     * @param string|array $searchStrings
     */
    protected function seeInConsoleOutput($searchStrings)
    {
        if (! is_array($searchStrings)) {
            $searchStrings = [$searchStrings];
        }

        $output = Artisan::output();

        foreach ($searchStrings as $searchString) {
            $this->assertStringContainsStringIgnoringCase((string) $searchString, $output);
        }
    }

    /**
     * @param string|array $searchStrings
     */
    protected function dontSeeInConsoleOutput($searchStrings)
    {
        if (! is_array($searchStrings)) {
            $searchStrings = [$searchStrings];
        }

        $output = Artisan::output();

        foreach ($searchStrings as $searchString) {
            $this->assertStringNotContainsString((string) $searchString, $output);
        }
    }
}
