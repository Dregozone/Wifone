<?php

use App\Http\Middleware\CaptureSignUpSource;
use App\Http\Middleware\EmbeddedSession;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Before the session starts: a framed request gets its own session cookie.
        $middleware->web(prepend: [EmbeddedSession::class], append: [CaptureSignUpSource::class, TrackUserActivity::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
