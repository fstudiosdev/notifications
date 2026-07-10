<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · Notificaciones</title>
    <style>
        :root {
            --bg: #0f172a; --panel: #1e293b; --panel-2: #334155;
            --text: #e2e8f0; --muted: #94a3b8; --border: #334155;
            --accent: #22c55e; --accent-2: #16a34a; --danger: #ef4444;
            --warn: #f59e0b; --blue: #3b82f6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg); color: var(--text); line-height: 1.5;
        }
        a { color: var(--blue); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px; background: var(--panel); border-bottom: 1px solid var(--border);
        }
        .topbar .brand { font-weight: 700; font-size: 16px; color: var(--text); }
        .topbar .brand span { color: var(--accent); }
        .container { max-width: 980px; margin: 0 auto; padding: 28px 24px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 16px; margin: 28px 0 12px; color: var(--text); }
        .muted { color: var(--muted); }
        .card {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 12px; padding: 20px; margin-bottom: 18px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 14px; }
        th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        tr:last-child td { border-bottom: none; }
        .btn {
            display: inline-block; padding: 9px 16px; border-radius: 8px; border: none;
            background: var(--accent); color: #06240f; font-weight: 600; font-size: 14px;
            cursor: pointer; text-decoration: none;
        }
        .btn:hover { background: var(--accent-2); text-decoration: none; }
        .btn.secondary { background: var(--panel-2); color: var(--text); }
        .btn.danger { background: transparent; color: var(--danger); border: 1px solid var(--danger); }
        .btn.small { padding: 6px 11px; font-size: 13px; }
        label { display: block; font-size: 13px; color: var(--muted); margin: 14px 0 6px; }
        input, select {
            width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border);
            background: var(--bg); color: var(--text); font-size: 14px;
        }
        input:focus, select:focus { outline: none; border-color: var(--accent); }
        .row { display: flex; gap: 16px; flex-wrap: wrap; }
        .row > div { flex: 1; min-width: 200px; }
        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
        .flash.ok { background: #052e16; border: 1px solid var(--accent-2); color: #86efac; }
        .flash.warn { background: #422006; border: 1px solid var(--warn); color: #fcd34d; }
        .flash.err { background: #450a0a; border: 1px solid var(--danger); color: #fca5a5; }
        .pill { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pill.on { background: #052e16; color: #86efac; }
        .pill.off { background: #450a0a; color: #fca5a5; }
        .pill.st-sent, .pill.st-delivered, .pill.st-read { background: #052e16; color: #86efac; }
        .pill.st-queued { background: #1e293b; color: var(--muted); }
        .pill.st-failed { background: #450a0a; color: #fca5a5; }
        .pill.st-received { background: #172554; color: #93c5fd; }
        code {
            background: var(--bg); padding: 3px 8px; border-radius: 6px;
            font-family: ui-monospace, "Cascadia Code", monospace; font-size: 13px;
            border: 1px solid var(--border); word-break: break-all;
        }
        .kv { display: flex; gap: 10px; margin: 8px 0; align-items: baseline; }
        .kv .k { color: var(--muted); font-size: 13px; min-width: 130px; }
        .dir-in { color: #93c5fd; } .dir-out { color: #86efac; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">Notifica<span>·</span>API</div>
        @auth
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="btn secondary small">Salir</button>
        </form>
        @endauth
    </div>
    <div class="container">
        @if (session('status'))
            <div class="flash ok">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
</body>
</html>
