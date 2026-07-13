<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea el usuario administrador por defecto del panel (/admin).
 *
 * Es idempotente: si el correo ya existe, actualiza su nombre y contraseña
 * en lugar de duplicarlo. Pensado para entornos de desarrollo.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin1234'),
            ],
        );

        $this->command?->info('Admin del panel listo: admin@admin.com / admin1234');
    }
}
