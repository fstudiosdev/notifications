<?php

namespace Tests\Feature;

use App\Models\AssistantNumber;
use App\Models\Tenant;
use App\Support\TwilioInbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Teléfonos de doctores que hablan con el asistente IA de una instancia.
 * Sin este registro, en un número compartido el mensaje «en frío» del doctor
 * no se podía atribuir a ninguna clínica y se perdía.
 */
class AssistantNumbersTest extends TestCase
{
    use RefreshDatabase;

    private const COMUNITARIO = '+50375974011';

    private function tenant(string $slug, string $from = self::COMUNITARIO, string $tipo = 'comunitario'): Tenant
    {
        return Tenant::create([
            'name' => $slug,
            'slug' => $slug,
            'provider' => 'twilio',
            'twilio_from' => $from,
            'numero_tipo' => $tipo,
            'callback_url' => "https://{$slug}.example.com/callback",
        ]);
    }

    /** Habla con la API como esa instancia (token real, como en producción). */
    private function como(Tenant $tenant): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($tenant->createToken('prueba')->plainTextToken);
    }

    private function entrante(string $from, string $to = self::COMUNITARIO): Request
    {
        return Request::create('/api/webhooks/twilio/inbound', 'POST', [
            'To' => 'whatsapp:'.$to,
            'From' => 'whatsapp:'.$from,
            'Body' => '¿qué tengo hoy?',
            'MessageSid' => 'SM'.uniqid(),
        ]);
    }

    public function test_la_instancia_registra_un_telefono_en_digitos(): void
    {
        $this->como($this->tenant('medibell'))->postJson('/api/assistant/numbers', ['phone' => '+503 7845-1122'])
            ->assertCreated()
            ->assertJson(['phone' => '50378451122']);

        $this->assertDatabaseHas('assistant_numbers', ['phone' => '50378451122']);
    }

    public function test_sin_token_no_se_puede_registrar(): void
    {
        $this->postJson('/api/assistant/numbers', ['phone' => '+50378451122'])->assertUnauthorized();
    }

    public function test_un_mensaje_en_frio_en_el_numero_compartido_llega_a_la_instancia_del_doctor(): void
    {
        $this->tenant('demo');
        $medibell = $this->tenant('medibell');
        $this->como($medibell)->postJson('/api/assistant/numbers', ['phone' => '+50378451122'])->assertCreated();

        [$tenant, $referencia] = TwilioInbound::resolve($this->entrante('+50378451122'));

        $this->assertSame($medibell->id, $tenant?->id);
        $this->assertSame('asistente', $referencia);
    }

    public function test_sin_registro_el_mensaje_en_frio_sigue_sin_instancia(): void
    {
        $this->tenant('demo');
        $this->tenant('medibell');

        [$tenant] = TwilioInbound::resolve($this->entrante('+50378451122'));

        $this->assertNull($tenant);
    }

    public function test_en_el_numero_compartido_un_telefono_solo_puede_ser_de_una_instancia(): void
    {
        $demo = $this->tenant('demo');
        $medibell = $this->tenant('medibell');

        $this->como($medibell)->postJson('/api/assistant/numbers', ['phone' => '+50378451122'])->assertCreated();

        $this->como($demo)->postJson('/api/assistant/numbers', ['phone' => '50378451122'])->assertStatus(409);
    }

    public function test_con_numeros_distintos_el_mismo_doctor_puede_estar_en_dos_clinicas(): void
    {
        $a = $this->tenant('clinica-a', '+50322220000', 'dedicado');
        $b = $this->tenant('clinica-b', '+50322221111', 'dedicado');

        $this->como($a)->postJson('/api/assistant/numbers', ['phone' => '+50378451122'])->assertCreated();
        $this->como($b)->postJson('/api/assistant/numbers', ['phone' => '+50378451122'])->assertCreated();

        [$tenant] = TwilioInbound::resolve($this->entrante('+50378451122', '+50322221111'));
        $this->assertSame($b->id, $tenant?->id);
    }

    public function test_al_quitarlo_deja_de_enrutarse(): void
    {
        $this->tenant('demo');
        $medibell = $this->tenant('medibell');
        $this->como($medibell)->postJson('/api/assistant/numbers', ['phone' => '+50378451122']);

        $this->deleteJson('/api/assistant/numbers', ['phone' => '+50378451122'])->assertOk();

        $this->assertSame(0, AssistantNumber::count());
        [$tenant] = TwilioInbound::resolve($this->entrante('+50378451122'));
        $this->assertNull($tenant);
    }

    public function test_una_instancia_no_puede_quitar_el_numero_de_otra(): void
    {
        $this->tenant('demo');
        $medibell = $this->tenant('medibell');
        $otra = $this->tenant('otra');
        $this->como($medibell)->postJson('/api/assistant/numbers', ['phone' => '+50378451122']);

        $this->como($otra)->deleteJson('/api/assistant/numbers', ['phone' => '+50378451122'])->assertOk();

        $this->assertSame(1, AssistantNumber::count());
    }
}
