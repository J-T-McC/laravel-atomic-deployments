<?php

namespace JTMcC\AtomicDeployments\Helpers;

use Illuminate\Console\Command;

class ConsoleOutput
{
    public static ?Command $runningCommand = null;

    /**
     * @param Command $runningCommand
     */
    public function setOutput(Command $runningCommand)
    {
        static::$runningCommand = $runningCommand;
    }

    /**
     * @param string $method
     * @param $arguments
     */
    public static function __callStatic(string $method, $arguments)
    {
        if (!static::$runningCommand) {
            return;
        }

        static::$runningCommand->$method(...$arguments);
    }
}
