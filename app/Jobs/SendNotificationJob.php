<?php

namespace App\Jobs;

use App\Messaging\NotificationDispatcher;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Ejecuta el envío de una notificación en segundo plano.
 *
 * Reintenta ante fallos transitorios; si agota los intentos, marca la
 * notificación como fallida (ver failed()).
 */
class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Espera (segundos) entre reintentos.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $notificationId,
    ) {
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification) {
            return;
        }

        $dispatcher->process($notification);
    }

    /**
     * Se llama cuando el job agota todos los reintentos.
     */
    public function failed(Throwable $e): void
    {
        Notification::where('id', $this->notificationId)->update([
            'status' => 'failed',
            'error' => 'Job agotó reintentos: '.$e->getMessage(),
        ]);
    }
}
