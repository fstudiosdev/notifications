<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
*/

// Login de la instancia: client_id + client_secret -> access_token.
Route::post('/auth/token', [AuthController::class, 'token']);

// Endpoints protegidos: el sistema cliente manda Authorization: Bearer <token>.
// Sanctum resuelve el token a su instancia (tenant).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/notification/message', [NotificationController::class, 'store']);
});

// Webhook de Meta (WhatsApp). No lleva token de instancia:
//  - GET  -> verificación del webhook (challenge)
//  - POST -> mensajes entrantes y actualizaciones de estado (firma verificada)
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('whatsapp.signature');
