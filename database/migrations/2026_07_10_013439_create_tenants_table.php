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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            // Identidad del cliente (taller / clínica)
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->nullable();      // p.ej. taller, clinica
            $table->boolean('active')->default(true);

            // Autenticación: los sistemas cliente mandan este token.
            // Se guarda hasheado (sha256); nunca en texto plano.
            $table->string('api_token_hash', 64)->unique();

            // Credenciales del proveedor (Meta WhatsApp Cloud API).
            // El access token se encripta a nivel de aplicación.
            $table->string('provider')->default('meta');
            $table->string('wa_phone_number_id')->nullable();      // phone_number_id de Meta
            $table->string('wa_business_account_id')->nullable();  // WABA id
            $table->string('wa_phone_number')->nullable();         // display, +52...
            $table->text('wa_access_token')->nullable();           // encriptado

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
