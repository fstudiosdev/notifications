<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soporte para recibir respuestas y reenviarlas al sistema cliente.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Endpoint del sistema que consume el servicio (Clinea, el del
            // taller...) al que reenviamos los mensajes entrantes.
            $table->string('callback_url')->nullable()->after('twilio_from');
        });

        Schema::table('notifications', function (Blueprint $table) {
            // Referencia de negocio del sistema cliente (ej. "cita:4581").
            // Se guarda al enviar y se recupera al llegar la respuesta.
            $table->string('reference')->nullable()->after('payload');

            // Respuesta de botón normalizada (ej. "confirmar", "reagendar").
            $table->string('button_payload')->nullable()->after('reference');

            // Para buscar el último envío a un teléfono de una instancia.
            $table->index(['tenant_id', 'to_address', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('callback_url');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'to_address', 'direction']);
            $table->dropColumn(['reference', 'button_payload']);
        });
    }
};
