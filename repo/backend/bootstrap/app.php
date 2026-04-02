<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use App\Http\Middleware\AuditLoggerMiddleware;
use App\Http\Middleware\CheckMuted;
use App\Http\Middleware\JwtAuth;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ScopeCheck;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
        ]);

        $middleware->alias([
            'app.jwt' => JwtAuth::class,
            'check.muted' => CheckMuted::class,
            'audit.logger' => AuditLoggerMiddleware::class,
            'scope.check' => ScopeCheck::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
