<?php

namespace App\Messaging;

use App\Messaging\Contracts\NotificationProvider;
use App\Messaging\Providers\LogNotificationProvider;
use App\Messaging\Providers\MetaWhatsAppProvider;
use App\Messaging\Providers\TwilioProvider;
use App\Models\Tenant;

/**
 * Resuelve qué proveedor de mensajería usar para cada instancia (tenant).
 *
 * - Si MESSAGING_DRIVER=log, TODAS las instancias simulan el envío
 *   (override global de pruebas), sin importar su proveedor.
 * - En otro caso, se usa el proveedor configurado en la instancia:
 *   'twilio' -> Twilio · cualquier otro valor -> Meta (por defecto).
 */
class ProviderManager
{
    /**
     * Proveedores válidos que una instancia puede elegir en el panel.
     */
    public const CHOICES = ['meta', 'twilio'];

    public function for(Tenant $tenant): NotificationProvider
    {
        if (config('messaging.driver') === 'log') {
            return new LogNotificationProvider;
        }

        return match ($tenant->provider) {
            'twilio' => new TwilioProvider,
            default => new MetaWhatsAppProvider(
                graphVersion: config('services.meta.graph_version'),
            ),
        };
    }
}
