<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Messaging\NotificationDispatcher;
use App\Messaging\OutboundMessage;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstanceController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::withCount('notifications')
            ->orderBy('name')
            ->get();

        return view('admin.instances.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('admin.instances.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);

        $slug = Str::slug($data['name']);
        if (Tenant::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $tenant = new Tenant([
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $data['type'] ?? null,
            'active' => true,
            'provider' => 'meta',
        ]);

        $secret = $tenant->generateCredentials();
        $tenant->save();

        // El secret solo se muestra una vez (flash de sesión).
        return redirect()
            ->route('admin.instances.show', $tenant)
            ->with('new_secret', $secret);
    }

    public function show(Tenant $tenant): View
    {
        $messages = $tenant->notifications()
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.instances.show', compact('tenant', 'messages'));
    }

    public function updateWhatsapp(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'wa_phone_number_id' => ['nullable', 'string', 'max:60'],
            'wa_business_account_id' => ['nullable', 'string', 'max:60'],
            'wa_phone_number' => ['nullable', 'string', 'max:25'],
            'wa_access_token' => ['nullable', 'string'],
        ]);

        // Si el token viene vacío, conservamos el que ya estaba.
        if (blank($data['wa_access_token'])) {
            unset($data['wa_access_token']);
        }

        $tenant->update($data);

        return redirect()
            ->route('admin.instances.show', $tenant)
            ->with('status', 'Credenciales de WhatsApp actualizadas.');
    }

    public function regenerateSecret(Tenant $tenant): RedirectResponse
    {
        $secret = $tenant->generateCredentials();
        $tenant->save();

        // Al cambiar credenciales, invalidamos los tokens de acceso vigentes.
        $tenant->tokens()->delete();

        return redirect()
            ->route('admin.instances.show', $tenant)
            ->with('new_secret', $secret)
            ->with('status', 'Se generaron credenciales nuevas. Las anteriores dejaron de servir.');
    }

    public function toggleActive(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['active' => ! $tenant->active]);

        return redirect()
            ->route('admin.instances.show', $tenant)
            ->with('status', $tenant->active ? 'Instancia activada.' : 'Instancia desactivada.');
    }

    /**
     * Envía una notificación de prueba de forma síncrona desde el panel y
     * muestra el resultado. Ideal con MESSAGING_DRIVER=log (simulación) para
     * probar el flujo sin credenciales reales de WhatsApp.
     */
    public function sendTest(Request $request, Tenant $tenant, NotificationDispatcher $dispatcher): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:20'],
            'type' => ['required', Rule::in(['text', 'template'])],
            'text' => ['required_if:type,text', 'nullable', 'string', 'max:4096'],
            'template' => ['required_if:type,template', 'nullable', 'string', 'max:120'],
            'language' => ['nullable', 'string', 'max:10'],
            // Parámetros de plantilla separados por coma (p.ej. "Miguel, 14/07 10:00").
            'params' => ['nullable', 'string', 'max:500'],
        ]);

        $message = $data['type'] === 'template'
            ? OutboundMessage::template(
                to: $data['to'],
                name: $data['template'],
                language: $data['language'] ?: 'es',
                params: $this->parseParams($data['params'] ?? null),
            )
            : OutboundMessage::text(to: $data['to'], body: $data['text']);

        $notification = $dispatcher->sendNow($tenant, $message);

        if ($notification->status === 'sent') {
            $sim = config('messaging.driver') === 'log' ? ' (modo simulación)' : '';

            return redirect()
                ->route('admin.instances.show', $tenant)
                ->with('status', "Prueba enviada correctamente{$sim}. Estado: {$notification->status}.");
        }

        return redirect()
            ->route('admin.instances.show', $tenant)
            ->with('error', 'La prueba falló: '.($notification->error ?: 'error desconocido.'));
    }

    /**
     * Convierte "a, b, c" en ['a', 'b', 'c'], ignorando espacios y vacíos.
     *
     * @return array<int, string>
     */
    private function parseParams(?string $raw): array
    {
        if (blank($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }
}
