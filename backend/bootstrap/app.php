<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Exceptions\AiException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // AiException is an expected "domain" exception (e.g. rate limits); don't spam logs.
        $exceptions->dontReport([
            AiException::class,
        ]);

        $exceptions->render(function (AiException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = $e->statusCode ?? 500;

            return response()->json([
                'ok' => false,
                'error' => [
                    'message' => $e->getMessage(),
                    'status' => $status,
                    'request_id' => $e->requestId,
                    'retry_after' => $e->retryAfterSeconds,
                ],
            ], $status);
        });
    })->create();
