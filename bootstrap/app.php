<?php

use App\Http\Middleware\AdminTwoFactorMiddleware;
use App\Http\Middleware\EnsureMerchantApproved;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\MerchantMiddleware;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifiedMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'payment/webhook/*',
            'payment/ipn/*',
            'payment/callback/*',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'guest' => RedirectIfAuthenticated::class,
            'verified' => VerifiedMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'merchant.approved' => EnsureMerchantApproved::class,
            'merchant' => MerchantMiddleware::class,
            'admin.2fa' => AdminTwoFactorMiddleware::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
