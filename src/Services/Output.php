<?php


namespace JTMcC\AtomicDeployments\Services;

use Illuminate\Support\Facades\Log;
use JTMcC\AtomicDeployments\Helpers\ConsoleOutput;

class Output
{

    public static function throwable(\Throwable $obj)
    {
        $title = get_class($obj);
        $file = $obj->getFile() . ' line ' . $obj->getLine();
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

    public static function alert($message)
    {
        ConsoleOutput::newLine();
        ConsoleOutput::alert($message);
        Log::info($message);
    }

    public static function error($message)
    {
        ConsoleOutput::error($message);
        Log::error($message);
    }

    public static function emergency($message)
    {
        ConsoleOutput::error($message);
        Log::emergency($message);
    }

    public static function info($message)
    {
        ConsoleOutput::info($message);
        Log::info($message);
    }

    public static function warn($message)
    {
        ConsoleOutput::warn($message);
        Log::warning($message);
    }

}