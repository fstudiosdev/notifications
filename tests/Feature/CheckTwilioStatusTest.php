<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckTwilioStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_trae_el_estado_real_y_corrige_el_de_la_bd(): void
    {
        // Twilio dice la verdad: el mensaje NO se entregó (ventana de 24h).
        Http::fake([
            'api.twilio.com/*' => Http::response([
                'sid' => 'SM123',
                'to' => 'whatsapp:+50363071123',
                'status' => 'undelivered',
                'error_code' => 63016,
                'error_message' => 'Failed to send freeform message because you are outside the allowed window.',
            ]),
        ]);

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

        $tenant->notifications()->create([
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'to_address' => '50363071123',
            'type' => 'text',
            'provider' => 'twilio',
            'provider_message_id' => 'SM123',
            'status' => 'sent',   // lo que creíamos
        ]);

        $this->artisan('twilio:check')
            ->expectsOutputToContain('Clinica San Jose')
            ->assertSuccessful();

        // El panel deja de mentir: pasa de "sent" al estado real.
        $this->assertDatabaseHas('notifications', [
            'provider_message_id' => 'SM123',
            'status' => 'undelivered',
        ]);
    }
}
