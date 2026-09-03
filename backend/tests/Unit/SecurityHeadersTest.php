<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

/** Baseline security headers, and HSTS only over HTTPS. */
class SecurityHeadersTest extends TestCase
{
    private function through(Request $request): Response
    {
        return (new SecurityHeaders)->handle($request, fn () => new Response('ok'));
    }

    public function test_baseline_headers_are_set(): void
    {
        $res = $this->through(Request::create('https://app.test/api/v1/x', 'GET'));

        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $res->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("default-src 'none'", $res->headers->get('Content-Security-Policy'));
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $res = $this->through(Request::create('https://app.test/api/v1/x', 'GET'));

        $this->assertTrue($res->headers->has('Strict-Transport-Security'));
        $this->assertStringContainsString('max-age=31536000', $res->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        $res = $this->through(Request::create('http://app.test/api/v1/x', 'GET'));

        $this->assertFalse($res->headers->has('Strict-Transport-Security'));
    }
}
