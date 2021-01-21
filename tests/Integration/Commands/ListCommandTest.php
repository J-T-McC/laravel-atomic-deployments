<?php

namespace Tests\Integration\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use JTMcC\AtomicDeployments\Models\AtomicDeployment;
use Tests\TestCase;

class ListCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_lists_available_builds()
    {
        Artisan::call('atomic-deployments:deploy --directory=test-dir-1');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-2');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-3');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-4');
        Artisan::call('atomic-deployments:deploy --directory=test-dir-5');

        AtomicDeployment::find(1)->delete();

        Artisan::call('atomic-deployments:list');

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
