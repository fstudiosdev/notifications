<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InboundMediaTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): Tenant
    {
        $t = new Tenant([
            'name' => $slug, 'slug' => $slug, 'active' => true, 'provider' => 'twilio',
            'twilio_account_sid' => 'AC_'.$slug, 'twilio_auth_token' => 'tok_'.$slug, 'twilio_from' => '+50322334455',
        ]);
        $t->generateCredentials();
        $t->save();

        return $t;
    }

    private function entrante(Tenant $t): Notification
    {
        return $t->notifications()->create([
            'channel' => 'whatsapp', 'direction' => 'inbound', 'to_address' => '50322334455', 'from_address' => '50377778888',
            'type' => 'text', 'provider' => 'twilio', 'provider_message_id' => 'SM'.uniqid(), 'status' => 'received',
            'payload' => ['NumMedia' => '1', 'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Messages/MM/Media/ME1', 'MediaContentType0' => 'audio/ogg'],
        ]);
    }

    public function test_entrega_el_archivo_descargado_de_twilio_con_las_credenciales_de_la_instancia(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response('OGGDATA', 200, ['Content-Type' => 'audio/ogg'])]);
        $t = $this->tenant('agentes');
        $n = $this->entrante($t);

        $r = $this->withToken($t->createToken('x')->plainTextToken)->get("/api/inbound/{$n->id}/media/0");

        $r->assertOk();
        $this->assertSame('OGGDATA', $r->getContent());
        $this->assertStringStartsWith('audio/ogg', $r->headers->get('Content-Type'));
        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Basic '.base64_encode('AC_agentes:tok_agentes')));
    }

    public function test_otra_instancia_no_puede_bajar_el_archivo(): void
    {
        Http::fake();
        $n = $this->entrante($this->tenant('agentes'));
        $otra = $this->tenant('clinea');

        $this->withToken($otra->createToken('x')->plainTextToken)->get("/api/inbound/{$n->id}/media/0")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_sin_token_no_se_entrega(): void
    {
        $n = $this->entrante($this->tenant('agentes'));

        $this->getJson("/api/inbound/{$n->id}/media/0")->assertUnauthorized();
    }

    public function test_un_indice_sin_archivo_da_404(): void
    {
        Http::fake();
        $t = $this->tenant('agentes');
        $n = $this->entrante($t);

        $this->withToken($t->createToken('x')->plainTextToken)->get("/api/inbound/{$n->id}/media/3")->assertNotFound();
    }
}
