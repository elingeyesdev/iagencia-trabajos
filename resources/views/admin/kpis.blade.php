<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <script>/* Tema antes de pintar (sin parpadeo). Ver resources/js/themes.js */try{document.documentElement.setAttribute('data-theme',localStorage.getItem('redwork-theme')==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — Dashboard de KPIs</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <meta name="api-token" content="{{ $token }}">

    {{-- Mismos estilos del panel de administración + módulo de KPIs reutilizado (sin admin-dashboard.js: esta página no carga sus paneles). --}}
    @vite(['resources/css/admin-dashboard.css', 'resources/css/kpi-dashboard.css', 'resources/js/kpi-dashboard.js', 'resources/css/themes.css', 'resources/js/themes.js'])
</head>
<body class="rw-admin">

<!-- ══ TOPBAR ══ -->
<nav class="topbar">
    <div class="topbar-left">
        <div class="menu-toggle" onclick="toggleSidebar()" id="menuToggle">
            <span></span><span></span><span></span>
        </div>
        <div class="logo">
            <img src="{{ asset('images/redwork-logo.png') }}" alt="RedWork Logo" class="logo-img">
        </div>
        <span class="admin-badge">⚙️ Admin</span>
    </div>
    <div class="topbar-right">
        <button type="button" class="theme-switch" data-theme-toggle role="switch" aria-checked="false" aria-label="Tema oscuro activado. Cambiar a tema claro" title="Cambiar a tema claro"><span class="theme-switch-track" aria-hidden="true"><span class="theme-switch-thumb">🌙</span></span><span class="theme-switch-label">Tema oscuro</span></button>
        <div class="notif-btn" title="Notificaciones">🔔<span class="notif-dot"></span></div>
        <div class="dropdown" onclick="this.classList.toggle('open')">
            <div class="avatar-btn">AD</div>
            <div class="dropdown-menu">
                <a href="{{ route('profile.edit') }}" class="dropdown-item">👤 Mi Perfil</a>
                <a href="{{ route('dashboard') }}#usuarios" class="dropdown-item">⚙️ Configuración</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item danger" style="width:100%;background:none;border:none;font-family:inherit;cursor:pointer;">
                        🚪 Cerrar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<!-- ══ SIDEBAR (mismo menú del administrador; los paneles se abren en /dashboard#panel) ══ -->
<aside class="sidebar" id="sidebar">

    <!-- 1. Acceso y Seguridad -->
    <div class="nav-group" id="group-seguridad">
        <div class="nav-group-header" onclick="toggleGroup('seguridad')">
            <div class="nav-group-title"><span class="nav-group-icon">🔐</span> Acceso y Seguridad</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item" href="{{ route('dashboard') }}"><span class="nav-item-icon">🏠</span> Inicio</a>
            <a class="nav-item" href="{{ route('dashboard') }}#usuarios"><span class="nav-item-icon">👥</span> Administrar Usuario</a>
            <a class="nav-item" href="{{ route('dashboard') }}#roles"><span class="nav-item-icon">🛡️</span> Administrar Rol</a>
            <a class="nav-item" href="{{ route('dashboard') }}#asignar-rol"><span class="nav-item-icon">🔗</span> Asignar Rol</a>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- 2. Parametrización -->
    <div class="nav-group" id="group-param">
        <div class="nav-group-header" onclick="toggleGroup('param')">
            <div class="nav-group-title"><span class="nav-group-icon">⚙️</span> Parametrización</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item" href="{{ route('dashboard') }}#categorias"><span class="nav-item-icon">🏢</span> Categorías</a>
            <a class="nav-item" href="{{ route('dashboard') }}#publicaciones"><span class="nav-item-icon">💼</span> Publicaciones</a>
            <a class="nav-item" href="{{ route('dashboard') }}#postulantes"><span class="nav-item-icon">🧰</span> Postulantes</a>
            <a class="nav-item" href="{{ route('dashboard') }}#calificaciones-trans"><span class="nav-item-icon">⭐</span> Calificaciones</a>
            <a class="nav-item" href="{{ route('dashboard') }}#comision"><span class="nav-item-icon">💲</span> Comisión</a>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- 3. Transaccional -->
    <div class="nav-group" id="group-trans">
        <div class="nav-group-header" onclick="toggleGroup('trans')">
            <div class="nav-group-title"><span class="nav-group-icon">🔄</span> Transaccional</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item" href="{{ route('dashboard') }}#postulaciones-trans"><span class="nav-item-icon">📝</span> Postulaciones</a>
            <a class="nav-item" href="{{ route('dashboard') }}#contrataciones-trans"><span class="nav-item-icon">🤝</span> Contrataciones</a>
            <a class="nav-item" href="{{ route('dashboard') }}#pagos-trans"><span class="nav-item-icon">💳</span> Pagos</a>
        </div>
    </div>

    @if (\Illuminate\Support\Facades\Route::has('reportes.index'))
    <div class="sidebar-divider"></div>

    <!-- 4. Reportes -->
    <div class="nav-group" id="group-reportes">
        <div class="nav-group-header" onclick="toggleGroup('reportes')">
            <div class="nav-group-title"><span class="nav-group-icon">📄</span> Reportes</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item" href="{{ route('reportes.index') }}"><span class="nav-item-icon">🖨️</span> Reportes</a>
        </div>
    </div>
    @endif

    <div class="sidebar-divider"></div>

    <!-- 5. Business Intelligence -->
    <div class="nav-group open" id="group-bi">
        <div class="nav-group-header" onclick="toggleGroup('bi')">
            <div class="nav-group-title"><span class="nav-group-icon">📊</span> Business Intelligence</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item active" href="{{ route('admin.kpis') }}" aria-current="page"><span class="nav-item-icon">📈</span> Dashboard de KPIs</a>
        </div>
    </div>

    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout">🚪 Cerrar Sesión</button>
        </form>
    </div>
</aside>

<!-- ══ MAIN CONTENT ══ -->
<div class="main" id="main">
    <div class="content">
        <div class="panel active" id="panel-kpis">
            <div class="page-header">
                <div>
                    <div class="page-title">📊 Dashboard de KPIs</div>
                    <div class="page-breadcrumb">Admin / <span>Business Intelligence</span> / Dashboard de KPIs</div>
                </div>
            </div>

            <!-- Sistema de KPIs (Incremento 5): GET /api/admin/kpis/indicadores — resources/js/kpi-dashboard.js -->
            <section class="kpi-bi" id="kpi-admin" data-kpi-dashboard data-alcance="global"
                     data-endpoint="/api/admin/kpis/indicadores" data-categorias="/api/categorias"></section>
        </div>
    </div>
</div>

<script>
    // Solo comportamiento del menú (mismo que en el inicio del administrador).
    // En pantallas ≤ 900px el CSS muestra el menú con .mobile-open; en escritorio se usa .collapsed.
    function toggleSidebar() {
        if (window.matchMedia('(max-width: 900px)').matches) {
            document.getElementById('sidebar').classList.toggle('mobile-open');
            return;
        }
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('main').classList.toggle('expanded');
    }
    function toggleGroup(id) {
        document.getElementById('group-' + id).classList.toggle('open');
    }
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.dropdown.open').forEach(function (d) {
            if (!d.contains(e.target)) d.classList.remove('open');
        });
    });
</script>
</body>
</html>
