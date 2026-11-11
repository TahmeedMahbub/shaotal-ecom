<?php

use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateCustomerSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global so 404s (thrown before route middleware) and other error pages get the headers too.
        $middleware->append(SecurityHeaders::class);
        // After StartSession: ends sessions of deleted/blocked customers and of sessions older than a password change.
        $middleware->appendToGroup('web', ValidateCustomerSession::class);
        $middleware->alias(['customer' => EnsureCustomer::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
