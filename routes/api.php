<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TwilioWebhookController;
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

/*
| Webhooks de los proveedores. No llevan token de instancia: se autentican
| por firma, y la instancia se identifica por el número que recibió el mensaje.
*/

// Twilio (form-urlencoded, firma X-Twilio-Signature).
Route::post('/webhooks/twilio/inbound', [TwilioWebhookController::class, 'inbound'])
    ->middleware('twilio.signature');
Route::post('/webhooks/twilio/status', [TwilioWebhookController::class, 'status'])
    ->middleware('twilio.signature');

// Meta (JSON, firma X-Hub-Signature-256).
//  - GET  -> verificación del webhook (challenge)
//  - POST -> mensajes entrantes y actualizaciones de estado
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('whatsapp.signature');
