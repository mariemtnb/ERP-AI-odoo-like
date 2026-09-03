<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers on every response.
        $middleware->append(SecurityHeaders::class);
        // Apply the global `api` rate limiter to every API route, not just the
        // hand-picked ones. Tighter per-route throttles still stack on top.
        $middleware->throttleApi();
        $middleware->alias([
            'role' => EnsureRole::class,
            // Finer-grained successor to `role:`; existing routes keep theirs
            // until each module is migrated deliberately.
            'can.perm' => EnsurePermission::class,
            'feature' => EnsureFeature::class,
            'active' => EnsureActiveUser::class,
            // Enforces a custom role's module allowlist; a no-op for built-ins.
            'module.access' => EnsureModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
