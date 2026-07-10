<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {email : Correo del administrador}
        {--name= : Nombre (por defecto, la parte antes del @)}
        {--password= : Contraseña (si se omite, se genera una)}';

    protected $description = 'Crea (o actualiza) un usuario administrador del panel.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?: strstr($email, '@', true);
        $password = $this->option('password') ?: \Illuminate\Support\Str::random(12);

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        $this->info("Administrador listo: {$user->email}");

        if (! $this->option('password')) {
            $this->newLine();
            $this->warn('Contraseña generada (guárdala):');
            $this->line($password);
        }

        return self::SUCCESS;
    }
}
