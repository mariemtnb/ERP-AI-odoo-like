<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;

/**
 * Enforces a custom role's module allowlist on the API.
 *
 * A user whose role carries an allowlist (a custom role) may only touch
 * resources belonging to a module on that list. Built-in roles carry no
 * allowlist and pass straight through — their access is shaped by the `role:`
 * gates on the routes, exactly as before.
 *
 * This is the server-side counterpart of the sidebar/route gating: the UI
 * hides what a restricted user cannot reach, and this makes the API refuse it
 * even if the URL is called directly. Resources with no module of their own
 * (auth, me, notifications, the admin console) are never restricted here.
 *
 * Must run AFTER `auth:api`, so the user is resolved.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $role = Role::where('key', $user->role)->first();
        $allowlist = $role?->moduleAllowlist();
        if ($allowlist === null) {
            // Built-in or unrestricted role — nothing to enforce.
            return $next($request);
        }

        $module = Modules::forSegment($this->resourceSegment($request));
        if ($module !== null && ! in_array($module, $allowlist, true)) {
            return response()->json(
                ['detail' => 'Your role does not have access to this module.'],
                403
            );
        }

        return $next($request);
    }

    /** The first path segment after the `api/v1` prefix, e.g. "tickets". */
    private function resourceSegment(Request $request): string
    {
        $parts = array_values(array_filter(explode('/', $request->path())));
        // Drop the leading api/version prefix segments.
        while ($parts && in_array($parts[0], ['api', 'v1'], true)) {
            array_shift($parts);
        }

        return $parts[0] ?? '';
    }
}
