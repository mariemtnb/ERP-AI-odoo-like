<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Rate limits.
     *
     * Only login, register and agent chat were throttled; every other endpoint
     * was unbounded, so one authenticated account could hammer reports or
     * enumerate records at will. `api` is a blanket ceiling on every
     * authenticated route — deliberately generous, because it exists to stop
     * abuse rather than to shape normal ERP use, where opening a busy page can
     * fire a dozen requests at once.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Keyed per user when known, per IP otherwise, so one noisy tenant
            // cannot exhaust the budget of everyone behind the same NAT.
            return Limit::perMinute(300)->by(
                $request->user()?->id
                    ? 'user:' . $request->user()->id
                    : 'ip:' . $request->ip()
            );
        });
    }
}
