<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Da de alta una instancia de Clinea en el número comunitario.
 *
 * Copia las credenciales de Twilio de una instancia comunitaria que ya
 * funciona (--plantilla, por defecto la primera) y apunta los mensajes
 * entrantes a la instancia nueva (--callback). Lo usa `php artisan
 * tere:habilitar --gateway-dir=…` desde Clinea; también se puede correr a mano.
 *
 * No toca ninguna instancia existente: si el slug ya existe, falla.
 */
class CreateClineaTenant extends Command
{
    protected $signature = 'tenant:clinea
        {slug : Slug único (p. ej. "avatrauma")}
        {--nombre= : Nombre visible (por defecto el slug)}
        {--callback= : URL del webhook de la instancia, con su token}
        {--plantilla= : Slug de la instancia comunitaria de la que se copian las credenciales de Twilio}
        {--json : Salida en JSON (para que la lea Clinea)}';

    protected $description = 'Crea la instancia de una clínica de Clinea en el número comunitario y muestra sus credenciales.';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');

        if (Tenant::where('slug', $slug)->exists()) {
            return $this->fallar("Ya existe una instancia con el slug '{$slug}'. No se tocó.");
        }
        if (! filter_var((string) $this->option('callback'), FILTER_VALIDATE_URL)) {
            return $this->fallar('Falta --callback con la URL del webhook de la instancia.');
        }

        $plantilla = Tenant::query()
            ->where('numero_tipo', Tenant::NUMERO_COMUNITARIO)
            ->whereNotNull('twilio_from')
            ->when($this->option('plantilla'), fn ($q, $p) => $q->where('slug', $p))
            ->orderBy('id')
            ->first();
        if (! $plantilla) {
            return $this->fallar('No hay una instancia comunitaria de la que copiar las credenciales de Twilio.');
        }

        $tenant = new Tenant([
            'name' => $this->option('nombre') ?: $slug,
            'slug' => $slug,
            'type' => 'clinica',
            'active' => true,
            'provider' => $plantilla->provider,
            'twilio_account_sid' => $plantilla->twilio_account_sid,
            'twilio_auth_token' => $plantilla->twilio_auth_token,
            'twilio_from' => $plantilla->twilio_from,
            'numero_tipo' => Tenant::NUMERO_COMUNITARIO,
            'callback_url' => $this->option('callback'),
        ]);
        $secreto = $tenant->generateCredentials();
        $tenant->save();

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => true, 'id' => $tenant->id, 'client_id' => $tenant->client_id, 'client_secret' => $secreto]));
        } else {
            $this->info("Instancia '{$slug}' creada (id {$tenant->id}) en el número comunitario, copiando Twilio de '{$plantilla->slug}'.");
            $this->warn('Credenciales (el secret NO se vuelve a mostrar):');
            $this->line('client_id:     '.$tenant->client_id);
            $this->line('client_secret: '.$secreto);
        }

        return self::SUCCESS;
    }

    private function fallar(string $error): int
    {
        $this->option('json') ? $this->line(json_encode(['ok' => false, 'error' => $error])) : $this->error($error);

        return self::FAILURE;
    }
}
