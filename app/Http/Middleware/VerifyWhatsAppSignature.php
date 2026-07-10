<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida que el webhook venga realmente de Meta comprobando la firma
 * X-Hub-Signature-256 = HMAC-SHA256(cuerpo_crudo, app_secret).
 *
 * Si META_APP_SECRET no está configurado, se omite la validación (útil en
 * pruebas locales). En producción SIEMPRE debe estar configurado.
 */
class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.meta.app_secret');

        if (empty($secret)) {
            Log::warning('WhatsApp webhook: firma NO verificada (META_APP_SECRET vacío).');

            return $next($request);
        }

        $header = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! is_string($header) || ! hash_equals($expected, $header)) {
            Log::warning('WhatsApp webhook: firma inválida, petición rechazada.');

            return response()->json(['message' => 'Firma inválida.'], 403);
        }

        return $next($request);
    }
}
