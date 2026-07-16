<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendNotificationRequest;
use App\Messaging\NotificationDispatcher;
use App\Messaging\OutboundMessage;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    /**
     * Envía una notificación de WhatsApp para el tenant autenticado.
     */
    public function store(SendNotificationRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $reference = $request->input('referencia');

        $message = $request->input('type') === 'template'
            ? OutboundMessage::template(
                to: $request->input('to'),
                name: $request->input('template'),
                language: $request->input('language', 'es'),
                params: $request->input('params', []),
                reference: $reference,
            )
            : OutboundMessage::text(
                to: $request->input('to'),
                body: $request->input('text'),
                reference: $reference,
            );

        $notification = $this->dispatcher->queue($tenant, $message);

        // El envío ocurre en segundo plano; devolvemos 202 (aceptado).
        // El estado final se puede consultar luego o vía webhook.
        return response()->json([
            'id' => $notification->id,
            'status' => $notification->status, // queued
            'referencia' => $notification->reference,
        ], 202);
    }
}
