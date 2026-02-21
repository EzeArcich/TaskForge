<?php

use App\Exceptions\NormalizationFailedException;
use App\Exceptions\PublishFailedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $exceptions->render(function (NormalizationFailedException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'normalization_error',
                    'title' => 'Plan Normalization Failed',
                    'detail' => $e->getMessage(),
                    'errors' => $e->errors,
                ], 422);
            }
        });

        $exceptions->render(function (PublishFailedException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'publish_error',
                    'title' => 'Publish Failed',
                    'detail' => $e->getMessage(),
                    'provider' => $e->provider,
                ], 502);
            }
        });
    })->create();
