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
    ) {
    }

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
