<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El proveedor de mensajería se resuelve por instancia en
        // App\Messaging\ProviderManager (Meta / Twilio / simulación).
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
