<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers on every API response. The SPA served by
 * nginx sets its own document-level CSP (see docker/nginx-frontend.conf); these
 * harden the JSON API — which renders nothing — with a locked-down policy and
 * the usual anti-sniffing / anti-framing headers.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=()',
            // The API returns JSON only — nothing should ever load or frame it.
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'",
            'Cross-Origin-Resource-Policy' => 'same-site',
        ];
        foreach ($headers as $k => $v) {
            if (! $response->headers->has($k)) {
                $response->headers->set($k, $v);
            }
        }

        return $response;
    }
}
