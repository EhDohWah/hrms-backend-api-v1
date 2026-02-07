<?php

namespace App\Exceptions;

use RuntimeException;

class SafeDeleteBlockedException extends RuntimeException
{
    /**
     * @param  array<string>  $blockers  Human-readable blocker messages
     */
    public function __construct(
        public readonly array $blockers,
        string $message = 'Deletion blocked due to existing dependencies',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
