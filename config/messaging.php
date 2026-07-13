<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proveedor de mensajería activo
    |--------------------------------------------------------------------------
    |
    | Define qué implementación de NotificationProvider se usa para enviar:
    |
    |   'meta' -> WhatsApp Cloud API de Meta (envío real).
    |   'log'  -> Simulación: no envía nada, solo escribe en el log y marca
    |             el mensaje como enviado. Útil para pruebas sin credenciales.
    |
    */

    'driver' => env('MESSAGING_DRIVER', 'meta'),

];
