<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            Log::info('Forcing secure URLs for Livewire in production.');
            Livewire::forceSecure();
        }

        // Log session and CSRF details on every request for debugging
        if (env('APP_DEBUG')) {
            $this->app['router']->aliasMiddleware('log.requests', \App\Http\Middleware\LogRequests::class);
            if (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], 'livewire')) {
                Log::info('Livewire request detected.', [
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'session_id' => session()->getId(),
                    'csrf_token' => session()->token(),
                    'has_valid_csrf' => request()->hasHeader('x-csrf-token') && request()->header('x-csrf-token') === session()->token(),
                    'headers' => request()->headers->all(),
                ]);
            }
        }
    }
}
