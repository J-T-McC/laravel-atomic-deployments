<?php

namespace Tests\Unit\Helpers;

use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Helpers\FileHelper;
use JTMcC\AtomicDeployments\Services\Exec;
use Tests\TestCase;

class FileHelperTest extends TestCase
{
    public function test_it_throws_invalid_path_exception_on_invalid_path()
    {
        // Act
        $this->expectException(InvalidPathException::class);

        // Act
        FileHelper::confirmPathsExist('not_a_real_path');
    }

    public function test_it_updates_symbolic_links_to_new_path()
    {
        // Collect
        $oldSite = self::TMP_FOLDER . 'build/site';
        $oldContent = $oldSite . '/content';
        $oldLink = $oldSite . '/link';

        $newSite = self::TMP_FOLDER . 'deployments/site';
        $newContent = $newSite . '/content';
        $newLink = $newSite . '/link';

        $this->fileSystem->ensureDirectoryExists($oldContent);
        $this->fileSystem->ensureDirectoryExists($newSite);

        // Act
        Exec::ln($oldLink, $oldContent);

        // Assert
        $this->assertTrue(Exec::readlink($oldLink) === $oldContent);

        // Act
        Exec::rsync($oldSite . '/', $newSite . '/');

        // Assert
        $this->assertTrue(Exec::readlink($newLink) === $oldContent);

        // Act
        FileHelper::recursivelyUpdateSymlinks($oldSite, $newSite);

        // Assert
        $this->assertTrue(Exec::readlink($newLink) === $newContent);
    }
}