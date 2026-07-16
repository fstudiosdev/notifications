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
}
