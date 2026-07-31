<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;

/**
 * Permission gate: `can:sales.confirm` or `can:sales.confirm,sales.update`
 * (any one of them suffices).
 *
 * The finer-grained successor to `role:`. New routes should prefer it;
 * existing routes keep their `role:` groups until they are migrated
 * deliberately, one module at a time.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();

        if (! $user || ! PermissionService::hasAny($user, $permissions)) {
            return response()->json([
                'detail' => 'You do not have permission to perform this action.',
                'required' => $permissions,
            ], 403);
        }

        return $next($request);
    }
}
