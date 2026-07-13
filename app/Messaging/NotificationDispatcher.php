<?php

namespace App\Messaging;

use App\Jobs\SendNotificationJob;
use App\Messaging\Contracts\NotificationProvider;
use App\Models\Notification;
use App\Models\Tenant;

/**
 * Orquesta el envío de notificaciones salientes.
 *
 * - queue():   registra la notificación (estado "queued") y encola el envío.
 * - process(): lo ejecuta el job en segundo plano; llama al proveedor y
 *              actualiza el estado según el resultado.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationProvider $provider,
    ) {
    }

    /**
     * Registra la notificación y despacha el job de envío. Devuelve de
     * inmediato, sin esperar a Meta.
     */
    public function queue(Tenant $tenant, OutboundMessage $message): Notification
    {
        $notification = $this->record($tenant, $message);

        SendNotificationJob::dispatch($notification->id);

        return $notification;
    }

    /**
     * Envía de forma SÍNCRONA (sin cola) y devuelve la notificación ya
     * procesada, con su estado final (sent/failed). Pensado para el botón
     * de prueba del panel, donde queremos ver el resultado al instante.
     */
    public function sendNow(Tenant $tenant, OutboundMessage $message): Notification
    {
        $notification = $this->record($tenant, $message);

        $this->process($notification);

        return $notification->refresh();
    }

    /**
     * Registra la notificación saliente en estado "queued".
     */
    private function record(Tenant $tenant, OutboundMessage $message): Notification
    {
        return $tenant->notifications()->create([
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'to_address' => $message->to,
            'from_address' => $tenant->wa_phone_number,
            'type' => $message->type,
            'payload' => $this->payloadFor($message),
            'provider' => $this->provider->name(),
            'status' => 'queued',
        ]);
    }

    /**
     * Ejecuta el envío real contra el proveedor y actualiza el estado.
     * Lo invoca el job.
     */
    public function process(Notification $notification): void
    {
        $tenant = $notification->tenant;
        $message = $this->messageFromNotification($notification);

        $result = $this->provider->send($tenant, $message);

        if ($result->success) {
            $notification->update([
                'status' => 'sent',
                'provider_message_id' => $result->providerMessageId,
                'error' => null,
            ]);
        } else {
            $notification->update([
                'status' => 'failed',
                'error' => $result->error,
            ]);
        }
    }

    /**
     * Reconstruye el mensaje a partir de lo guardado en la notificación.
     */
    private function messageFromNotification(Notification $notification): OutboundMessage
    {
        $payload = $notification->payload ?? [];

        if ($notification->type === 'template') {
            return OutboundMessage::template(
                to: $notification->to_address,
                name: $payload['template'] ?? '',
                language: $payload['language'] ?? 'es',
                params: $payload['params'] ?? [],
            );
        }

        return OutboundMessage::text(
            to: $notification->to_address,
            body: $payload['text'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(OutboundMessage $message): array
    {
        if ($message->type === 'template') {
            return [
                'template' => $message->templateName,
                'language' => $message->templateLanguage,
                'params' => $message->templateParams,
            ];
        }

        return ['text' => $message->text];
    }
}
