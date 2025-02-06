<?php

namespace JTMcC\AtomicDeployments\Helpers;

use Illuminate\Console\Command;

/**
 * @method static void line(string $string)
 * @method static void alert(string $string)
 * @method static void error(string $string)
 * @method static void info(string $string)
 * @method static void table(string $string)
 */
class ConsoleOutput
{
    public static ?Command $runningCommand = null;

    public function setOutput(Command $runningCommand): void
    {
        static::$runningCommand = $runningCommand;
    }

    public static function __callStatic(string $method, $arguments)
    {
        if (! static::$runningCommand) {
            return;
        }

        static::$runningCommand->$method(...$arguments);
    }
}
