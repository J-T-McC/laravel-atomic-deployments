<?php

namespace JTMcC\AtomicDeployments\Services;

use Illuminate\Support\Facades\Log;
use JTMcC\AtomicDeployments\Helpers\ConsoleOutput;

class Output
{
    /**
     * Print throwable to console | log.
     */
    public static function throwable(\Throwable $obj): void
    {
        $title = get_class($obj);
        $file = $obj->getFile().' line '.$obj->getLine();
        $message = $obj->getMessage();
        $stack = $obj->getTraceAsString();

        self::error(
            <<<EOD
        $title
        $file
        $message
        $stack
        EOD
        );
    }

    public static function alert(string $message): void
    {
        ConsoleOutput::line('');
        ConsoleOutput::alert($message);
        Log::info($message);
    }

    public static function error(string $message): void
    {
        ConsoleOutput::error($message);
        Log::error($message);
    }

    public static function emergency(string $message): void
    {
        ConsoleOutput::error($message);
        Log::emergency($message);
    }

    public static function info(string $message): void
    {
        ConsoleOutput::info($message);
        Log::info($message);
    }

    public static function warn(string $message): void
    {
        ConsoleOutput::warn($message);
        Log::warning($message);
    }

    public static function line(): void
    {
        ConsoleOutput::line('');
    }
}
