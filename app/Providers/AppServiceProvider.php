<?php

namespace App\Providers;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fix lazy-loaded console commands missing their Laravel container reference.
        // Commands resolved via ContainerCommandLoader bypass Application::add(),
        // so setLaravel() is never called. This afterResolving callback ensures
        // every Command gets the container set before it runs.
        $this->app->afterResolving(Command::class, function (Command $command) {
            if ($command->getLaravel() === null) {
                $command->setLaravel($this->app);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Register model observers
        \App\Models\Comic::observe(\App\Observers\ComicObserver::class);
    }
}
