<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de número de la instancia:
 *  - "dedicado":    la clínica tiene su propio número (se resuelve por el número).
 *  - "comunitario": varias clínicas comparten UN número y las MISMAS credenciales
 *    de Twilio; las respuestas se enrutan por el contexto de la respuesta
 *    (OriginalRepliedMessageSid), no por el número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('numero_tipo')->default('dedicado')->after('twilio_from'); // dedicado | comunitario
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('numero_tipo');
        });
    }
};
