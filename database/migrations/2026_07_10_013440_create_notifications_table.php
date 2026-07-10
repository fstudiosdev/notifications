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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('channel')->default('whatsapp');   // whatsapp, sms, email...
            $table->string('direction')->default('outbound');  // outbound | inbound (chatbot)

            $table->string('to_address');                      // destinatario (+52...)
            $table->string('from_address')->nullable();        // remitente del tenant

            $table->string('type')->default('text');           // text | template
            $table->json('payload')->nullable();               // contenido / params de plantilla

            $table->string('provider')->default('meta');
            $table->string('provider_message_id')->nullable()->index(); // wamid de Meta

            // queued -> sent -> delivered -> read | failed
            $table->string('status')->default('queued')->index();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'direction', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
