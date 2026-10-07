<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Messaging\PhoneNumber;
use App\Models\AssistantNumber;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La instancia registra (o quita) los teléfonos de sus doctores que hablan
 * con su asistente IA. Solo sirve para enrutar los mensajes entrantes; quién
 * puede hablar con el asistente lo decide y lo verifica la instancia.
 */
class AssistantNumberController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $phone = $this->phone($request);

        // En un número compartido, un teléfono solo puede ser de UNA de las
        // instancias que lo comparten: si no, no sabríamos a cuál enrutarlo.
        $ocupado = AssistantNumber::query()
            ->where('phone', $phone)
            ->where('tenant_id', '!=', $tenant->id)
            ->whereIn('tenant_id', $this->mismoNumero($tenant))
            ->exists();

        if ($ocupado) {
            return response()->json([
                'ok' => false,
                'error' => 'Ese teléfono ya está registrado en otra instancia que usa el mismo número de WhatsApp.',
            ], 409);
        }

        AssistantNumber::firstOrCreate(['tenant_id' => $tenant->id, 'phone' => $phone]);

        return response()->json(['ok' => true, 'phone' => $phone], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        AssistantNumber::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $this->phone($request))
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function phone(Request $request): string
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $phone = PhoneNumber::normalize($request->input('phone'));
        abort_if($phone === null || strlen($phone) < 8 || strlen($phone) > 15, 422, 'Teléfono inválido.');

        return $phone;
    }

    /**
     * Ids de las instancias que envían desde el mismo número que esta.
     *
     * @return list<int>
     */
    private function mismoNumero(Tenant $tenant): array
    {
        $propio = PhoneNumber::normalize($tenant->twilio_from);

        if ($propio === null) {
            return [$tenant->id];
        }

        return Tenant::query()
            ->whereNotNull('twilio_from')
            ->get(['id', 'twilio_from'])
            ->filter(fn (Tenant $t) => PhoneNumber::normalize($t->twilio_from) === $propio)
            ->pluck('id')
            ->all();
    }
}
