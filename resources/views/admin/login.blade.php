@extends('layouts.admin')
@section('title', 'Ingresar')

@section('content')
    <div style="max-width: 400px; margin: 40px auto;">
        <h1>Panel de notificaciones</h1>
        <p class="muted">Ingresa para gestionar las instancias.</p>

        <div class="card">
            @if ($errors->any())
                <div class="flash err">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <label>Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" autofocus required>

                <label>Contraseña</label>
                <input type="password" name="password" required>

                <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
                    <input type="checkbox" name="remember" style="width:auto;"> Recordarme
                </label>

                <button class="btn" style="width:100%; margin-top:18px;">Ingresar</button>
            </form>
        </div>
    </div>
@endsection
