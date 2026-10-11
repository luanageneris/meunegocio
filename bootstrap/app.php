<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureUserIsRoot;
use App\Http\Middleware\EnsureCompanyIsolation;
use App\Http\Middleware\EnsureMasterAdminAccess;
use App\Http\Middleware\PreventAuthenticatedPageCache;
use App\Http\Middleware\TrackChanges;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\TranslateApiErrorMessages;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetSecurityHeaders::class,
        ]);

        $middleware->api(append: [
            TranslateApiErrorMessages::class,
            SetSecurityHeaders::class,
        ]);

        $middleware->alias([
            'root' => EnsureUserIsRoot::class,
            'master.admin' => EnsureMasterAdminAccess::class,
            'company.isolation' => EnsureCompanyIsolation::class,
            'track.changes' => TrackChanges::class,
            'prevent.auth.cache' => PreventAuthenticatedPageCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
