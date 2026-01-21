<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\OperationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds OperationContext to the container for the current request.
 *
 * Laravel way: Middleware handles cross-cutting concerns.
 * Services automatically resolve context from container - no manual calls needed.
 *
 * Future multi-tenant: Add tenant resolution here, all services automatically scoped.
 */
class BindOperationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Create context from current request
        $context = new OperationContext(
            userId: auth()->id(),
            tenantId: $this->resolveTenantId($request),
            ipAddress: $request->ip(),
            timestamp: now(),
            metadata: [
                'source' => 'http',
                'request_id' => $request->header('X-Request-ID'),
            ],
        );

        // Bind as scoped singleton - same instance for entire request lifecycle
        app()->scoped(OperationContext::class, fn () => $context);

        return $next($request);
    }

    /**
     * Resolve tenant ID from request.
     *
     * TODO: Implement when multi-tenant infrastructure is ready.
     * Options:
     * - From authenticated user: auth()->user()?->tenant_id
     * - From subdomain: explode('.', $request->getHost())[0]
     * - From header: $request->header('X-Tenant-ID')
     * - From route parameter: $request->route('tenant')
     */
    private function resolveTenantId(Request $request): ?int
    {
        // Placeholder for future multi-tenant support
        // return auth()->user()?->tenant_id;
        return null;
    }
}
