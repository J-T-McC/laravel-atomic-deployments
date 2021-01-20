<?php

namespace JTMcC\AtomicDeployments\Services;

use Illuminate\Support\Facades\Log;

use JTMcC\AtomicDeployments\Helpers\ConsoleOutput;

class Output
{


    /**
     * Print throwable to console | log
     *
     * @param \Throwable $obj
     */
    public static function throwable(\Throwable $obj) : void
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


    /**
     * @param string $message
     */
    public static function alert(string $message) : void
    {
        ConsoleOutput::newLine();
        ConsoleOutput::alert($message);
        Log::info($message);
    }


    /**
     * @param string $message
     */
    public static function error(string $message) : void
    {
        ConsoleOutput::error($message);
        Log::error($message);
    }


    /**
     * @param string $message
     */
    public static function emergency(string $message) : void
    {
        ConsoleOutput::error($message);
        Log::emergency($message);
    }


    /**
     * @param $message
     */
    public static function info(string $message) : void
    {
        ConsoleOutput::info($message);
        Log::info($message);
    }


    /**
     * @param $message
     */
    public static function warn(string $message) : void
    {
        ConsoleOutput::warn($message);
        Log::warning($message);
    }

}