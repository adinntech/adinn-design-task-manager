<?php
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);

        // Behind the adinntech.in reverse proxy (path-based /design-workflow
        // deployment): trust X-Forwarded-* so Laravel derives its scheme/host
        // AND base path from the proxy's headers instead of from APP_URL. This
        // is the fix for the doubled "/design-workflow/design-workflow/..."
        // Livewire URL — see the deployment notes for the full root-cause
        // writeup. 'at: *' because the upstream proxy is a separate Droplet
        // with no fixed, known IP from inside this container; narrow it to
        // that Droplet's actual IP once known for tighter security.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
