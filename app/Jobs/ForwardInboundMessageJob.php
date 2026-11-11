<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Reenvía un mensaje entrante al sistema que consume el servicio
 * (Clinea, el del taller...), usando el callback_url de su instancia.
 *
 * El gateway solo transporta: qué hacer con la respuesta lo decide el
 * sistema cliente, no nosotros.
 */
class ForwardInboundMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 120];

    public function __construct(
        public readonly int $notificationId,
    ) {}

    public function handle(): void
    {
        $notification = Notification::with('tenant')->find($this->notificationId);

        if (! $notification || $notification->direction !== 'inbound') {
            return;
        }

        $tenant = $notification->tenant;

        if (blank($tenant?->callback_url)) {
            Log::info('Mensaje entrante sin callback_url configurado; no se reenvía.', [
                'tenant_id' => $tenant?->id,
                'notification_id' => $notification->id,
            ]);

            return;
        }

        $isButton = filled($notification->button_payload);

        $response = Http::asJson()
            ->timeout(15)
            ->post($tenant->callback_url, [
                'referencia' => $notification->reference,
                'telefono' => $notification->from_address,
                'tipo' => $isButton ? 'boton' : 'texto',
                'payload' => $notification->button_payload,
                'texto' => $isButton ? null : $this->textOf($notification),
                // El SID del proveedor es la llave con la que el sistema cliente
                // reconoce un mensaje que ya recibió. Este job reintenta hasta
                // cuatro veces si el callback falla, y sin el SID el cliente no
                // puede distinguir un reintento de un mensaje nuevo: lo guardaría
                // dos veces. Va también el momento real del mensaje, que no es
                // el del reenvío cuando hubo reintentos.
                'message_sid' => $notification->provider_message_id,
                'timestamp' => $notification->created_at?->toIso8601String(),
                // Fotos, notas de voz y documentos. Campo nuevo y opcional: el
                // sistema que no lo use lo ignora (el «tipo» sigue siendo texto).
                'adjuntos' => $this->attachmentsOf($notification),
            ]);

        if ($response->failed()) {
            // Lanza para que el job reintente con backoff.
            throw new RuntimeException(
                "El callback de '{$tenant->slug}' respondió {$response->status()}."
            );
        }

        $notification->update(['status' => 'forwarded']);
    }

    /**
     * Texto libre del mensaje entrante. Cada proveedor lo manda distinto:
     * Twilio en "Body" (form-urlencoded) y Meta en "text.body" (JSON).
     */
    private function textOf(Notification $notification): ?string
    {
        return data_get($notification->payload, 'Body')
            ?? data_get($notification->payload, 'text.body');
    }

    /**
     * Archivos del mensaje entrante: [{tipo, mime, url}].
     * Twilio: NumMedia + MediaUrlN / MediaContentTypeN (la URL puede pedir las
     * credenciales de la cuenta para abrirla). Meta: un objeto por tipo con su
     * id y mime_type, sin URL directa.
     *
     * @return list<array{tipo: string, mime: ?string, url: ?string, ref?: string}>
     */
    private function attachmentsOf(Notification $notification): array
    {
        $payload = (array) $notification->payload;
        $out = [];

        $count = min((int) data_get($payload, 'NumMedia', 0), 10);
        for ($i = 0; $i < $count; $i++) {
            $mime = data_get($payload, "MediaContentType{$i}");
            $out[] = [
                'tipo' => $this->kindOf($mime),
                'mime' => $mime,
                'url' => data_get($payload, "MediaUrl{$i}"),
                // Para pedirle el archivo al gateway: GET /api/inbound/{ref}.
                'ref' => "{$notification->id}/media/{$i}",
            ];
        }

        $metaTypes = ['image' => 'imagen', 'audio' => 'audio', 'voice' => 'audio', 'video' => 'video', 'document' => 'documento', 'sticker' => 'sticker'];
        $type = data_get($payload, 'type');
        if ($out === [] && isset($metaTypes[$type])) {
            $out[] = ['tipo' => $metaTypes[$type], 'mime' => data_get($payload, "{$type}.mime_type"), 'url' => null];
        }

        return $out;
    }

    private function kindOf(?string $mime): string
    {
        return match (true) {
            str_starts_with((string) $mime, 'image/webp') => 'sticker',
            str_starts_with((string) $mime, 'image/') => 'imagen',
            str_starts_with((string) $mime, 'audio/') => 'audio',
            str_starts_with((string) $mime, 'video/') => 'video',
            default => 'documento',
        };
    }

    public function failed(Throwable $e): void
    {
        Log::error('No se pudo reenviar el mensaje entrante al sistema cliente.', [
            'notification_id' => $this->notificationId,
            'error' => $e->getMessage(),
        ]);

        Notification::where('id', $this->notificationId)
            ->update(['status' => 'forward_failed', 'error' => $e->getMessage()]);
    }
}
