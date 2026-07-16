<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Flujo: Twilio recibe la respuesta del paciente -> nos la entrega ->
 * la reenviamos al sistema cliente (Clinea) -> ese sistema decide.
 */
class TwilioInboundTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_TOKEN = 'token_secreto_123';

    private const WEBHOOK_URL = 'http://localhost/api/webhooks/twilio/inbound';

    protected function setUp(): void
    {
        parent::setUp();

        // La firma se calcula sobre esta URL exacta.
        config(['services.twilio.webhook_url' => self::WEBHOOK_URL]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function tenant(array $overrides = []): Tenant
    {
        $tenant = new Tenant(array_merge([
            'name' => 'Clinica San Jose',
            'slug' => 'clinica-san-jose',
            'active' => true,
            'provider' => 'twilio',
            'twilio_account_sid' => 'AC_test',
            // Sin auth token, la validación de firma se omite: así los tests
            // del flujo no tienen que firmar. Los de firma sí lo configuran.
            'twilio_from' => '+50322334455',
            'callback_url' => 'https://clinea.test/api/webhooks/mensajeria',
        ], $overrides));

        $tenant->generateCredentials();
        $tenant->save();

        return $tenant;
    }

    /**
     * El recordatorio que originó la conversación (trae la referencia).
     */
    private function previousReminder(Tenant $tenant, string $phoneDigits, string $reference): void
    {
        $tenant->notifications()->create([
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'to_address' => $phoneDigits,
            'from_address' => '50322334455',
            'type' => 'template',
            'payload' => ['template' => 'HXabc123'],
            'reference' => $reference,
            'provider' => 'twilio',
            'provider_message_id' => 'SM_PREV',
            'status' => 'delivered',
        ]);
    }

    /**
     * Firma como la calcula Twilio: HMAC-SHA1 sobre la URL + los parámetros
     * ordenados por clave, en base64.
     *
     * @param  array<string, string>  $params
     */
    private function sign(array $params): string
    {
        ksort($params);

        $data = self::WEBHOOK_URL;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, self::AUTH_TOKEN, true));
    }

    public function test_respuesta_de_boton_se_reenvia_a_clinea_con_su_referencia(): void
    {
        Http::fake(['clinea.test/*' => Http::response(['ok' => true])]);

        $tenant = $this->tenant();
        $this->previousReminder($tenant, '50377778888', 'cita:4581');

        // Twilio postea form-urlencoded.
        $this->post('/api/webhooks/twilio/inbound', [
            'From' => 'whatsapp:+50377778888',
            'To' => 'whatsapp:+50322334455',
            'MessageSid' => 'SM999',
            'ButtonPayload' => 'confirmar',
            'Body' => 'Confirmar',   // texto visible: NO debe usarse para decidir
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'direction' => 'inbound',
            'from_address' => '50377778888',
            'button_payload' => 'confirmar',
            'reference' => 'cita:4581',
            'provider_message_id' => 'SM999',
            'status' => 'forwarded',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://clinea.test/api/webhooks/mensajeria'
                && $request['referencia'] === 'cita:4581'
                && $request['telefono'] === '50377778888'
                && $request['tipo'] === 'boton'
                && $request['payload'] === 'confirmar'
                && $request['texto'] === null;
        });
    }

    public function test_texto_libre_se_reenvia_como_tipo_texto(): void
    {
        Http::fake(['clinea.test/*' => Http::response(['ok' => true])]);

        $tenant = $this->tenant();
        $this->previousReminder($tenant, '50377778888', 'cita:7777');

        $this->post('/api/webhooks/twilio/inbound', [
            'From' => 'whatsapp:+50377778888',
            'To' => 'whatsapp:+50322334455',
            'MessageSid' => 'SM777',
            'Body' => 'Puedo llegar 20 minutos tarde?',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['tipo'] === 'texto'
            && $request['payload'] === null
            && $request['texto'] === 'Puedo llegar 20 minutos tarde?'
            && $request['referencia'] === 'cita:7777');
    }

    public function test_sin_envio_previo_la_referencia_va_nula_pero_igual_se_reenvia(): void
    {
        Http::fake(['clinea.test/*' => Http::response(['ok' => true])]);

        $this->tenant(); // sin recordatorio previo

        $this->post('/api/webhooks/twilio/inbound', [
            'From' => 'whatsapp:+50377778888',
            'To' => 'whatsapp:+50322334455',
            'MessageSid' => 'SM111',
            'Body' => 'Hola',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['referencia'] === null
            && $request['texto'] === 'Hola');
    }

    public function test_sin_callback_url_se_guarda_pero_no_se_reenvia(): void
    {
        Http::fake();

        $this->tenant(['callback_url' => null]);

        $this->post('/api/webhooks/twilio/inbound', [
            'From' => 'whatsapp:+50377778888',
            'To' => 'whatsapp:+50322334455',
            'MessageSid' => 'SM222',
            'Body' => 'Hola',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'provider_message_id' => 'SM222',
            'status' => 'received',
        ]);

        Http::assertNothingSent();
    }

    public function test_numero_desconocido_se_ignora_sin_error(): void
    {
        Http::fake();

        $this->tenant();

        $this->post('/api/webhooks/twilio/inbound', [
            'From' => 'whatsapp:+50377778888',
            'To' => 'whatsapp:+50399999999',   // no es de ninguna instancia
            'MessageSid' => 'SM333',
            'Body' => 'Hola',
        ])->assertOk();

        $this->assertDatabaseMissing('notifications', ['provider_message_id' => 'SM333']);
        Http::assertNothingSent();
    }

    public function test_firma_valida_es_aceptada(): void
    {
        Http::fake(['clinea.test/*' => Http::response(['ok' => true])]);

        $this->tenant(['twilio_auth_token' => self::AUTH_TOKEN]);

        $params = [
            'From' => 'whatsapp:+50377778888',
            'To' => 'whatsapp:+50322334455',
            'MessageSid' => 'SM555',
            'Body' => 'Hola',
        ];

        $this->withHeader('X-Twilio-Signature', $this->sign($params))
            ->post('/api/webhooks/twilio/inbound', $params)
            ->assertOk();

        $this->assertDatabaseHas('notifications', ['provider_message_id' => 'SM555']);
    }

    public function test_firma_invalida_es_rechazada(): void
    {
        Http::fake();

        $this->tenant(['twilio_auth_token' => self::AUTH_TOKEN]);

        $this->withHeader('X-Twilio-Signature', 'firma-falsa')
            ->post('/api/webhooks/twilio/inbound', [
                'From' => 'whatsapp:+50377778888',
                'To' => 'whatsapp:+50322334455',
                'MessageSid' => 'SM666',
                'Body' => 'Intento de suplantación',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('notifications', ['provider_message_id' => 'SM666']);
        Http::assertNothingSent();
    }

    public function test_estado_del_mensaje_se_actualiza(): void
    {
        $tenant = $this->tenant();
        $this->previousReminder($tenant, '50377778888', 'cita:1');

        $this->post('/api/webhooks/twilio/status', [
            'MessageSid' => 'SM_PREV',
            'MessageStatus' => 'read',
        ])->assertOk();

        $this->assertSame('read', Notification::where('provider_message_id', 'SM_PREV')->value('status'));
    }
}
