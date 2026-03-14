<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \App\Providers\HttpsServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*', headers: 
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'livewire/upload-file',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\LogRequests::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        ], append: [
            \App\Http\Middleware\ApiResponseFormatter::class,
        ]);

        $middleware->alias([
            'api.rate_limit' => \App\Http\Middleware\ApiRateLimit::class,
            'optional.auth' => \App\Http\Middleware\OptionalAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Authentication errors
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Authentication required',
                        'timestamp' => now()->toISOString(),
                    ]
                ], 401);
            }
        });

        // Authorization errors
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Access denied',
                        'timestamp' => now()->toISOString(),
                    ]
                ], 403);
            }
        });

        // Validation errors
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'errors' => $e->errors(),
                        'timestamp' => now()->toISOString(),
                    ]
                ], 422);
            }
        });

        // Model not found
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'The requested resource was not found.',
                        'timestamp' => now()->toISOString(),
                    ]
                ], 404);
            }
        });

        // Duplicate entry / unique constraint
        $exceptions->render(function (\Illuminate\Database\UniqueConstraintViolationException $e, $request) {
            \Illuminate\Support\Facades\Log::warning('Unique constraint violation', [
                'url' => $request->url(),
                'error' => $e->getMessage(),
            ]);

            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DUPLICATE_ENTRY',
                        'message' => 'This record already exists.',
                        'timestamp' => now()->toISOString(),
                    ]
                ], 409);
            }

            // Filament / web — redirect back with error
            return back()->with('notification', [
                'title' => 'Duplicate entry',
                'body' => 'A record with this information already exists. Please use a different value.',
                'status' => 'danger',
            ]);
        });

        // Database errors
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            \Illuminate\Support\Facades\Log::error('Database error', [
                'url' => $request->url(),
                'error' => $e->getMessage(),
            ]);

            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DATABASE_ERROR',
                        'message' => 'A database error occurred. Please try again.',
                        'timestamp' => now()->toISOString(),
                    ]
                ], 500);
            }
        });

        // Catch-all for any unhandled exception (API only — web uses Laravel's error pages)
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') && !config('app.debug')) {
                \Illuminate\Support\Facades\Log::error('Unhandled exception', [
                    'url' => $request->url(),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INTERNAL_SERVER_ERROR',
                        'message' => 'Something went wrong. Please try again later.',
                        'timestamp' => now()->toISOString(),
                    ]
                ], 500);
            }
        });
    })->create();
