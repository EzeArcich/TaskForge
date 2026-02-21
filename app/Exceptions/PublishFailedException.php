<?php

namespace App\Exceptions;

use RuntimeException;

class PublishFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to publish plan to external services.',
        public readonly string $provider = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
