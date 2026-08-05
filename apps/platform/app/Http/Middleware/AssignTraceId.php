<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a trace_id to every request: reused from an inbound X-Trace-Id
 * header when present (so a caller-supplied trace survives the request),
 * otherwise generated. Propagated on the response and into the log context
 * so every structured log line for this request carries it.
 */
final class AssignTraceId
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header('X-Trace-Id') ?: (string) Str::ulid();

        $request->attributes->set('trace_id', $traceId);
        Log::withContext(['trace_id' => $traceId]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
