<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', \App\Http\Middleware\TouchAdminActivity::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\RequiresPolicyAcceptance::class);

        $middleware->alias([
            'policy.accepted' => \App\Http\Middleware\RequiresPolicyAcceptance::class,
        ]);

        // This is a plaintext UI-preference cookie set client-side by JS
        // (users index "Show profile photos"). Exempt it from encryption so
        // the server can read it back via $request->cookie().
        $middleware->encryptCookies(except: ['users_show_photos']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return null;
            }

            return redirect()->route('login')->with('status', 'Your session had expired. Please log in again.');
        });
    })
    ->create();

/**
 * Your public web root is /www/dev (outside the Laravel project’s /public).
 * From /laravel/bootstrap, two levels up is /www/dev.
 */
if (env('APP_ENV') === 'production') {
    // two levels up from /laravel/bootstrap is /www/dev
    $app->usePublicPath(dirname(__DIR__, 2));
}

return $app;
