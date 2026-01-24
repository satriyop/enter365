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
        // Bind as scoped singleton - resolved only when needed by services
        // This allows auth()->id() to be correctly resolved AFTER Sanctum/auth middleware runs
        app()->scoped(OperationContext::class, function () use ($request) {
            return new OperationContext(
                userId: auth()->id(),
                tenantId: $this->resolveTenantId($request),
                ipAddress: $request->ip(),
                timestamp: now(),
                metadata: [
                    'source' => 'http',
                    'request_id' => $request->header('X-Request-ID'),
                ],
            );
        });

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
