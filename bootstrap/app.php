<?php

use App\Support\TelegramExceptionReporter;
use App\Http\Middleware\EnsureApiUserHasActiveAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveApiUser;
use App\Http\Middleware\ResolveTelegramAppUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'api.user' => ResolveApiUser::class,
            'api.user.access' => EnsureApiUserHasActiveAccess::class,
            'telegram.app' => ResolveTelegramAppUser::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('wireguard.active-peers'));

        $middleware->preventRequestForgery(except: [
            'api/users/register',
            'api/users/*/save-id',
            'telegram-app/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $throwable) {
            rescue(
                fn () => app(TelegramExceptionReporter::class)->report($throwable),
                report: false,
            );
        });
    })->create();
