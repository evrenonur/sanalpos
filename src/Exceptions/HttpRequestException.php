<?php

namespace EvrenOnur\SanalPos\Exceptions;

use RuntimeException;

/**
 * HTTP isteği sırasında oluşan hataları temsil eder.
 */
class HttpRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $url = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
