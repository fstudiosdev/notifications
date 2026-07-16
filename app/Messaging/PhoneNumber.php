<?php

namespace App\Messaging;

/**
 * Normaliza teléfonos a solo dígitos.
 *
 * Hace falta porque cada lado usa un formato distinto: el sistema cliente
 * manda "+503 7777-8888", Twilio entrega "whatsapp:+50377778888" y Meta
 * "50377778888". Sin un formato común no podríamos casar una respuesta con
 * el envío que la originó.
 */
class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits === '' ? null : $digits;
    }
}
