<?php

namespace JTMcC\AtomicDeployments\Exceptions;

use Exception;
use Throwable;

class InvalidPathException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Invalid Path: {$message}", $code, $previous);
    }
}
