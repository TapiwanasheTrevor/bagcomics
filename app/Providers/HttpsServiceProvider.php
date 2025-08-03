<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class HttpsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Force HTTPS in production or when FORCE_HTTPS is true
        if (config('app.force_https') || $this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Trust proxies (for Render and other cloud platforms)
        if ($this->app->environment('production')) {
            $this->app['request']->server->set('HTTPS', 'on');
        }
    }
}
