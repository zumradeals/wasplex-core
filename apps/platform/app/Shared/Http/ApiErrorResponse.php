<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Builds the standard Wasplex API error shape.
 * Docs/20-architecture-technique-generale-wasplex.md #64.
 */
final class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function make(
        Request $request,
        string $code,
        string $message,
        array $details = [],
        int $status = 500,
    ): JsonResponse {
        return new JsonResponse([
            'code' => $code,
            'message' => $message,
            'details' => $details,
            'trace_id' => $request->attributes->get('trace_id'),
        ], $status);
    }
}
