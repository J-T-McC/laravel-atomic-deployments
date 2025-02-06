<?php

namespace JTMcC\AtomicDeployments\Exceptions;

use Exception;
use Throwable;

class ExecuteFailedException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("exec failed: {$message}", $code, $previous);
    }
}
