<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardInboundMessageJob;
use App\Messaging\PhoneNumber;
use App\Models\Notification;
use App\Models\Tenant;
use App\Support\TwilioInbound;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de Twilio (canal WhatsApp).
 *
 * Flujo: Twilio recibe el mensaje del paciente -> nos lo entrega aquí ->
 * lo reenviamos al sistema cliente (Clinea) -> ese sistema decide si responde.
 *
 * Respondemos 200 rápido (TwiML vacío) y el reenvío ocurre en cola: si
 * devolviéramos error, Twilio reintentaría.
 *
 * Docs: https://www.twilio.com/docs/messaging/guides/webhook-request
 */
class TwilioWebhookController extends Controller
{
    /**
     * Mensajes entrantes (respuestas del paciente).
     */
    public function inbound(Request $request): Response
    {
        // "To" es NUESTRO número (el de la instancia); "From" es el paciente.
        $tenant = TwilioInbound::tenantFor($request->input('To'));

        if (! $tenant) {
            Log::warning('Twilio webhook: mensaje entrante sin instancia', [
                'to' => $request->input('To'),
            ]);

            return $this->ok();
        }

        $from = PhoneNumber::normalize($request->input('From'));

        // La decisión SIEMPRE se toma por el payload del botón, nunca por el
        // texto visible. Si no hay botón, es texto libre.
        $buttonPayload = $request->input('ButtonPayload');

        $notification = $tenant->notifications()->create([
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'to_address' => PhoneNumber::normalize($request->input('To')),
            'from_address' => $from,
            'type' => filled($buttonPayload) ? 'button' : 'text',
            'payload' => $request->post(),
            'button_payload' => $buttonPayload,
            'reference' => $this->referenceFor($tenant, $from),
            'provider' => 'twilio',
            'provider_message_id' => $request->input('MessageSid'),
            'status' => 'received',
        ]);

        ForwardInboundMessageJob::dispatch($notification->id);

        return $this->ok();
    }

    /**
     * Actualizaciones de estado de los mensajes que enviamos
     * (queued/sent/delivered/read/failed).
     */
    public function status(Request $request): Response
    {
        $sid = $request->input('MessageSid');
        $status = $request->input('MessageStatus');

        if ($sid && $status) {
            Notification::where('provider_message_id', $sid)->update([
                'status' => $status,
                'error' => $request->input('ErrorMessage'),
            ]);
        }

        return $this->ok();
    }

    /**
     * Recupera la referencia de negocio (ej. "cita:4581") a partir del último
     * mensaje que le enviamos a ese teléfono desde esta instancia. Así el
     * sistema cliente sabe a qué cita corresponde la respuesta.
     */
    private function referenceFor(Tenant $tenant, ?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        return $tenant->notifications()
            ->where('direction', 'outbound')
            ->where('to_address', $phone)
            ->whereNotNull('reference')
            ->latest('id')
            ->value('reference');
    }

    /**
     * TwiML vacío: le dice a Twilio "recibido, no respondas nada".
     * La respuesta la decide el sistema cliente, no el gateway.
     */
    private function ok(): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }
}
