<?php

namespace JTMcC\AtomicDeployments\Helpers;

use Illuminate\Support\Facades\File;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;

class FileHelper
{
    /**
     * @param string ...$paths
     *
     * @throws InvalidPathException
     *
     * @return bool
     */
    public static function confirmPathsExist(string ...$paths): bool
    {
        foreach ($paths as $path) {
            if (!File::exists($path)) {
                throw new InvalidPathException("{$path} does not exist");
            }
        }

        return true;
    }
}
