<?php

namespace App\Messaging;

/**
 * Mensaje saliente, independiente del proveedor.
 *
 * - text:     mensaje de texto libre (solo válido dentro de la ventana de 24h
 *             de una conversación ya iniciada por el usuario).
 * - template: plantilla pre-aprobada en Meta (obligatoria para iniciar
 *             conversación con el cliente).
 */
class OutboundMessage
{
    /**
     * @param  array<int, mixed>  $templateParams  Parámetros del cuerpo de la plantilla.
     */
    public function __construct(
        public readonly string $to,
        public readonly string $type = 'text',
        public readonly ?string $text = null,
        public readonly ?string $templateName = null,
        public readonly string $templateLanguage = 'es',
        public readonly array $templateParams = [],
    ) {
    }

    public static function text(string $to, string $body): self
    {
        return new self(to: $to, type: 'text', text: $body);
    }

    /**
     * @param  array<int, mixed>  $params
     */
    public static function template(string $to, string $name, string $language = 'es', array $params = []): self
    {
        return new self(
            to: $to,
            type: 'template',
            templateName: $name,
            templateLanguage: $language,
            templateParams: $params,
        );
    }
}
