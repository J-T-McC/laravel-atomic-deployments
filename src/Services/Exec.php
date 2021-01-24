<?php

namespace JTMcC\AtomicDeployments\Services;

use JTMcC\AtomicDeployments\Exceptions\ExecuteFailedException;

class Exec
{
    /**
     * @param string $command
     * @param array  $arguments
     *
     * @throws ExecuteFailedException
     *
     * @return string
     */
    private static function run(string $command, array $arguments = [])
    {
        $arguments = array_map(fn ($argument) => escapeshellarg($argument), $arguments);

        $command = escapeshellcmd(count($arguments) ? sprintf($command, ...$arguments) : $command);

        $output = [];
        $status = null;

        $result = trim(exec($command, $output, $status));

        //non zero status means execution failed
        //see https://www.linuxtopia.org/online_books/advanced_bash_scripting_guide/exitcodes.html
        if ($status) {
            throw new ExecuteFailedException("resulted in exit code {$status}");
        }

        return $result;
    }

    /**
     * @param $link
     *
     * @throws ExecuteFailedException
     *
     * @return string
     */
    public static function readlink($link)
    {
        return self::run('readlink -f %s', [$link]);
    }

    /**
     * @param string $link
     * @param string $path
     *
     * @throws ExecuteFailedException
     *
     * @return string
     */
    public static function ln(string $link, string $path)
    {
        return self::run('ln -sfn %s %s', [$path, $link]);
    }

    /**
     * @param string $from
     * @param string $to
     *
     * @throws ExecuteFailedException
     *
     * @return string
     */
    public static function rsync(string $from, string $to)
    {
        return self::run('rsync -aW --no-compress %s %s', [$from, $to]);
    }

    /**
     * @throws ExecuteFailedException
     *
     * @return string
     */
    public static function getGitHash()
    {
        return self::run('git log --pretty="%h" -n1');
    }

}
