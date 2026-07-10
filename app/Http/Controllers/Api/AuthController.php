<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Intercambia client_id + client_secret por un access token de Sanctum.
     *
     * El sistema cliente llama esto una vez y luego usa el token devuelto
     * en el header Authorization: Bearer <token> de cada petición.
     */
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
        ]);

        $tenant = Tenant::where('client_id', $data['client_id'])->first();

        if (! $tenant || ! $tenant->active || ! $tenant->checkSecret($data['client_secret'])) {
            return response()->json([
                'message' => 'Credenciales inválidas o instancia inactiva.',
            ], 401);
        }

        // Un token por sesión de conexión. Se puede revocar desde el panel.
        $token = $tenant->createToken('api')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'instance' => $tenant->slug,
        ]);
    }
}
