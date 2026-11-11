<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega a la instancia un archivo (foto, nota de voz, documento) de un
 * mensaje entrante suyo. Twilio pide las credenciales de la cuenta para
 * descargar los archivos, y esas credenciales solo las tiene el gateway: la
 * instancia nunca las ve. Solo se entrega lo de la propia instancia.
 */
class InboundMediaController extends Controller
{
    /** Tope por archivo (WhatsApp permite hasta 16 MB en audio y video). */
    private const MAX_BYTES = 16 * 1024 * 1024;

    public function show(Request $request, int $notification, int $index): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $n = Notification::query()
            ->whereKey($notification)
            ->where('tenant_id', $tenant->id)
            ->where('direction', 'inbound')
            ->first();

        $url = $n ? data_get($n->payload, "MediaUrl{$index}") : null;

        if (! $n || $n->provider !== 'twilio' || blank($url) || ! str_starts_with($url, 'https://api.twilio.com/')) {
            return response()->json(['ok' => false, 'error' => 'Archivo no encontrado.'], 404);
        }

        // Twilio redirige a su almacenamiento; Guzzle no reenvía la clave a otro dominio.
        $r = Http::withBasicAuth((string) $tenant->twilio_account_sid, (string) $tenant->twilio_auth_token)
            ->timeout(30)
            ->get($url);

        if ($r->failed()) {
            return response()->json(['ok' => false, 'error' => 'Twilio respondió '.$r->status().'.'], 502);
        }

        $body = $r->body();
        if (strlen($body) > self::MAX_BYTES) {
            return response()->json(['ok' => false, 'error' => 'El archivo pasa de 16 MB.'], 413);
        }

        $mime = data_get($n->payload, "MediaContentType{$index}") ?: ($r->header('Content-Type') ?: 'application/octet-stream');

        return response($body, 200, ['Content-Type' => $mime, 'Cache-Control' => 'no-store']);
    }
}
