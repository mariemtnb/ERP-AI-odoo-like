<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Role gate: `role:admin` or `role:admin,manager`.
 * The RBAC matrix itself is expressed in routes/api.php route groups.
 *
 * Strictly additive since the permission engine landed: the original
 * `users.role` check runs first and unchanged, so nobody who passed before can
 * fail now. Custom roles are an extra way IN — a user holding a role that
 * inherits from `manager` satisfies `role:manager` — never a way to be locked
 * out.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user) {
            return $this->deny();
        }

        // 1. Original behaviour, untouched.
        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // 2. Custom roles: does any assigned role inherit from one named here?
        if ($this->holdsRoleInLineage($user, $roles)) {
            return $next($request);
        }

        return $this->deny();
    }

    /** True when one of the user's assigned roles inherits from `$roles`. */
    private function holdsRoleInLineage($user, array $roles): bool
    {
        try {
            $assigned = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->pluck('role_id');

            foreach ($assigned as $roleId) {
                $role = Role::find($roleId);
                if (! $role) {
                    continue;
                }
                foreach ($role->lineage() as $ancestor) {
                    if (in_array($ancestor->key, $roles, true)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            // Before the permission tables exist, step 1 is the whole gate.
            return false;
        }

        return false;
    }

    private function deny()
    {
        return response()->json(
            ['detail' => 'You do not have permission to perform this action.'],
            403
        );
    }
}
