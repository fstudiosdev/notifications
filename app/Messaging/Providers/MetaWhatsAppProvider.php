<?php

namespace App\Messaging\Providers;

use App\Messaging\Contracts\NotificationProvider;
use App\Messaging\OutboundMessage;
use App\Messaging\SendResult;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Implementación contra la WhatsApp Cloud API de Meta (Graph API).
 *
 * Docs: https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class MetaWhatsAppProvider implements NotificationProvider
{
    public function __construct(
        private readonly string $graphVersion = 'v21.0',
    ) {
    }

    public function name(): string
    {
        return 'meta';
    }

    public function send(Tenant $tenant, OutboundMessage $message): SendResult
    {
        if (! $tenant->wa_phone_number_id || ! $tenant->wa_access_token) {
            return SendResult::fail('El tenant no tiene credenciales de WhatsApp configuradas.');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->graphVersion,
            $tenant->wa_phone_number_id,
        );

        try {
            $response = Http::withToken($tenant->wa_access_token)
                ->asJson()
                ->post($url, $this->buildPayload($message));
        } catch (Throwable $e) {
            Log::error('Meta WhatsApp: fallo de red al enviar', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return SendResult::fail('Error de conexión con Meta: '.$e->getMessage());
        }

        if ($response->failed()) {
            $error = $response->json('error.message', 'Error desconocido de Meta.');

            Log::warning('Meta WhatsApp: respuesta de error', [
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return SendResult::fail($error);
        }

        $messageId = $response->json('messages.0.id');

        if (! $messageId) {
            return SendResult::fail('Meta no devolvió un id de mensaje.');
        }

        return SendResult::ok($messageId);
    }

    /**
     * Construye el cuerpo JSON según el tipo de mensaje.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(OutboundMessage $message): array
    {
        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $message->to,
        ];

        if ($message->type === 'template') {
            return array_merge($base, [
                'type' => 'template',
                'template' => [
                    'name' => $message->templateName,
                    'language' => ['code' => $message->templateLanguage],
                    'components' => $this->templateComponents($message->templateParams),
                ],
            ]);
        }

        return array_merge($base, [
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => (string) $message->text,
            ],
        ]);
    }

    /**
     * Convierte una lista simple de parámetros en el formato de componentes
     * que espera Meta para el cuerpo de una plantilla.
     *
     * @param  array<int, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    private function templateComponents(array $params): array
    {
        if (empty($params)) {
            return [];
        }

        return [[
            'type' => 'body',
            'parameters' => array_map(
                fn ($value) => ['type' => 'text', 'text' => (string) $value],
                $params,
            ),
        ]];
    }
}
