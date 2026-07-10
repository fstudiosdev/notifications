<?php

namespace App\Messaging;

/**
 * Resultado normalizado del envío, sin importar el proveedor.
 */
class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function ok(string $providerMessageId): self
    {
        return new self(success: true, providerMessageId: $providerMessageId);
    }

    public static function fail(string $error): self
    {
        return new self(success: false, error: $error);
    }
}
