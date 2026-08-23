<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure the authenticated user holds a permission.
 *
 * Route-level enforcement so that an unprotected endpoint is a visible omission
 * in the route file rather than a silent hole in a controller.
 *
 * Usage in routes:
 *   Route::middleware('permission:inventory.adjust')->post('adjust', ...);
 *   Route::middleware('permission:users.manage_roles')->group(function () { ... });
 *
 * Multiple permissions are treated as "any of":
 *   Route::middleware('permission:invoices.view,invoices.create')->get(...);
 *
 * A trailing "message:..." segment overrides the generic 403 copy:
 *   Route::middleware('permission:pos.session.open,message:Anda tidak boleh membuka sesi kasir.')
 *
 * Admin bypasses via HasRolesAndPermissions::hasPermission().
 */
class EnsurePermission
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Silakan masuk terlebih dahulu.');
        }

        $message = 'Anda tidak diizinkan melakukan ini.';

        $permissions = array_values(array_filter($permissions, function (string $permission) use (&$message): bool {
            if (str_starts_with($permission, 'message:')) {
                $message = substr($permission, strlen('message:'));

                return false;
            }

            return true;
        }));

        if ($permissions === []) {
            abort(403, $message);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, $message);
    }
}
