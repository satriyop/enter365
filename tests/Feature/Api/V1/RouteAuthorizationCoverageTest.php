<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Guard against F-01/F-03 regressing.
 *
 * Every authenticated API route must be gated either by the `permission:`
 * middleware or by an explicit authorize()/Gate call in its controller method.
 * A new route with neither is a silent authorization hole, so it fails here
 * rather than in production.
 */
function controllerEnforcesAuthorization(string $action): bool
{
    if (! str_contains($action, '@')) {
        return false;
    }

    [$class, $method] = explode('@', $action, 2);

    if (! class_exists($class)) {
        return false;
    }

    $file = (new ReflectionClass($class))->getFileName();
    if ($file === false) {
        return false;
    }

    if (! method_exists($class, $method)) {
        return false;
    }

    $reflection = new ReflectionMethod($class, $method);
    $lines = file($file);
    $body = implode('', array_slice(
        $lines,
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1
    ));

    // Authorization performed in the method body...
    if (preg_match('/authorize\(|Gate::|hasPermission\(|ensurePermission\(|assertOwnSession\(/', $body)) {
        return true;
    }

    // ...or delegated to a FormRequest that does not blanket-allow.
    if (preg_match('/([A-Za-z0-9_]+Request)\s+\$/', $body, $m)) {
        $requestClass = $m[1];
        $source = implode('', $lines);

        // Resolve the short name against the controller's own use statements.
        $candidates = [];
        if (preg_match('/^use\s+([^;]*\\'.$requestClass.');$/m', $source, $useMatch)) {
            $candidates[] = $useMatch[1];
        }
        $candidates[] = $requestClass;

        foreach ($candidates as $candidate) {
            if (! class_exists($candidate)) {
                continue;
            }
            if (! method_exists($candidate, 'authorize')) {
                continue;
            }

            // Follow authorize() to wherever it is actually declared — it may live
            // in a trait (e.g. AuthorizesPosPermission) rather than the request itself.
            $declaringFile = (new ReflectionMethod($candidate, 'authorize'))
                ->getDeclaringClass()
                ->getFileName();

            if ($declaringFile && ! preg_match('/function authorize\(\): bool\s*\{\s*return true;/s', file_get_contents($declaringFile))) {
                return true;
            }
        }
    }

    return false;
}

/** Routes intentionally reachable by any authenticated user. */
const AUTHENTICATED_ALLOWLIST = [
    'api/v1/auth/logout',
    'api/v1/auth/logout-all',
    'api/v1/auth/me',
    'api/v1/auth/refresh',
    'api/v1/features',
    'api/v1/company-profiles',
    'api/v1/company-profiles/{company_profile}',
    'api/v1/attachments/categories',
];

it('has no authenticated API route without an authorization gate', function () {
    $unprotected = [];

    foreach (Route::getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        if (! in_array('auth:sanctum', $middleware, true)) {
            continue; // public or non-API route
        }

        if (in_array($route->uri(), AUTHENTICATED_ALLOWLIST, true)) {
            continue;
        }

        $hasPermissionMiddleware = collect($middleware)
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));

        if ($hasPermissionMiddleware) {
            continue;
        }

        $action = $route->getActionName();
        if (controllerEnforcesAuthorization($action)) {
            continue;
        }

        $unprotected[] = implode('|', $route->methods()).' '.$route->uri().'  →  '.$action;
    }

    expect($unprotected)->toBe([], "Routes with no authorization gate:\n".implode("\n", $unprotected));
});

it('has no route pointing at a controller method that does not exist', function () {
    $missing = [];

    foreach (Route::getRoutes() as $route) {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            continue;
        }

        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class)) {
            $missing[] = $route->uri().'  →  missing class '.$class;

            continue;
        }

        if (! method_exists($class, $method)) {
            $missing[] = implode('|', $route->methods()).' '.$route->uri().'  →  '.$action;
        }
    }

    expect($missing)->toBe([], "Routes bound to nonexistent controller actions:\n".implode("\n", $missing));
});
