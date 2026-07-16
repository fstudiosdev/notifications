<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Le pregunta a Twilio el estado REAL de los mensajes que enviamos.
 *
 * Sirve para diagnosticar sin entrar a la consola de Twilio: usa el Account
 * SID / Auth Token que ya están guardados en la instancia.
 *
 * Importante: en nuestro panel, "sent" solo significa que Twilio ACEPTÓ el
 * mensaje. El estado de ENTREGA real (delivered / undelivered / failed) y su
 * código de error solo los sabe Twilio, y es lo que trae este comando.
 */
class CheckTwilioStatus extends Command
{
    protected $signature = 'twilio:check
        {--tenant= : Slug de la instancia (por defecto, todas las de Twilio)}
        {--limit=5 : Cuántos mensajes recientes revisar}';

    protected $description = 'Consulta a Twilio el estado real de los últimos mensajes enviados.';

    public function handle(): int
    {
        $tenants = Tenant::where('provider', 'twilio')
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No hay instancias con proveedor Twilio.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->checkTenant($tenant);
        }

        return self::SUCCESS;
    }

    private function checkTenant(Tenant $tenant): void
    {
        $this->newLine();
        $this->info("Instancia: {$tenant->name} ({$tenant->slug})");

        if (! $tenant->twilio_account_sid || ! $tenant->twilio_auth_token) {
            $this->warn('  Sin credenciales de Twilio cargadas.');

            return;
        }

        $messages = $tenant->notifications()
            ->where('direction', 'outbound')
            ->whereNotNull('provider_message_id')
            ->latest('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($messages->isEmpty()) {
            $this->warn('  No hay mensajes enviados con SID de Twilio.');

            return;
        }

        $rows = [];

        foreach ($messages as $notification) {
            $data = $this->fetch($tenant, $notification->provider_message_id);

            if ($data === null) {
                $rows[] = [$notification->id, $notification->provider_message_id, '(no se pudo consultar)', '', ''];

                continue;
            }

            // Guardamos el estado real para que el panel deje de mentir.
            $notification->update([
                'status' => $data['status'] ?? $notification->status,
                'error' => $data['error_message'] ?? null,
            ]);

            $rows[] = [
                $notification->id,
                // El "To" que Twilio REALMENTE recibió.
                $data['to'] ?? '?',
                $data['status'] ?? '?',
                $data['error_code'] ?? '—',
                $this->explain($data['error_code'] ?? null) ?: ($data['error_message'] ?? '—'),
            ];
        }

        $this->table(['ID', 'To (según Twilio)', 'Estado real', 'Error', 'Qué significa'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(Tenant $tenant, string $sid): ?array
    {
        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages/%s.json',
            $tenant->twilio_account_sid,
            $sid,
        );

        $response = Http::withBasicAuth($tenant->twilio_account_sid, $tenant->twilio_auth_token)
            ->get($url);

        if ($response->failed()) {
            $this->warn("  {$sid}: Twilio respondió {$response->status()} - ".$response->json('message', ''));

            return null;
        }

        return $response->json();
    }

    /**
     * Traduce los códigos de error más comunes de WhatsApp/Twilio.
     */
    private function explain(int|string|null $code): ?string
    {
        return match ((string) $code) {
            '63016' => 'Fuera de la ventana de 24h: hay que usar plantilla.',
            '63015' => 'El número no tiene WhatsApp.',
            '63003' => 'Destinatario no encontrado / no alcanzable.',
            '63007' => 'El número emisor no está habilitado para WhatsApp.',
            '21211' => 'Número "To" inválido.',
            '63005' => 'Mensaje bloqueado por WhatsApp (contenido).',
            '63018' => 'Demasiados mensajes al mismo usuario.',
            default => null,
        };
    }
}
