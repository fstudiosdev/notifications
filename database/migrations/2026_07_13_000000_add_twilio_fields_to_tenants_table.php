<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciales de Twilio, alternativas a las de Meta.
     * Cada instancia usa uno u otro proveedor según la columna `provider`.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Account SID + Auth Token de la consola de Twilio.
            $table->string('twilio_account_sid')->nullable()->after('wa_access_token');
            $table->text('twilio_auth_token')->nullable()->after('twilio_account_sid'); // encriptado
            // Número emisor habilitado para WhatsApp en Twilio (E.164, sin "whatsapp:").
            $table->string('twilio_from')->nullable()->after('twilio_auth_token');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['twilio_account_sid', 'twilio_auth_token', 'twilio_from']);
        });
    }
};
