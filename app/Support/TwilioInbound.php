<?php

namespace App\Support;

use App\Messaging\PhoneNumber;
use App\Models\Tenant;

/**
 * Utilidades compartidas entre el middleware de firma y el controlador
 * del webhook de Twilio: ambos necesitan resolver a qué instancia
 * pertenece un mensaje entrante.
 */
class TwilioInbound
{
    /**
     * La instancia se identifica por el número que RECIBIÓ el mensaje
     * (el campo "To" que manda Twilio, ej. "whatsapp:+50322334455").
     *
     * Se compara por dígitos porque twilio_from puede estar guardado con
     * "+", espacios o guiones.
     */
    public static function tenantFor(?string $to): ?Tenant
    {
        $digits = PhoneNumber::normalize($to);

        if ($digits === null) {
            return null;
        }

        return Tenant::query()
            ->where('provider', 'twilio')
            ->whereNotNull('twilio_from')
            ->get()
            ->first(fn (Tenant $t) => PhoneNumber::normalize($t->twilio_from) === $digits);
    }
}
