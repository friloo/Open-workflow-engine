<?php

use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\CheckTokenAbility;
use App\Http\Middleware\EnforceTwoFactorByRole;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\PerformanceAudit;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [SetLocale::class, RedirectIfNotInstalled::class, EnforceTwoFactorByRole::class, PerformanceAudit::class]);
        $middleware->api(append: [PerformanceAudit::class]);
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
            'token.auth' => AuthenticateToken::class,
            'token.ability' => CheckTokenAbility::class,
        ]);

        // SAML-Callback ist POST vom IdP — CSRF-Token kann der IdP nicht
        // mitliefern. Stattdessen wird die Antwort vom IdP signiert und
        // im SamlLoginController validiert.
        $middleware->validateCsrfTokens(except: [
            'auth/saml/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
