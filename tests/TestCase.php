<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

use Illuminate\Filesystem\Filesystem;

abstract class TestCase extends BaseTestCase
{

    const tmpFolder = __DIR__ . '/tmp/';
    public $buildPath;
    public $deploymentLink;
    public $deploymentsPath;

    public ?Filesystem $fileSystem = null;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'testbench'])->run();

        $this->fileSystem = new Filesystem();
        $this->fileSystem->deleteDirectory(self::tmpFolder);
        $this->fileSystem->makeDirectory(self::tmpFolder);

        $config = $this->app->config->get('atomic-deployments');

        $this->buildPath = static::tmpFolder . $config['build-path'];
        $this->deploymentsPath = static::tmpFolder. $config['deployments-path'];
        $this->deploymentLink = static::tmpFolder. $config['deployment-link'];

        $this->fileSystem->makeDirectory($this->buildPath);
        $this->fileSystem->makeDirectory($this->deploymentsPath);

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
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [\JTMcC\AtomicDeployments\AtomicDeploymentsServiceProvider::class];
    }

}
