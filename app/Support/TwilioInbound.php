<?php

namespace App\Support;

use App\Messaging\PhoneNumber;
use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Utilidades para resolver a qué instancia (tenant) pertenece un mensaje entrante
 * de Twilio, tanto para números dedicados como para el número COMUNITARIO
 * (compartido por varias clínicas con las mismas credenciales de Twilio).
 */
class TwilioInbound
{
    /**
     * Resuelve la instancia y la referencia de negocio de un mensaje entrante.
     *
     * Cascada:
     *  1) Contexto de respuesta: Twilio manda `OriginalRepliedMessageSid` (el SID
     *     del saliente que llevaba los botones). Ese saliente ya está asociado a
     *     una instancia y a una referencia → es lo más robusto y sirve tanto para
     *     número dedicado como compartido.
     *  2) Por el número que recibió el mensaje (`To`), cuando ese número es de una
     *     sola instancia (número dedicado).
     *  3) Número compartido sin contexto (p. ej. texto libre): el último saliente a
     *     ese paciente entre las instancias que comparten el número.
     *
     * @return array{0: ?Tenant, 1: ?string}  [instancia, referencia]
     */
    public static function resolve(Request $request): array
    {
        // 1) Contexto de respuesta (lo más confiable).
        $repliedSid = $request->input('OriginalRepliedMessageSid');
        if (filled($repliedSid)) {
            $saliente = Notification::query()
                ->where('direction', 'outbound')
                ->where('provider_message_id', $repliedSid)
                ->first();

            if ($saliente) {
                return [$saliente->tenant, $saliente->reference];
            }
        }

        $to = PhoneNumber::normalize($request->input('To'));
        $from = PhoneNumber::normalize($request->input('From'));

        // Instancias dueñas de ese número (por dígitos, tolerando formato).
        $candidatos = Tenant::query()
            ->where('provider', 'twilio')
            ->whereNotNull('twilio_from')
            ->get()
            ->filter(fn (Tenant $t) => PhoneNumber::normalize($t->twilio_from) === $to)
            ->values();

        // 2) Número dedicado: una sola instancia dueña del número.
        if ($candidatos->count() === 1) {
            $tenant = $candidatos->first();

            return [$tenant, self::ultimaReferencia($tenant->id, $from)];
        }

        // 3) Número compartido sin contexto: último saliente a ese paciente entre
        //    las instancias que comparten el número.
        if ($candidatos->count() > 1) {
            $saliente = Notification::query()
                ->whereIn('tenant_id', $candidatos->pluck('id'))
                ->where('direction', 'outbound')
                ->where('to_address', $from)
                ->latest('id')
                ->first();

            if ($saliente) {
                return [$saliente->tenant, $saliente->reference];
            }
        }

        return [null, null];
    }

    /**
     * Última referencia de negocio (ej. "cita:4581") que ESA instancia le envió a
     * ese teléfono. Sirve para asociar la respuesta a la cita correcta.
     */
    public static function ultimaReferencia(int $tenantId, ?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        return Notification::query()
            ->where('tenant_id', $tenantId)
            ->where('direction', 'outbound')
            ->where('to_address', $phone)
            ->whereNotNull('reference')
            ->latest('id')
            ->value('reference');
    }

    /**
     * La instancia se identifica por el número que RECIBIÓ el mensaje (el campo
     * "To" que manda Twilio, ej. "whatsapp:+50322334455"). Lo usa el middleware de
     * firma para obtener el Auth Token; en un número comunitario todas las
     * instancias comparten las mismas credenciales, así que cualquiera devuelve un
     * token válido (se ordena por id para que sea determinístico).
     *
     * Se compara por dígitos porque twilio_from puede estar guardado con "+",
     * espacios o guiones.
     */
    public static function tenantFor(?string $to): ?Tenant
    {
        $digits = PhoneNumber::normalize($to);

        if ($digits === null) {
            return null;
        }

        return Tenant::query()
            ->where('provider', 'twilio')
            ->whereNotNull('twilio_from')
            ->orderBy('id')
            ->get()
            ->first(fn (Tenant $t) => PhoneNumber::normalize($t->twilio_from) === $digits);
    }
}
