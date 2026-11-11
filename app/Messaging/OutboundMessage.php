<?php

namespace App\Messaging;

/**
 * Mensaje saliente, independiente del proveedor.
 *
 * - text:     mensaje de texto libre (solo válido dentro de la ventana de 24h
 *             de una conversación ya iniciada por el usuario).
 * - template: plantilla pre-aprobada en Meta (obligatoria para iniciar
 *             conversación con el cliente).
 * - audio:    nota de voz desde una URL pública https (.ogg/Opus para que
 *             WhatsApp la muestre como nota de voz). Como el texto libre, solo
 *             dentro de la ventana de 24 h. WhatsApp no admite texto junto al audio.
 *
 * `reference` es la referencia de negocio del sistema cliente (ej. "cita:4581").
 * La guardamos al enviar para poder devolverla cuando el cliente responda, y
 * que el sistema cliente sepa a qué cita corresponde la respuesta.
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
        public readonly ?string $reference = null,
        public readonly ?string $mediaUrl = null,
    ) {}

    public static function audio(string $to, string $mediaUrl, ?string $reference = null): self
    {
        return new self(to: $to, type: 'audio', reference: $reference, mediaUrl: $mediaUrl);
    }

    public static function text(string $to, string $body, ?string $reference = null): self
    {
        return new self(to: $to, type: 'text', text: $body, reference: $reference);
    }

    /**
     * @param  array<int, mixed>  $params
     */
    public static function template(
        string $to,
        string $name,
        string $language = 'es',
        array $params = [],
        ?string $reference = null,
    ): self {
        return new self(
            to: $to,
            type: 'template',
            templateName: $name,
            templateLanguage: $language,
            templateParams: $params,
            reference: $reference,
        );
    }
}
