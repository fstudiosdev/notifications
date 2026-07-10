<?php

namespace App\Messaging\Contracts;

use App\Messaging\OutboundMessage;
use App\Messaging\SendResult;
use App\Models\Tenant;

/**
 * Contrato de todo proveedor de mensajería (Meta, Twilio, SMS, email...).
 *
 * La app siempre habla con esta interfaz; cambiar de proveedor es cambiar
 * la implementación, no la lógica de negocio.
 */
interface NotificationProvider
{
    /**
     * Envía un mensaje usando las credenciales del tenant indicado.
     */
    public function send(Tenant $tenant, OutboundMessage $message): SendResult;

    /**
     * Identificador del proveedor (p.ej. "meta").
     */
    public function name(): string;
}
