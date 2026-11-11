<?php

namespace Tests\Feature;

use App\Messaging\NotificationDispatcher;
use App\Messaging\OutboundMessage;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioOutboundTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        $tenant = new Tenant([
            'name' => 'Clinica San Jose',
            'slug' => 'clinica-san-jose',
            'active' => true,
            'provider' => 'twilio',
            'twilio_account_sid' => 'AC_test',
            'twilio_auth_token' => 'token_test',
            'twilio_from' => '+50322334455',
        ]);

        $tenant->generateCredentials();
        $tenant->save();

        return $tenant;
    }

    /**
     * Aunque guardemos el teléfono normalizado (sin "+"), a Twilio debe
     * llegar SIEMPRE en formato "whatsapp:+<E164>".
     */
    public function test_el_numero_llega_a_twilio_con_el_mas_aunque_se_guarde_sin_el(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'])]);

        $tenant = $this->tenant();

        app(NotificationDispatcher::class)->sendNow(
            $tenant,
            OutboundMessage::text('+503 6307-1123', 'Mensaje de prueba'),
        );

        // En BD se guarda normalizado.
        $this->assertDatabaseHas('notifications', [
            'to_address' => '50363071123',
            'status' => 'sent',
        ]);

        // Pero a Twilio va con el "+".
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['To'] === 'whatsapp:+50363071123'
                && $body['From'] === 'whatsapp:+50322334455'
                && $body['Body'] === 'Mensaje de prueba';
        });
    }

    public function test_la_nota_de_voz_va_a_twilio_como_media_url_sin_texto(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM124'])]);

        app(NotificationDispatcher::class)->sendNow(
            $this->tenant(),
            OutboundMessage::audio('+50363071123', 'https://agente.fstudios.dev/voz/abc.ogg'),
        );

        $this->assertDatabaseHas('notifications', ['type' => 'audio', 'status' => 'sent']);
        Http::assertSent(fn ($request) => $request->data()['MediaUrl'] === 'https://agente.fstudios.dev/voz/abc.ogg'
            && ! array_key_exists('Body', $request->data()));
    }

    public function test_la_api_acepta_audio_solo_con_url_https(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM125'])]);
        $tenant = $this->tenant();
        $token = $tenant->createToken('x')->plainTextToken;

        $this->withToken($token)->postJson('/api/notification/message', ['to' => '50363071123', 'type' => 'audio'])
            ->assertStatus(422);
        $this->withToken($token)->postJson('/api/notification/message', ['to' => '50363071123', 'type' => 'audio', 'media_url' => 'http://inseguro.test/a.ogg'])
            ->assertStatus(422);
        $this->withToken($token)->postJson('/api/notification/message', ['to' => '50363071123', 'type' => 'audio', 'media_url' => 'https://agente.fstudios.dev/voz/abc.ogg'])
            ->assertStatus(202);
    }
}
