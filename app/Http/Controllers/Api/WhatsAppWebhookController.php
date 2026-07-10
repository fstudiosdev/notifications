<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de la WhatsApp Cloud API de Meta.
 *
 * Meta llama a estos endpoints:
 *  - GET:  verificación inicial (challenge) al configurar el webhook.
 *  - POST: entrega de mensajes entrantes y actualizaciones de estado
 *          (sent/delivered/read/failed) de los mensajes salientes.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Verificación del webhook. Meta manda hub.mode, hub.verify_token y
     * hub.challenge; devolvemos el challenge si el token coincide.
     */
    public function verify(Request $request): mixed
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.meta.webhook_verify_token')) {
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Recepción de eventos. Siempre respondemos 200 rápido: si devolvemos
     * error, Meta reintenta y puede acabar deshabilitando el webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                $value = Arr::get($change, 'value', []);

                $this->handleStatuses(Arr::get($value, 'statuses', []));
                $this->handleIncomingMessages($value);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * Actualiza el estado de los mensajes salientes según el wamid.
     *
     * @param  array<int, array<string, mixed>>  $statuses
     */
    private function handleStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            $wamid = Arr::get($status, 'id');
            $state = Arr::get($status, 'status'); // sent|delivered|read|failed

            if (! $wamid || ! $state) {
                continue;
            }

            Notification::where('provider_message_id', $wamid)->update([
                'status' => $state,
                'error' => Arr::get($status, 'errors.0.title'),
            ]);
        }
    }

    /**
     * Registra los mensajes entrantes. Aquí se engancha, más adelante,
     * el chatbot / asistente virtual.
     *
     * @param  array<string, mixed>  $value
     */
    private function handleIncomingMessages(array $value): void
    {
        $messages = Arr::get($value, 'messages', []);

        if (empty($messages)) {
            return;
        }

        // El número que recibió el mensaje identifica al tenant.
        $phoneNumberId = Arr::get($value, 'metadata.phone_number_id');
        $tenant = $phoneNumberId
            ? Tenant::where('wa_phone_number_id', $phoneNumberId)->first()
            : null;

        if (! $tenant) {
            Log::warning('WhatsApp webhook: mensaje entrante sin tenant', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return;
        }

        foreach ($messages as $message) {
            $tenant->notifications()->create([
                'channel' => 'whatsapp',
                'direction' => 'inbound',
                'to_address' => $tenant->wa_phone_number ?? (string) $phoneNumberId,
                'from_address' => Arr::get($message, 'from'),
                'type' => Arr::get($message, 'type', 'text'),
                'payload' => $message,
                'provider' => 'meta',
                'provider_message_id' => Arr::get($message, 'id'),
                'status' => 'received',
            ]);

            // TODO: aquí se dispara el chatbot / asistente (respuesta automática).
        }
    }
}
