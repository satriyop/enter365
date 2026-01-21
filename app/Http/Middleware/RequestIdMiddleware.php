<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure every request has a unique identifier.
 *
 * The request ID is used for:
 * - Distributed tracing
 * - Correlating logs across services
 * - Debugging specific requests
 *
 * If a client provides X-Request-ID header, it will be used.
 * Otherwise, a new UUID is generated.
 */
class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?? Str::uuid()->toString();

        // Make request ID available globally
        $request->headers->set('X-Request-ID', $requestId);

        // Process request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
