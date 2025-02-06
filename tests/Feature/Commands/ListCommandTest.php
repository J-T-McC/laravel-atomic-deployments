<?php

namespace Tests\Integration\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use Tests\TestCase;

class ListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_available_builds()
    {
        // Collect
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-3');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-4');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-5');

        // Act
        Artisan::call('atomic-deployments:list');

        // Assert
        $this->seeInConsoleOutput([
            'ID',
            'Commit Hash',
            'Path',
            'SymLink',
            'Status',
            'Created',
            'Live',
            "{$this->deploymentsPath}/test-dir-2",
            "{$this->deploymentsPath}/test-dir-3",
            "{$this->deploymentsPath}/test-dir-4",
            "{$this->deploymentsPath}/test-dir-5",
        ]);

        $this->dontSeeInConsoleOutput("{$this->deploymentsPath}/test-dir-1");
    }
}