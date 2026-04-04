<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\EnsureNotBanned;
use App\Http\Middleware\GameParticipant;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'not.banned' => EnsureNotBanned::class,
            'admin' => AdminOnly::class,
            'game.player' => GameParticipant::class,
        ]);

        $middleware->appendToGroup('web', UpdateLastSeen::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
