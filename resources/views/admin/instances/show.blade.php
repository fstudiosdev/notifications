@extends('layouts.admin')
@section('title', $tenant->name)

@section('content')
    <p><a href="{{ route('admin.instances.index') }}">← Instancias</a></p>

    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
        <div>
            <h1>{{ $tenant->name }}</h1>
            <p class="muted">
                {{ $tenant->slug }} · {{ $tenant->type ?? 'sin tipo' }} ·
                <span class="pill {{ $tenant->active ? 'on' : 'off' }}">{{ $tenant->active ? 'Activa' : 'Inactiva' }}</span>
            </p>
        </div>
        <form method="POST" action="{{ route('admin.instances.toggle', $tenant) }}">
            @csrf
            <button class="btn secondary small">{{ $tenant->active ? 'Desactivar' : 'Activar' }}</button>
        </form>
    </div>

    {{-- Secret recién generado: se muestra una sola vez --}}
    @if (session('new_secret'))
        <div class="flash warn">
            <strong>Guarda estas credenciales ahora.</strong> El <code>client_secret</code> no se vuelve a mostrar.
            <div class="kv" style="margin-top:10px;"><span class="k">client_id</span> <code>{{ $tenant->client_id }}</code></div>
            <div class="kv"><span class="k">client_secret</span> <code>{{ session('new_secret') }}</code></div>
        </div>
    @endif

    <h2>Credenciales de la API</h2>
    <div class="card">
        <p class="muted" style="margin-top:0;">Estos son los datos que entregas al sistema del cliente para conectarse.</p>
        <div class="kv"><span class="k">client_id</span> <code>{{ $tenant->client_id }}</code></div>
        <div class="kv"><span class="k">client_secret</span> <span class="muted">oculto (solo se ve al generarlo)</span></div>
        <form method="POST" action="{{ route('admin.instances.regenerate', $tenant) }}"
              onsubmit="return confirm('Se generará un secret nuevo y se invalidarán los tokens actuales. ¿Continuar?');"
              style="margin-top:14px;">
            @csrf
            <button class="btn danger small">Regenerar client_secret</button>
        </form>
    </div>

    <h2>WhatsApp (Meta)</h2>
    <div class="card">
        <p class="muted" style="margin-top:0;">Datos que sacas del panel de Meta para el número de esta instancia.</p>
        <form method="POST" action="{{ route('admin.instances.whatsapp', $tenant) }}">
            @csrf
            @method('PUT')
            <div class="row">
                <div>
                    <label>Phone Number ID</label>
                    <input type="text" name="wa_phone_number_id" value="{{ old('wa_phone_number_id', $tenant->wa_phone_number_id) }}" placeholder="123456789012345">
                </div>
                <div>
                    <label>WhatsApp Business Account ID</label>
                    <input type="text" name="wa_business_account_id" value="{{ old('wa_business_account_id', $tenant->wa_business_account_id) }}" placeholder="987654321098765">
                </div>
            </div>
            <label>Número (visible)</label>
            <input type="text" name="wa_phone_number" value="{{ old('wa_phone_number', $tenant->wa_phone_number) }}" placeholder="+521234567890">

            <label>Access Token {{ $tenant->wa_access_token ? '(ya configurado — deja vacío para conservarlo)' : '' }}</label>
            <input type="password" name="wa_access_token" placeholder="{{ $tenant->wa_access_token ? '••••••••••••' : 'EAAG...' }}">

            <button class="btn" style="margin-top:18px;">Guardar credenciales de WhatsApp</button>
        </form>
    </div>

    <h2>Cómo se conecta el cliente</h2>
    <div class="card">
        <p class="muted" style="margin-top:0;">1) Pide un token con sus credenciales · 2) Envía mensajes con ese token.</p>
        <pre style="overflow-x:auto; background:var(--bg); padding:14px; border-radius:8px; border:1px solid var(--border); font-size:13px;">POST /api/auth/token
{ "client_id": "{{ $tenant->client_id }}", "client_secret": "••••" }
  → { "access_token": "..." }

POST /api/notification/message
Authorization: Bearer &lt;access_token&gt;
{ "to": "+521234567890", "type": "text", "text": "Su auto está listo" }</pre>
    </div>

    <h2>Mensajes recientes ({{ $messages->count() }})</h2>
    <div class="card">
        @if ($messages->isEmpty())
            <p class="muted" style="margin:0;">Todavía no hay mensajes para esta instancia.</p>
        @else
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Dir.</th><th>Contacto</th><th>Tipo</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    @foreach ($messages as $m)
                        <tr>
                            <td class="muted">{{ $m->created_at->format('d/m H:i') }}</td>
                            <td class="{{ $m->direction === 'inbound' ? 'dir-in' : 'dir-out' }}">
                                {{ $m->direction === 'inbound' ? '↓ entra' : '↑ sale' }}
                            </td>
                            <td>{{ $m->direction === 'inbound' ? $m->from_address : $m->to_address }}</td>
                            <td>{{ $m->type }}</td>
                            <td>
                                <span class="pill st-{{ $m->status }}">{{ $m->status }}</span>
                                @if ($m->error)<br><span class="muted" style="font-size:12px;">{{ Str::limit($m->error, 40) }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
