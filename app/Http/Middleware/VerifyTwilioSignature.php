<?php

namespace App\Http\Middleware;

use App\Support\TwilioInbound;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

/**
 * Valida que el webhook venga realmente de Twilio y no de un impostor.
 *
 * El Auth Token es por instancia, así que primero resolvemos de qué
 * instancia es el mensaje (por el campo "To") y validamos con SU token.
 *
 * Si la instancia no tiene Auth Token configurado, se omite la validación
 * (útil en pruebas). En producción SIEMPRE debe estar configurado.
 */
class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TwilioInbound::tenantFor($request->input('To'));

        // Si no reconocemos el número, no hay token con qué validar.
        // El controlador responderá 200 e ignorará el mensaje.
        if (! $tenant || blank($tenant->twilio_auth_token)) {
            if ($tenant) {
                Log::warning('Twilio webhook: firma NO verificada (instancia sin Auth Token).', [
                    'tenant_id' => $tenant->id,
                ]);
            }

            return $next($request);
        }

        $signature = $request->header('X-Twilio-Signature', '');

        // La firma se calcula sobre la URL EXACTA registrada en Twilio.
        // Detrás de ngrok/proxy, la URL que ve Laravel puede diferir; por eso
        // se puede forzar con TWILIO_WEBHOOK_URL.
        $url = config('services.twilio.webhook_url') ?: $request->fullUrl();

        $validator = new RequestValidator($tenant->twilio_auth_token);

        if (! is_string($signature) || ! $validator->validate($signature, $url, $request->post())) {
            Log::warning('Twilio webhook: firma inválida, petición rechazada.', [
                'tenant_id' => $tenant->id,
                'url_usada' => $url,
            ]);

            return response('Firma inválida.', 403);
        }

        return $next($request);
    }
}
