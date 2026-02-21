<?php

namespace App\Exceptions;

use RuntimeException;

class NormalizationFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'AI normalization failed after retries.',
        public readonly array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
