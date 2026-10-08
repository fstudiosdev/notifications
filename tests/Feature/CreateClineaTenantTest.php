<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateClineaTenantTest extends TestCase
{
    use RefreshDatabase;

    private function comunitaria(): Tenant
    {
        $t = new Tenant([
            'name' => 'Medibell', 'slug' => 'clinica-medibell', 'type' => 'clinica', 'active' => true,
            'provider' => 'twilio', 'twilio_account_sid' => 'AC123', 'twilio_auth_token' => 'secreto-twilio',
            'twilio_from' => 'whatsapp:+50375974011', 'numero_tipo' => Tenant::NUMERO_COMUNITARIO,
            'callback_url' => 'https://medibell.clinea.app/api/webhooks/mensajeria?token=abc',
        ]);
        $t->generateCredentials();
        $t->save();

        return $t;
    }

    public function test_crea_la_instancia_copiando_twilio_sin_tocar_la_existente(): void
    {
        $existente = $this->comunitaria();
        $antes = $existente->fresh()->getAttributes();

        $this->artisan('tenant:clinea', ['slug' => 'nueva', '--callback' => 'https://nueva.clinea.app/api/webhooks/mensajeria?token=xyz', '--json' => true])
            ->assertSuccessful();

        $nueva = Tenant::where('slug', 'nueva')->sole();
        $this->assertSame(Tenant::NUMERO_COMUNITARIO, $nueva->numero_tipo);
        $this->assertSame('AC123', $nueva->twilio_account_sid);
        $this->assertSame('secreto-twilio', $nueva->twilio_auth_token);
        $this->assertSame('whatsapp:+50375974011', $nueva->twilio_from);
        $this->assertSame('https://nueva.clinea.app/api/webhooks/mensajeria?token=xyz', $nueva->callback_url);
        $this->assertNotSame($existente->client_id, $nueva->client_id);
        $this->assertSame($antes, $existente->fresh()->getAttributes());
    }

    public function test_si_el_slug_ya_existe_no_hace_nada(): void
    {
        $existente = $this->comunitaria();

        $this->artisan('tenant:clinea', ['slug' => 'clinica-medibell', '--callback' => 'https://x.clinea.app/api/webhooks/mensajeria?token=1'])
            ->assertFailed();

        $this->assertSame(1, Tenant::count());
        $this->assertSame($existente->callback_url, $existente->fresh()->callback_url);
    }
}
