<?php

namespace JTMcC\AtomicDeployments\Services;

use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;

class Exec
{
    /**
     * @throws ExecuteFailedException
     */
    private static function run(string $command, array $arguments = []): string
    {
        $arguments = array_map('escapeshellarg', $arguments);
        $command = escapeshellcmd(count($arguments) ? sprintf($command, ...$arguments) : $command);

        $output = [];
        $status = null;
        $result = trim(exec($command, $output, $status));

        if ($status) {
            throw new ExecuteFailedException(sprintf('Command resulted in exit code %d', $status));
        }

        return $result;
    }

    /**
     * @throws ExecuteFailedException
     */
    public static function readlink(string $link): string
    {
        return self::run('readlink -f %s', [$link]);
    }

    /**
     * @throws ExecuteFailedException
     */
    public static function ln(string $link, string $path): string
    {
        return self::run('ln -sfn %s %s', [$path, $link]);
    }

    /**
     * @throws ExecuteFailedException
     */
    public static function rsync(string $from, string $to): string
    {
        return self::run('rsync -aW --no-compress %s %s', [$from, $to]);
    }

    /**
     * @throws ExecuteFailedException
     */
    public static function getGitHash(): string
    {
        return self::run('git log --pretty="%h" -n1');
    }
}
