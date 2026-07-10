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

            <button class="btn" style="margin-top:20px;">Crear instancia</button>
        </form>
    </div>
@endsection
