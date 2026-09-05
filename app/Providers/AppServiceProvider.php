<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');

              URL::forceRootUrl(
                'https://adinntech.in/design-workflow'
            );
Livewire::setUpdateRoute(function ($handle) {
                return Route::post(
                    '/livewire-da2b704c/update',
                    $handle
                );
            });

        }
    }
}


