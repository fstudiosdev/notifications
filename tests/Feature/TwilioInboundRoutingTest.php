<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Tenant;
use App\Support\TwilioInbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Enrutamiento de respuestas entrantes a la instancia correcta, incluyendo el
 * caso del número COMUNITARIO (compartido por varias clínicas).
 */
class TwilioInboundRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, string $from, string $tipo = 'comunitario'): Tenant
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

    private function outbound(Tenant $t, string $to, string $sid, string $reference): Notification
    {
        return $t->notifications()->create([
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'to_address' => $to,
            'type' => 'template',
            'reference' => $reference,
            'provider' => 'twilio',
            'provider_message_id' => $sid,
            'status' => 'sent',
        ]);
    }

    private function request(array $params): Request
    {
        return Request::create('/api/webhooks/twilio/inbound', 'POST', $params);
    }

    public function test_numero_compartido_resuelve_por_contexto_de_respuesta(): void
    {
        $numero = '+50322220000';
        $a = $this->tenant('clinica-a', $numero);
        $b = $this->tenant('clinica-b', $numero);

        // Cada clínica envió un recordatorio al MISMO número compartido.
        $this->outbound($a, '50378840001', 'SMaaa', 'cita:11');
        $this->outbound($b, '50378840002', 'SMbbb', 'cita:22');

        // El paciente de A toca "Confirmar": Twilio manda el SID del saliente de A.
        [$tenant, $reference] = TwilioInbound::resolve($this->request([
            'To' => 'whatsapp:'.$numero,
            'From' => 'whatsapp:+50378840001',
            'ButtonPayload' => 'confirmar',
            'OriginalRepliedMessageSid' => 'SMaaa',
            'MessageSid' => 'SMreply1',
        ]));

        $this->assertNotNull($tenant);
        $this->assertSame($a->id, $tenant->id);
        $this->assertSame('cita:11', $reference);
    }

    public function test_numero_dedicado_resuelve_por_numero(): void
    {
        $c = $this->tenant('clinica-c', '+50322221111', 'dedicado');
        $this->outbound($c, '50378843333', 'SMccc', 'cita:33');

        [$tenant, $reference] = TwilioInbound::resolve($this->request([
            'To' => 'whatsapp:+50322221111',
            'From' => 'whatsapp:+50378843333',
            'ButtonPayload' => 'confirmar',
            'MessageSid' => 'SMreply2',
        ]));

        $this->assertSame($c->id, $tenant->id);
        $this->assertSame('cita:33', $reference);
    }

    public function test_numero_compartido_sin_contexto_usa_ultimo_saliente_al_paciente(): void
    {
        $numero = '+50322224444';
        $a = $this->tenant('clinica-d', $numero);
        $b = $this->tenant('clinica-e', $numero);

        // Ambas escribieron al mismo paciente; el último saliente es de B.
        $this->outbound($a, '50378845555', 'SMddd', 'cita:44');
        $this->outbound($b, '50378845555', 'SMeee', 'cita:55');

        // Texto libre (sin OriginalRepliedMessageSid).
        [$tenant, $reference] = TwilioInbound::resolve($this->request([
            'To' => 'whatsapp:'.$numero,
            'From' => 'whatsapp:+50378845555',
            'Body' => 'Gracias',
            'MessageSid' => 'SMreply3',
        ]));

        $this->assertSame($b->id, $tenant->id);
        $this->assertSame('cita:55', $reference);
    }

    public function test_numero_desconocido_no_resuelve(): void
    {
        [$tenant, $reference] = TwilioInbound::resolve($this->request([
            'To' => 'whatsapp:+50399999999',
            'From' => 'whatsapp:+50378840001',
            'MessageSid' => 'SMreply4',
        ]));

        $this->assertNull($tenant);
        $this->assertNull($reference);
    }
}
