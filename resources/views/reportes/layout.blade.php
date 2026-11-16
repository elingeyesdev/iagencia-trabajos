<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — @yield('titulo')</title>
    @isset($token)
        {{-- Vista imprimible que consume GET /api/admin/reportes/{tipo} (token Sanctum de corta duración). --}}
        <meta name="api-token" content="{{ $token }}">
        <meta name="reporte-tipo" content="{{ $tipo }}">
        @vite('resources/js/reportes.js')
    @endisset
    <style>
        :root { --oscuro:#060d14; --acento:#FF5A5F; --borde:#d9dee5; --suave:#f4f6f9; --texto:#1b2430; --gris:#5b6675; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:'DM Sans', 'Segoe UI', Arial, sans-serif; color:var(--texto); background:#fff; font-size:14px; }
        .nav { background:var(--oscuro); color:#fff; padding:12px 28px; display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
        .nav .logo { font-weight:700; letter-spacing:2px; font-size:18px; }
        .nav .logo span { color:var(--acento); }
        .nav a { color:#cfd6df; text-decoration:none; font-size:13px; }
        .nav a:hover { color:#fff; }
        .contenido { max-width:1200px; margin:0 auto; padding:24px 28px 48px; }
        .cabecera { border-bottom:3px solid var(--acento); padding-bottom:10px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; }
        h1 { margin:0; font-size:24px; letter-spacing:.5px; }
        h2 { font-size:16px; margin:24px 0 8px; color:var(--oscuro); }
        .meta { color:var(--gris); font-size:12px; }
        .btn { background:var(--acento); color:#fff; border:0; border-radius:6px; padding:9px 16px; font-size:13px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn.sec { background:var(--oscuro); }
        .filtros { background:var(--suave); border:1px solid var(--borde); border-radius:8px; padding:12px 14px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px; }
        .filtros label { display:flex; flex-direction:column; font-size:12px; color:var(--gris); gap:4px; }
        .filtros select, .filtros input { padding:6px 8px; border:1px solid var(--borde); border-radius:5px; font-size:13px; }
        .filtros-aplicados { font-size:12px; color:var(--gris); margin:0 0 12px; }
        .errores { background:#fdecec; border:1px solid #f5b5b5; color:#8a1f1f; border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:13px; }
        table { width:100%; border-collapse:collapse; font-size:12.5px; }
        th, td { border:1px solid var(--borde); padding:6px 8px; text-align:left; vertical-align:top; }
        th { background:var(--oscuro); color:#fff; font-weight:600; }
        tbody tr:nth-child(even) { background:var(--suave); }
        td.num, th.num { text-align:right; white-space:nowrap; }
        tfoot td { font-weight:700; background:#eef1f5; }
        .tarjetas { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
        .tarjeta { border:1px solid var(--borde); border-left:4px solid var(--acento); border-radius:6px; padding:10px 14px; min-width:150px; }
        .tarjeta .v { font-size:20px; font-weight:700; }
        .tarjeta .l { font-size:12px; color:var(--gris); }
        .vacio { padding:14px; text-align:center; color:var(--gris); font-style:italic; }
        .nota { font-size:12px; color:var(--gris); margin-top:6px; }
        .banda-simulado { background:#fff4d6; border:2px dashed #d99a00; color:#7a5200; text-align:center; font-weight:700; letter-spacing:1px; padding:10px; margin-bottom:16px; border-radius:6px; }
        .pie { margin-top:28px; font-size:11px; color:var(--gris); border-top:1px solid var(--borde); padding-top:8px; }
        @media print {
            .no-print, .nav, .filtros, .btn { display:none !important; }
            .contenido { padding:0; max-width:none; }
            body { font-size:11px; }
            th { background:#333 !important; color:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .banda-simulado { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            tr { page-break-inside:avoid; }
            @page { size:A4 landscape; margin:12mm; }
        }
    </style>
</head>
<body>
<nav class="nav no-print">
    <div class="logo">RED<span>-</span>WORK</div>
    <a href="{{ route('dashboard') }}">Panel</a>
    <a href="{{ route('reportes.index') }}">Reportes</a>
    <a href="{{ route('reportes.usuarios') }}">Usuarios</a>
    <a href="{{ route('reportes.publicaciones') }}">Publicaciones</a>
    <a href="{{ route('reportes.categorias') }}">Categorías</a>
    <a href="{{ route('reportes.actividad') }}">Actividad</a>
    <a href="{{ route('reportes.pagos') }}">Pagos simulados</a>
</nav>
<main class="contenido">
    <div class="cabecera">
        <div>
            <h1>@yield('titulo')</h1>
            {{-- reportes.js sustituye este texto por generado_en_texto/generado_por del JSON de la API. --}}
            <div class="meta" id="reporte-meta">RedWork · Generado el {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()?->nombre }}</div>
        </div>
        @hasSection('imprimible')
            {{-- Descarga GET /api/admin/reportes/{tipo}/pdf con el token Bearer de la página (reportes.js). --}}
            <button type="button" class="btn no-print" id="btn-pdf">Descargar PDF</button>
        @endif
    </div>

    @isset($token)
        {{-- Errores de filtros (422 de la API) y estado de carga/error: los rellena reportes.js (con esc()). --}}
        <div class="errores no-print" id="reporte-errores" style="display:none"></div>
        <p class="nota no-print" id="reporte-estado">Cargando datos del reporte…</p>
    @endisset

    @yield('contenido')

    <div class="pie">Reporte generado con datos reales de la base de RedWork. Las fechas del dominio no registran hora.</div>
</main>
</body>
</html>
