<?php

namespace App\Providers;

use App\Messaging\Contracts\NotificationProvider;
use App\Messaging\Providers\LogNotificationProvider;
use App\Messaging\Providers\MetaWhatsAppProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Proveedor de mensajería según config/messaging.php (MESSAGING_DRIVER).
        //   'log'  -> simulación (no envía; útil para pruebas)
        //   'meta' -> WhatsApp Cloud API de Meta (envío real, por defecto)
        $this->app->bind(NotificationProvider::class, function () {
            return match (config('messaging.driver')) {
                'log' => new LogNotificationProvider,
                default => new MetaWhatsAppProvider(
                    graphVersion: config('services.meta.graph_version'),
                ),
            };
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
