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
 * Implementación contra la API de Twilio (WhatsApp).
 *
 * Usa las credenciales por instancia (Account SID, Auth Token y número
 * emisor). Docs: https://www.twilio.com/docs/whatsapp/api
 *
 * Nota sobre plantillas: en Twilio una "plantilla" es un Content Template
 * identificado por su Content SID (HX...). Por eso, cuando el mensaje es de
 * tipo "template", el campo `template` se interpreta como ese Content SID y
 * los `params` se envían como ContentVariables ({"1":"...","2":"..."}).
 */
class TwilioProvider implements NotificationProvider
{
    public function name(): string
    {
        return 'twilio';
    }

    public function send(Tenant $tenant, OutboundMessage $message): SendResult
    {
        if (! $tenant->twilio_account_sid || ! $tenant->twilio_auth_token || ! $tenant->twilio_from) {
            return SendResult::fail('El tenant no tiene credenciales de Twilio configuradas.');
        }

        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            $tenant->twilio_account_sid,
        );

        try {
            $response = Http::asForm()
                ->withBasicAuth($tenant->twilio_account_sid, $tenant->twilio_auth_token)
                ->post($url, $this->buildPayload($tenant, $message));
        } catch (Throwable $e) {
            Log::error('Twilio: fallo de red al enviar', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return SendResult::fail('Error de conexión con Twilio: '.$e->getMessage());
        }

        if ($response->failed()) {
            $error = $response->json('message', 'Error desconocido de Twilio.');

            Log::warning('Twilio: respuesta de error', [
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return SendResult::fail($error);
        }

        $messageId = $response->json('sid');

        if (! $messageId) {
            return SendResult::fail('Twilio no devolvió un SID de mensaje.');
        }

        return SendResult::ok($messageId);
    }

    /**
     * Construye el cuerpo (form-encoded) que espera Twilio.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Tenant $tenant, OutboundMessage $message): array
    {
        $base = [
            'From' => $this->whatsappAddress($tenant->twilio_from),
            'To' => $this->whatsappAddress($message->to),
        ];

        if ($message->type === 'template') {
            $payload = array_merge($base, [
                'ContentSid' => $message->templateName,
            ]);

            $variables = $this->contentVariables($message->templateParams);
            if ($variables !== null) {
                $payload['ContentVariables'] = $variables;
            }

            return $payload;
        }

        return array_merge($base, [
            'Body' => (string) $message->text,
        ]);
    }

    /**
     * Normaliza un número a la forma "whatsapp:+<E164>" que exige Twilio.
     */
    private function whatsappAddress(string $number): string
    {
        $n = preg_replace('/^whatsapp:/i', '', trim($number));

        if (! str_starts_with($n, '+')) {
            $n = '+'.ltrim($n, '+');
        }

        return 'whatsapp:'.$n;
    }

    /**
     * Convierte una lista de parámetros en el JSON ContentVariables de Twilio:
     * ["Miguel", "10:00"] -> {"1":"Miguel","2":"10:00"}.
     *
     * @param  array<int, mixed>  $params
     */
    private function contentVariables(array $params): ?string
    {
        if (empty($params)) {
            return null;
        }

        $variables = [];
        foreach (array_values($params) as $i => $value) {
            $variables[(string) ($i + 1)] = (string) $value;
        }

        return json_encode($variables);
    }
}
