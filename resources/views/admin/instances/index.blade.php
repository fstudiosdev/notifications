@extends('layouts.admin')
@section('title', 'Instancias')

@section('content')
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
            <h1>Instancias</h1>
            <p class="muted">Cada instancia es un cliente (taller, clínica...) con su propio número y credenciales.</p>
        </div>
        <a class="btn" href="{{ route('admin.instances.create') }}">+ Nueva instancia</a>
    </div>

    <div class="card" style="margin-top:18px;">
        @if ($tenants->isEmpty())
            <p class="muted" style="margin:0;">Aún no hay instancias. Crea la primera.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th><th>Tipo</th><th>Proveedor</th><th>Número</th><th>Mensajes</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $t)
                        <tr>
                            <td><strong>{{ $t->name }}</strong><br><span class="muted">{{ $t->slug }}</span></td>
                            <td>{{ $t->type ?? '—' }}</td>
                            <td>{{ strtoupper($t->provider ?? 'meta') }}</td>
                            <td>{{ $t->provider === 'twilio' ? ($t->twilio_from ?? '—') : ($t->wa_phone_number ?? '—') }}</td>
                            <td>{{ $t->notifications_count }}</td>
                            <td>
                                <span class="pill {{ $t->active ? 'on' : 'off' }}">
                                    {{ $t->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td><a href="{{ route('admin.instances.show', $t) }}">Gestionar →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
