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

        // HSTS only on HTTPS: tells browsers to refuse plain HTTP for a year.
        // Never sent over plain HTTP (which would pin an unreachable scheme in
        // local/dev). Behind a TLS-terminating proxy, trusted-proxy config must
        // forward X-Forwarded-Proto for isSecure() to be true.
        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
