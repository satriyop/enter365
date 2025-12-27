<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure a feature module is enabled.
 *
 * Usage in routes:
 *   Route::middleware('feature:inventory')->group(function () { ... });
 *   Route::get('/mrp', ...)->middleware('feature:mrp');
 */
class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (Features::disabled($feature)) {
            abort(404, 'Feature tidak tersedia.');
        }

        return $next($request);
    }
}
