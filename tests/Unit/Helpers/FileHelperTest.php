<?php

namespace Tests\Unit\Helpers;

use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Helpers\FileHelper;
use Tests\TestCase;

class FileHelperTest extends TestCase
{
    /**
     * @test
     */
    public function file_helper_throws_invalid_path_exception_on_invalid_path()
    {
        $this->expectException(InvalidPathException::class);
        FileHelper::confirmPathsExist('not_a_real_path');
    }

    /**
     * @test
     */
    public function file_helper_creates_and_confirms_new_directory()
    {
        $path = self::tmpFolder.'new-dir';
        FileHelper::createDirectory($path);
        $result = FileHelper::confirmPathsExist($path);
        $this->assertTrue($result);
    }
}
