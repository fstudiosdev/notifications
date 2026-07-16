<?php

namespace App\Messaging;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\Tenant;

/**
 * Orquesta el envío de notificaciones salientes.
 *
 * - queue():   registra la notificación (estado "queued") y encola el envío.
 * - process(): lo ejecuta el job en segundo plano; llama al proveedor y
 *              actualiza el estado según el resultado.
 *
 * El proveedor concreto (Meta / Twilio / simulación) lo resuelve el
 * ProviderManager según la instancia.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly ProviderManager $providers,
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
            // Normalizado (solo dígitos) para poder casar después la respuesta
            // del cliente, que llega en otro formato.
            'to_address' => PhoneNumber::normalize($message->to),
            'from_address' => PhoneNumber::normalize($this->senderFor($tenant)),
            'type' => $message->type,
            'payload' => $this->payloadFor($message),
            'reference' => $message->reference,
            'provider' => $this->providers->for($tenant)->name(),
            'status' => 'queued',
        ]);
    }

    /**
     * Número emisor de la instancia, según su proveedor.
     */
    private function senderFor(Tenant $tenant): ?string
    {
        return $tenant->provider === 'twilio'
            ? $tenant->twilio_from
            : $tenant->wa_phone_number;
    }

    /**
     * Ejecuta el envío real contra el proveedor y actualiza el estado.
     * Lo invoca el job.
     */
    public function process(Notification $notification): void
    {
        $tenant = $notification->tenant;
        $message = $this->messageFromNotification($notification);

        $result = $this->providers->for($tenant)->send($tenant, $message);

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
                reference: $notification->reference,
            );
        }

        return OutboundMessage::text(
            to: $notification->to_address,
            body: $payload['text'] ?? '',
            reference: $notification->reference,
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
