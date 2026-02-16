<?php

use App\Http\Middleware\SuperAdmin;
use App\Jobs\RetryDriverAssignmentJob;
use Illuminate\Foundation\Application;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\CheckApiClientBlocked;
use Spatie\Permission\Middleware\RoleMiddleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'api.key' => ApiKeyMiddleware::class,
            'check.apiclient.blocked' => CheckApiClientBlocked::class,
            'update.activity' => \App\Http\Middleware\UpdateUserActivity::class,
        ]);

        $middleware->priority([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\UpdateUserActivity::class,

        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(new RetryDriverAssignmentJob)->everyFiveMinutes();
        $schedule->call(function () {
        \App\Models\UserToken::where('expires_at', '<', now())->delete();
    })->daily();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
