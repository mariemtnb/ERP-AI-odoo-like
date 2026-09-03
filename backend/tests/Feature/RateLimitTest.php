<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** The global API rate limiter caps every endpoint, not just the auth routes. */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_api_rate_limit_is_registered(): void
    {
        $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('api'));
    }

    public function test_a_plain_endpoint_is_throttled_once_the_limit_is_hit(): void
    {
        $user = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        config(['security.api_rate_limit' => 3]);   // read per request by the limiter
        Cache::flush();                              // clear any throttle counters

        // Products is an ordinary authenticated GET with no per-route throttle,
        // so only the global limiter applies.
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($user, 'api')->getJson('/api/v1/products')->assertOk();
        }
        $this->actingAs($user, 'api')->getJson('/api/v1/products')->assertStatus(429);
    }
}
