<?php

namespace Tests\Unit\Services;

use JTMcC\AtomicDeployments\Services\Exec;
use Tests\TestCase;

class ExecServiceTest extends TestCase
{
    public function test_it_can_create_and_read_symbolic_link()
    {
        // Act
        Exec::ln($this->deploymentLink, $this->deploymentsPath);

        // Assert
        $this->assertTrue(Exec::readlink($this->deploymentLink) === $this->deploymentsPath);
    }

    public function test_it_can_remote_sync_folders()
    {
        // Collect
        $from = $this->buildPath.'/to-move';
        $to = $this->deploymentsPath;
        $confirm = $this->deploymentsPath.'/to-move';
        $this->fileSystem->makeDirectory($from);

        // Act
        Exec::rsync($from, $to);

        // Assert
        $this->assertTrue($this->fileSystem->isDirectory($confirm));
    }
}
