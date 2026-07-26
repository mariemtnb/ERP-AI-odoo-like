<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Deactivating an account must revoke access immediately.
 *
 * `is_active` used to be checked only at login, so a JWT issued before an
 * account was disabled stayed valid for its whole TTL — and because the
 * refresh endpoint did not check either, a deactivated user could keep
 * refreshing indefinitely. Offboarding an employee did not actually remove
 * their access to the ERP.
 *
 * Checking on every authenticated request costs one already-loaded attribute
 * and closes that hole.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Deny only on an explicit false. An absent attribute means the
        // instance was never hydrated with it — locking those out would turn
        // a missing column read into a lockout, which is the wrong failure
        // mode for an auth gate.
        if ($user && $user->getAttribute('is_active') !== null && ! $user->is_active) {
            return response()->json(
                ['detail' => 'This account has been deactivated.'],
                401
            );
        }

        return $next($request);
    }
}
