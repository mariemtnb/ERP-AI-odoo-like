<?php

namespace App\Http\Middleware;

use App\Models\FeatureFlag;
use Closure;
use Illuminate\Http\Request;

/**
 * Module gate: `feature:accounting`.
 *
 * Returns 404 rather than 403 — a disabled module should look absent, not
 * forbidden, so probing the API cannot map out which features exist.
 */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        if (! FeatureFlag::enabled($feature)) {
            return response()->json(
                ['detail' => 'This module is not enabled.'],
                404
            );
        }

        return $next($request);
    }
}
