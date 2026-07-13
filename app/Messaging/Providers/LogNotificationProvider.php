<?php

namespace App\Messaging\Providers;

use App\Messaging\Contracts\NotificationProvider;
use App\Messaging\OutboundMessage;
use App\Messaging\SendResult;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Proveedor de SIMULACIÓN (modo pruebas).
 *
 * No llama a Meta ni envía nada real: escribe el mensaje en el log y lo
 * marca como enviado, devolviendo un id de mensaje ficticio. Sirve para
 * probar todo el flujo (API / panel → cola → estado) sin credenciales de
 * WhatsApp.
 *
 * Se activa con MESSAGING_DRIVER=log en el .env.
 */
class LogNotificationProvider implements NotificationProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function send(Tenant $tenant, OutboundMessage $message): SendResult
    {
        $fakeId = 'sim_'.Str::lower(Str::random(24));

        Log::info('[SIMULACIÓN] Notificación no enviada realmente', [
            'tenant' => $tenant->slug,
            'to' => $message->to,
            'type' => $message->type,
            'text' => $message->text,
            'template' => $message->templateName,
            'params' => $message->templateParams,
            'fake_message_id' => $fakeId,
        ]);

        return SendResult::ok($fakeId);
    }
}
