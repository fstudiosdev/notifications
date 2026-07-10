<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
}
