<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Credenciales que el sistema cliente usa para autenticarse:
            //   client_id     -> identificador público (como el "usuario")
            //   client_secret -> se guarda hasheado (como la "contraseña")
            $table->string('client_id')->nullable()->unique()->after('slug');
            $table->string('client_secret_hash')->nullable()->after('client_id');

            // El token opaco anterior queda opcional (migramos a Sanctum).
            $table->string('api_token_hash', 64)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['client_id']);
            $table->dropColumn(['client_id', 'client_secret_hash']);
        });
    }
};
