@php
    $user = auth()->user();
    $user->tokens()->where('name', 'dashboard-trabajador')->delete();
    $token = $user->createToken('dashboard-trabajador')->plainTextToken;
    // Datos para trabajador-dashboard.js (#trabajador-me): solo los campos que usa (ME.cedula, ME.nombre, ME.ciudad).
    $me = $user->only(['cedula', 'nombre', 'ciudad']);
    $ini = mb_strtoupper(mb_substr($user->nombre ?? 'T', 0, 2));
@endphp
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
<meta charset="UTF-8">
<script>/* Tema antes de pintar (sin parpadeo). Ver resources/js/themes.js */try{document.documentElement.setAttribute('data-theme',localStorage.getItem('redwork-theme')==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="api-token" content="{{ $token }}">
<title>RED-WORK — Trabajador</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
@vite(['resources/css/trabajador-dashboard.css', 'resources/js/trabajador-dashboard.js', 'resources/css/kpi-dashboard.css', 'resources/js/kpi-dashboard.js', 'resources/css/themes.css', 'resources/js/themes.js'])
</head>
<body class="rw-trabajador">

<nav class="topbar">
    <div class="topbar-left">
        <button class="menu-btn" data-action="menu">☰</button>
        <div class="logo"><span class="logo-icon">🔧</span> RED-WORK</div>
    </div>
    <div class="topbar-actions">
        <button type="button" class="theme-switch" data-theme-toggle role="switch" aria-checked="false" aria-label="Tema oscuro activado. Cambiar a tema claro" title="Cambiar a tema claro"><span class="theme-switch-track" aria-hidden="true"><span class="theme-switch-thumb">🌙</span></span><span class="theme-switch-label">Tema oscuro</span></button>
        <span class="rol-badge">🔧 Trabajador</span>
        <div class="dropdown" id="dropdown">
            <div class="avatar-btn" data-action="dropdown" id="avatar-top">{{ $ini }}</div>
            <div class="dropdown-menu">
                <button class="dropdown-item" data-panel="perfil"><span>👤</span> Mi Perfil</button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item danger"><span>🚪</span> Cerrar Sesión</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="nav-section">
            <div class="nav-label">Principal</div>
            <a class="nav-item active" data-panel="home"><span>🏠</span> Dashboard</a>
            <a class="nav-item" data-panel="buscar" data-filtro="todos" title="Temporales y recurrentes: usa las pestañas de la sección"><span>🔍</span> Buscar Trabajos <span class="badge" id="badge-buscar"></span></a>
        </div>
        <div class="nav-section">
            <div class="nav-label">Mi Actividad</div>
            <a class="nav-item" data-panel="postulaciones" title="Postulaciones y el estado de sus contrataciones"><span>📋</span> Mis Postulaciones <span class="badge" id="badge-post"></span></a>
            <a class="nav-item" data-panel="pagos"><span>💳</span> Mis Pagos</a>
            <a class="nav-item" data-panel="indicadores"><span>📊</span> Mi Seguimiento</a>
            <a class="nav-item" data-panel="calificaciones"><span>⭐</span> Calificaciones</a>
            <a class="nav-item" data-panel="perfil"><span>👤</span> Mi Perfil</a>
        </div>
        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout"><span>🚪</span> Cerrar Sesión</button>
            </form>
        </div>
    </aside>

    <main class="main">

        <!-- INICIO -->
        <section class="panel active" id="panel-home">
            <div class="welcome-banner">
                <div>
                    <div class="welcome-title">Hola, <span class="u-nombre">{{ $user->nombre }}</span> 👋</div>
                    <div class="welcome-sub">Encuentra trabajos disponibles y empieza a ganar.</div>
                </div>
                <div class="disponibilidad new" id="disp" data-action="disponibilidad"><span class="dot"></span><span id="disp-txt">Cargando…</span></div>
            </div>

            <div class="stats-grid">
                <div class="stat-card" style="--bar-color:#2DD4BF"><div class="stat-icon">🔍</div><div class="stat-label">Trabajos Disponibles</div><div class="stat-value" id="st-disp">–</div><div class="stat-delta" id="st-disp-d"></div></div>
                <div class="stat-card" style="--bar-color:#f59e0b"><div class="stat-icon">📋</div><div class="stat-label">Mis Postulaciones</div><div class="stat-value" id="st-post">–</div><div class="stat-delta" id="st-post-d"></div></div>
                <div class="stat-card" style="--bar-color:#4ade80"><div class="stat-icon">✅</div><div class="stat-label">Completados</div><div class="stat-value" id="st-comp">–</div><div class="stat-delta" id="st-comp-d"></div></div>
                <div class="stat-card" style="--bar-color:#FF5A5F"><div class="stat-icon">⭐</div><div class="stat-label">Calificación</div><div class="stat-value" id="st-cal">–</div><div class="stat-delta" id="st-cal-d"></div></div>
            </div>

            <div class="content-grid">
                <div>
                    <div class="section-title">🔍 Trabajos Recientes <a class="link" data-panel="buscar" data-filtro="todos">Ver todos →</a></div>
                    <div class="jobs-list" id="home-jobs"></div>
                </div>
                <div>
                    <div class="widget profile-widget">
                        <div class="widget-title">Mi Perfil</div>
                        <div class="profile-avatar" id="avatar-widget">{{ $ini }}</div>
                        <div class="profile-name u-nombre">{{ $user->nombre }}</div>
                        <div class="profile-role">Trabajador · <span class="u-ciudad">{{ $user->ciudad ?? 'Sin ciudad' }}</span></div>
                        <div id="w-stars"></div>
                        <div class="profile-stats">
                            <div class="pstat"><div class="pstat-val" id="w-comp">0</div><div class="pstat-lbl">Completos</div></div>
                            <div class="pstat"><div class="pstat-val" id="w-acep">–</div><div class="pstat-lbl">Aceptación</div></div>
                        </div>
                    </div>
                    <div class="widget"><div class="widget-title">Mis Habilidades</div><div id="w-skills"></div></div>
                    <div class="widget"><div class="widget-title">Últimas Calificaciones</div><div id="w-califs"></div></div>
                </div>
            </div>
        </section>

        <!-- BUSCAR -->
        <section class="panel" id="panel-buscar">
            <div class="page-title">🔍 Trabajos Disponibles</div>
            <div class="toolbar">
                <input class="input" id="f-busqueda" placeholder="Buscar por título, lugar…">
                <select class="select" id="f-categoria"><option value="">Todas las categorías</option></select>
            </div>
            <div class="toolbar" id="filtros">
                <button class="filtro-btn active" data-filtro="todos">Todos</button>
                <button class="filtro-btn" data-filtro="recurrente">Recurrentes</button>
                <button class="filtro-btn" data-filtro="temporal">Temporales</button>
                <button class="filtro-btn" data-filtro="ciudad">Mi ciudad</button>
                <button class="filtro-btn" data-filtro="mejor">Mejor pagados</button>
            </div>
            <div class="jobs-list" id="lista-jobs"></div>
        </section>

        <!-- POSTULACIONES -->
        <section class="panel" id="panel-postulaciones">
            <div class="page-title">📋 Mis Postulaciones</div>
            <p class="muted" style="margin:-.4rem 0 1rem;font-size:.85rem;">Incluye el estado de la contratación de cada postulación aceptada.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Oferta</th><th>Contratador</th><th>Pago</th><th>Fecha</th><th>Estado</th><th>Contratación</th><th>Acciones</th></tr></thead>
                    <tbody id="tbody-post"><tr><td colspan="7" class="muted" style="text-align:center">Cargando…</td></tr></tbody>
                </table>
            </div>
        </section>

        <!-- MIS PAGOS (solo lectura; registros SIMULADOS) -->
        <section class="panel" id="panel-pagos">
            <div class="page-title">💳 Mis Pagos <span class="chip chip-yellow">SIMULADO</span></div>
            <div class="card" style="border-color:rgba(245,158,11,.3);color:var(--warning);font-size:.85rem;" id="pagos-aviso">SIMULADO — estos registros no representan dinero real.</div>
            <div class="section-title">Resumen por contratación</div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Contratación</th><th>Estado</th><th>Monto acordado</th><th>Confirmado</th><th>Pendiente</th><th>Saldo</th></tr></thead>
                    <tbody id="tbody-pagos-resumen"><tr><td colspan="6" class="muted" style="text-align:center">Cargando…</td></tr></tbody>
                </table>
            </div>
            <div class="section-title" style="margin-top:1.2rem">Pagos registrados</div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Labor</th><th>Contratador</th><th>Fecha</th><th>Método</th><th>Monto</th><th>Estado</th></tr></thead>
                    <tbody id="tbody-pagos"><tr><td colspan="6" class="muted" style="text-align:center">Cargando…</td></tr></tbody>
                </table>
            </div>
        </section>

        <!-- MI SEGUIMIENTO (GET /api/trabajador/kpis — resources/js/kpi-dashboard.js) -->
        <section class="panel" id="panel-indicadores">
            <div class="kpi-bi" id="kpi-trabajador" data-kpi-dashboard data-alcance="trabajador"
                 data-endpoint="/api/trabajador/kpis"></div>
        </section>

        <!-- CALIFICACIONES -->
        <section class="panel" id="panel-calificaciones">
            <div class="page-title">⭐ Calificaciones Recibidas</div>
            <div class="card" id="calif-resumen"></div>
            <div class="card" id="calif-lista"></div>
        </section>

        <!-- PERFIL -->
        <section class="panel" id="panel-perfil">
            <div class="page-title">👤 Mi Perfil</div>

            <div class="card">
                <div class="widget-title">Datos personales · Cédula {{ $user->cedula }}</div>
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Nombre</label><input class="input" id="p-nombre" value="{{ $user->nombre }}"></div>
                    <div class="form-group"><label class="form-label">Correo</label><input class="input" id="p-email" type="email" value="{{ $user->email }}"></div>
                    <div class="form-group"><label class="form-label">Teléfono</label><input class="input" id="p-telefono" value="{{ $user->telefono }}"></div>
                    <div class="form-group"><label class="form-label">Ciudad</label><input class="input" id="p-ciudad" value="{{ $user->ciudad }}"></div>
                    <div class="form-group full"><label class="form-label">Dirección</label><input class="input" id="p-direccion" value="{{ $user->direccion }}"></div>
                </div>
                <button class="btn btn-accent" data-action="guardar-datos">Guardar datos</button>
            </div>

            <div class="card">
                <div class="widget-title">Perfil profesional</div>
                <div class="form-grid">
                    <div class="form-group full"><label class="form-label">Habilidades (separadas por coma)</label><textarea class="textarea" id="pp-habilidades" placeholder="Ej: Plomería, Electricidad, Pintura"></textarea></div>
                    <div class="form-group full"><label class="form-label">Experiencia</label><textarea class="textarea" id="pp-experiencia" placeholder="Cuéntales a los contratadores qué has hecho"></textarea></div>
                    <div class="form-group"><label class="form-label">Disponibilidad</label><input class="input" id="pp-disponibilidad" list="disp-opts" placeholder="Ej: Fines de semana"><datalist id="disp-opts"><option value="Tiempo completo"><option value="Medio tiempo"><option value="Fines de semana"><option value="Por horas"></datalist></div>
                    <div class="form-group"><label class="form-label">Tarifa referencial (Bs)</label><input class="input" id="pp-tarifa" type="number" min="0" step="0.01"></div>
                    <div class="form-group full"><label class="check"><input type="checkbox" id="pp-estado" checked> Estoy disponible para nuevos trabajos</label></div>
                </div>
                <button class="btn btn-accent" data-action="guardar-profesional">Guardar perfil profesional</button>
            </div>
        </section>

    </main>
</div>

<!-- MODAL OFERTA / POSTULAR -->
<div class="modal-overlay" id="modal-oferta">
    <div class="modal">
        <h3 id="of-titulo"></h3>
        <div id="of-tags"></div>
        <div class="offer-desc" id="of-desc"></div>
        <div class="offer-grid" id="of-grid"></div>
        <div id="of-form">
            <label class="form-label">Mensaje para el contratador (opcional)</label>
            <textarea class="textarea" id="of-mensaje" maxlength="500" placeholder="Preséntate y cuéntale por qué eres la persona indicada"></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn" data-action="cerrar" data-id="modal-oferta">Cerrar</button>
            <button class="btn btn-accent" id="of-enviar" data-action="enviar-postulacion">Enviar postulación</button>
        </div>
    </div>
</div>

<!-- MODAL CALIFICAR -->
<div class="modal-overlay" id="modal-calificar">
    <div class="modal">
        <h3>⭐ Calificar a <span id="cal-nombre"></span></h3>
        <input type="hidden" id="cal-id"><input type="hidden" id="cal-puntos" value="0">
        <div class="star-pick" id="star-pick">
            <button data-star="1">★</button><button data-star="2">★</button><button data-star="3">★</button><button data-star="4">★</button><button data-star="5">★</button>
        </div>
        <label class="form-label">Comentario (opcional)</label>
        <textarea class="textarea" id="cal-comentario" placeholder="¿Cómo fue trabajar con esta persona?"></textarea>
        <div class="modal-actions">
            <button class="btn" data-action="cerrar" data-id="modal-calificar">Cancelar</button>
            <button class="btn btn-gold" data-action="enviar-calificacion">Enviar calificación</button>
        </div>
    </div>
</div>

<script type="application/json" id="trabajador-me">@json($me)</script>
</body>
</html>