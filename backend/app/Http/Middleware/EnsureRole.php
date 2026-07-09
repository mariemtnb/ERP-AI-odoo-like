<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Role gate: `role:admin` or `role:admin,manager`.
 * The RBAC matrix itself is expressed in routes/api.php route groups.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json(
                ['detail' => 'You do not have permission to perform this action.'],
                403
            );
        }

        return $next($request);
    }
}
