<?php

namespace App\Ai;

use RuntimeException;
use Throwable;

/**
 * The provider timed out, failed, rate-limited us or returned nothing usable.
 */
class AiUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
