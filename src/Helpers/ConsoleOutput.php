<?php

namespace JTMcC\AtomicDeployments\Helpers;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * @method static void line(string $string)
 * @method static void warn(string $string)
 * @method static void alert(string $string)
 * @method static void error(string $string)
 * @method static void info(string $string)
 * @method static void table(string[] $titles, Collection $rows)
 */
class ConsoleOutput
{
    public static ?Command $runningCommand = null;

    public function setOutput(Command $runningCommand): void
    {
        static::$runningCommand = $runningCommand;
    }

    public static function __callStatic(string $method, array $arguments)
    {
        if (! static::$runningCommand) {
            return;
        }

        static::$runningCommand->$method(...$arguments);
    }
}
