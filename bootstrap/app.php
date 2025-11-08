<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    // Ensure compiled Blade views are isolated per parallel process to avoid race conditions
    ->booting(function (): void {
        \Illuminate\Support\Facades\ParallelTesting::setUpProcess(function (int $token): void {
            $path = storage_path('framework/views-'.$token);
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }

            putenv('VIEW_COMPILED_PATH='.$path);
            $_ENV['VIEW_COMPILED_PATH'] = $path;
            $_SERVER['VIEW_COMPILED_PATH'] = $path;
        });
    })
    ->create();
