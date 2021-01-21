<?php

namespace Tests\Unit\Services;

use JTMcC\AtomicDeployments\Services\Exec;
use Tests\TestCase;

class ExecServiceTest extends TestCase
{

    /**
     * @test
     */
    public function it_can_create_and_read_symbolic_link()
    {
        Exec::ln($this->deploymentLink, $this->deploymentsPath);
        $this->assertTrue(Exec::readlink($this->deploymentLink) === $this->deploymentsPath);
    }

    /**
     * @test
     */
    public function it_can_remote_sync_folders()
    {
        $from = $this->buildPath . '/to-move';
        $to = $this->deploymentsPath;
        $confirm = $this->deploymentsPath . '/to-move';
        $this->fileSystem->makeDirectory($from);
        Exec::rsync($from, $to);
        $this->assertTrue($this->fileSystem->isDirectory($confirm));
    }

}

