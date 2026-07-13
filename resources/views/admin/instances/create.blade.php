@extends('layouts.admin')
@section('title', 'Nueva instancia')

@section('content')
    <p><a href="{{ route('admin.instances.index') }}">← Instancias</a></p>
    <h1>Nueva instancia</h1>
    <p class="muted">Al crearla se generan sus credenciales (client_id + client_secret).</p>

    <div class="card" style="max-width:520px;">
        @if ($errors->any())
            <div class="flash err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.instances.store') }}">
            @csrf
            <label>Nombre del cliente</label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Taller Gómez" autofocus required>

            <label>Tipo (opcional)</label>
            <input type="text" name="type" value="{{ old('type') }}" placeholder="taller, clinica...">

            <label>Proveedor de envío</label>
            <select name="provider">
                <option value="meta" {{ old('provider') === 'twilio' ? '' : 'selected' }}>Meta (WhatsApp Cloud API)</option>
                <option value="twilio" {{ old('provider') === 'twilio' ? 'selected' : '' }}>Twilio</option>
            </select>
            <p class="muted" style="font-size:12px; margin-top:6px;">Podrás cambiarlo y configurar sus credenciales después.</p>

            <button class="btn" style="margin-top:20px;">Crear instancia</button>
        </form>
    </div>
@endsection
