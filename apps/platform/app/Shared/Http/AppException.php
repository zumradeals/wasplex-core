<?php

declare(strict_types=1);

namespace App\Shared\Http;

use RuntimeException;
use Throwable;

/**
 * Base for business errors rendered as the standard Wasplex JSON error
 * shape (docs/20-architecture-technique-generale-wasplex.md #64):
 * { "code", "message", "details", "trace_id" }.
 */
class AppException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly int $status = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
