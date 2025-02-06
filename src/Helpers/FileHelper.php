<?php

namespace JTMcC\AtomicDeployments\Helpers;

use Illuminate\Support\Facades\File;
use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;
use JTMcC\AtomicDeployments\Exceptions\InvalidPathException;
use JTMcC\AtomicDeployments\Services\Exec;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileHelper
{
    /**
     * @throws InvalidPathException
     */
    public static function confirmPathsExist(string ...$paths): bool
    {
        foreach ($paths as $path) {
            if (! File::exists($path)) {
                throw new InvalidPathException("{$path} does not exist");
            }
        }

        return true;
    }

    /**
     * Recursively update symbolic links with new endpoint.
     *
     *
     * @throws ExecuteFailedException
     */
    public static function recursivelyUpdateSymlinks($from, $to)
    {
        $dir = new RecursiveDirectoryIterator($to);
        foreach (new RecursiveIteratorIterator($dir) as $file) {
            if (is_link($file)) {
                $link = $file->getPathName();
                $target = $file->getLinkTarget();
                $newPath = str_replace($from, $to, $target);
                if ($target !== $newPath) {
                    Exec::ln($link, $newPath);
                }
            }
        }
    }
}
