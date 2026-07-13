@extends('layouts.admin')
@section('title', $tenant->name)

@section('content')
    <p><a href="{{ route('admin.instances.index') }}">← Instancias</a></p>

    @if (session('error'))
        <div class="flash err">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="flash err">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
        <div>
            <h1>{{ $tenant->name }}</h1>
            <p class="muted">
                {{ $tenant->slug }} · {{ $tenant->type ?? 'sin tipo' }} ·
                <span class="pill {{ $tenant->active ? 'on' : 'off' }}">{{ $tenant->active ? 'Activa' : 'Inactiva' }}</span>
                · Proveedor: <strong>{{ strtoupper($tenant->provider ?? 'meta') }}</strong>
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

    <h2>Proveedor de envío</h2>
    <div class="card">
        <p class="muted" style="margin-top:0;">Define por dónde salen los mensajes de esta instancia. El resto del sistema funciona igual.</p>
        <form method="POST" action="{{ route('admin.instances.provider', $tenant) }}">
            @csrf
            @method('PUT')
            <div class="row" style="align-items:flex-end;">
                <div>
                    <label>Proveedor activo</label>
                    <select name="provider">
                        <option value="meta" {{ ($tenant->provider ?? 'meta') === 'meta' ? 'selected' : '' }}>Meta (WhatsApp Cloud API)</option>
                        <option value="twilio" {{ $tenant->provider === 'twilio' ? 'selected' : '' }}>Twilio</option>
                    </select>
                </div>
                <div style="flex:0;">
                    <button class="btn secondary">Cambiar proveedor</button>
                </div>
            </div>
        </form>
    </div>

    @if (($tenant->provider ?? 'meta') === 'meta')
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
    @endif

    @if ($tenant->provider === 'twilio')
    <h2>Twilio</h2>
    <div class="card">
        <p class="muted" style="margin-top:0;">Datos de tu consola de Twilio (Account SID, Auth Token y número emisor de WhatsApp).</p>
        <form method="POST" action="{{ route('admin.instances.twilio', $tenant) }}">
            @csrf
            @method('PUT')
            <div class="row">
                <div>
                    <label>Account SID</label>
                    <input type="text" name="twilio_account_sid" value="{{ old('twilio_account_sid', $tenant->twilio_account_sid) }}" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                </div>
                <div>
                    <label>Número emisor (WhatsApp)</label>
                    <input type="text" name="twilio_from" value="{{ old('twilio_from', $tenant->twilio_from) }}" placeholder="+14155238886">
                </div>
            </div>

            <label>Auth Token {{ $tenant->twilio_auth_token ? '(ya configurado — deja vacío para conservarlo)' : '' }}</label>
            <input type="password" name="twilio_auth_token" placeholder="{{ $tenant->twilio_auth_token ? '••••••••••••' : 'tu Auth Token de Twilio' }}">

            <button class="btn" style="margin-top:18px;">Guardar credenciales de Twilio</button>
        </form>
    </div>
    @endif

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

    <h2>Enviar prueba</h2>
    <div class="card">
        <p class="muted" style="margin-top:0;">
            Envía una notificación de inmediato para verificar la configuración.
            @if (config('messaging.driver') === 'log')
                <strong>Modo simulación activo</strong> (<code>MESSAGING_DRIVER=log</code>): no se envía nada real, solo se registra.
            @else
                Envío <strong>real</strong> por <strong>{{ strtoupper($tenant->provider ?? 'meta') }}</strong>: requiere las credenciales de ese proveedor cargadas arriba.
            @endif
        </p>
        <form method="POST" action="{{ route('admin.instances.test', $tenant) }}">
            @csrf
            <div class="row">
                <div>
                    <label>Número destino</label>
                    <input type="text" name="to" value="{{ old('to') }}" placeholder="+521234567890" required>
                </div>
                <div>
                    <label>Tipo</label>
                    <select name="type" id="test-type" onchange="document.getElementById('test-text').style.display = this.value === 'text' ? 'block' : 'none'; document.getElementById('test-template').style.display = this.value === 'template' ? 'block' : 'none';">
                        <option value="text" {{ old('type') === 'template' ? '' : 'selected' }}>Texto</option>
                        <option value="template" {{ old('type') === 'template' ? 'selected' : '' }}>Plantilla</option>
                    </select>
                </div>
            </div>

            <div id="test-text" style="display:{{ old('type') === 'template' ? 'none' : 'block' }};">
                <label>Texto del mensaje</label>
                <input type="text" name="text" value="{{ old('text') }}" placeholder="Mensaje de prueba desde el panel.">
            </div>

            <div id="test-template" style="display:{{ old('type') === 'template' ? 'block' : 'none' }};">
                <div class="row">
                    <div>
                        <label>Nombre de la plantilla</label>
                        <input type="text" name="template" value="{{ old('template') }}" placeholder="hello_world">
                    </div>
                    <div>
                        <label>Idioma</label>
                        <input type="text" name="language" value="{{ old('language', 'es') }}" placeholder="es">
                    </div>
                </div>
                <label>Parámetros (separados por coma)</label>
                <input type="text" name="params" value="{{ old('params') }}" placeholder="Miguel, 14/07 10:00">
            </div>

            <button class="btn" style="margin-top:18px;">Enviar prueba</button>
        </form>
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
