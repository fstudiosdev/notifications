<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teléfonos de doctores que hablan con el asistente IA de una instancia.
 *
 * En un número COMUNITARIO un mensaje que llega «en frío» (sin ser respuesta a
 * un recordatorio) no se puede atribuir a ninguna clínica. Con este registro
 * sí: la instancia avisa «este teléfono es mío» y sus mensajes se le enrutan.
 * La instancia (Clinea) es la que verifica y autoriza; aquí solo se enruta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20); // solo dígitos (PhoneNumber::normalize)
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_numbers');
    }
};
