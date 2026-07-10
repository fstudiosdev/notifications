<?php

namespace App\Providers;

use App\Messaging\Contracts\NotificationProvider;
use App\Messaging\Providers\MetaWhatsAppProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Proveedor de mensajería por defecto: Meta WhatsApp Cloud API.
        // Cambiar a Twilio (u otro) es cambiar solo esta línea.
        $this->app->bind(NotificationProvider::class, function () {
            return new MetaWhatsAppProvider(
                graphVersion: config('services.meta.graph_version'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
