<?php

namespace Tests\Unit\Helpers;

use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Helpers\FileHelper;
use JTMcC\AtomicDeployments\Services\Exec;
use Tests\TestCase;

class FileHelperTest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_invalid_path_exception_on_invalid_path()
    {
        $this->expectException(InvalidPathException::class);
        FileHelper::confirmPathsExist('not_a_real_path');
    }

    /**
     * @test
     */
    public function it_updates_symbolic_links_to_new_path()
    {

        //create test build & deployment scenario
        $oldSite = self::tmpFolder.'build/site';
        $oldContent = $oldSite.'/content';
        $oldLink = $oldSite.'/link';

        $newSite = self::tmpFolder.'deployments/site';
        $newContent = $newSite.'/content';
        $newLink = $newSite.'/link';

        $this->fileSystem->ensureDirectoryExists($oldContent);
        $this->fileSystem->ensureDirectoryExists($newSite);

        //link to old content
        Exec::ln($oldLink, $oldContent);
        $this->assertTrue(Exec::readlink($oldLink) === $oldContent);

        //copy old content to deployment folder and confirm link still points to build folder
        Exec::rsync($oldSite.'/', $newSite.'/');
        $this->assertTrue(Exec::readlink($newLink) === $oldContent);

        //convert links to new deployment path and confirm
        FileHelper::recursivelyUpdateSymlinks($oldSite, $newSite);
        $this->assertTrue(Exec::readlink($newLink) === $newContent);
    }
}
