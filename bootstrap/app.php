<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Percayai semua proxy di depan Laravel (Traefik / Coolify)
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'message' => 'Sesi Anda telah berakhir. Silakan login kembali.',
                        'redirect' => route('login'),
                    ], 419);
                }

                return redirect()->route('login')->with('warning', 'Sesi Anda telah berakhir karena lama tidak aktif. Silakan login kembali.');
            }
        });
    })->create();