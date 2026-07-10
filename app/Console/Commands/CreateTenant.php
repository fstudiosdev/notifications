<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create
        {name : Nombre del cliente (p.ej. "Taller Gómez")}
        {--type= : Tipo (taller, clinica...)}
        {--slug= : Slug único (por defecto se deriva del nombre)}';

    protected $description = 'Crea un tenant y muestra su API token (solo se ve una vez).';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("Ya existe un tenant con el slug '{$slug}'.");

            return self::FAILURE;
        }

        $tenant = new Tenant([
            'name' => $name,
            'slug' => $slug,
            'type' => $this->option('type'),
            'active' => true,
            'provider' => 'meta',
        ]);

        $secret = $tenant->generateCredentials();
        $tenant->save();

        $this->info("Instancia '{$name}' creada (id {$tenant->id}, slug {$slug}).");
        $this->newLine();
        $this->warn('Credenciales (el secret NO se vuelve a mostrar):');
        $this->line('client_id:     '.$tenant->client_id);
        $this->line('client_secret: '.$secret);

        return self::SUCCESS;
    }
}
