<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — Administrador</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #060d14;
            --bg-card: rgba(12, 20, 32, 0.92);
            --primary: #FF5A5F;
            --accent: #2DD4BF;
            --gold: #F59E0B;
            --success: #4ADE80;
            --danger: #ef4444;
            --text-main: #F8FAFC;
            --text-soft: #94A3B8;
            --text-muted: #64748B;
            --border: rgba(255,255,255,0.06);
            --shadow-soft: 0 10px 40px rgba(0,0,0,0.4);
            --sidebar-w: 260px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ══ TOPBAR ══ */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            height: 62px; padding: 0 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(6,13,20,.9); backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(0,0,0,.4);
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .menu-toggle {
            width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border);
            background: rgba(255,255,255,.04); cursor: pointer; display: flex;
            flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            transition: .2s;
        }
        .menu-toggle:hover { background: rgba(255,255,255,.08); }
        .menu-toggle span { width: 16px; height: 2px; background: var(--text-soft); border-radius: 2px; transition: .3s; }
        .logo{
        display:flex;
        align-items:center;
        text-decoration:none;
        }
        .logo-img{
        height:50px;
        width:auto;
        object-fit:contain;
        
        transition:transform .3s ease, filter .3s ease;
        filter:drop-shadow(0 0 12px rgba(255,0,0,.25));
        }
        .admin-badge {
            font-family: 'Space Mono', monospace; font-size: .58rem; letter-spacing: .12em; text-transform: uppercase;
            background: linear-gradient(135deg, rgba(245,158,11,.15), rgba(255,90,95,.1));
            color: var(--gold); border: 1px solid rgba(245,158,11,.25);
            padding: .3rem .8rem; border-radius: 20px;
        }
        .topbar-right { display: flex; align-items: center; gap: .8rem; }
        .notif-btn {
            width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border);
            background: rgba(255,255,255,.04); cursor: pointer; display: flex;
            align-items: center; justify-content: center; font-size: 1rem; position: relative; transition: .2s;
        }
        .notif-btn:hover { background: rgba(255,255,255,.08); }
        .notif-dot {
            position: absolute; top: 6px; right: 6px; width: 7px; height: 7px;
            border-radius: 50%; background: var(--primary); border: 1px solid var(--bg-main);
        }
        .avatar-btn {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), #ef4444);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .82rem; cursor: pointer; transition: .25s;
            border: 2px solid rgba(245,158,11,.3); position: relative;
        }
        .avatar-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(245,158,11,.25); }
        .dropdown { position: relative; }
        .dropdown-menu {
            display: none; position: absolute; right: 0; top: calc(100% + 10px);
            min-width: 180px; background: rgba(10,18,28,.95); backdrop-filter: blur(16px);
            border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-soft);
            z-index: 999;
        }
        .dropdown:hover .dropdown-menu, .dropdown.open .dropdown-menu { display: block; }
        .dropdown-item {
            display: flex; align-items: center; gap: .7rem; padding: .8rem 1rem;
            color: var(--text-soft); text-decoration: none; font-size: .83rem;
            border-bottom: 1px solid rgba(255,255,255,.03); transition: .15s; cursor: pointer;
        }
        .dropdown-item:hover { background: rgba(255,255,255,.04); color: var(--text-main); }
        .dropdown-item.danger:hover { background: rgba(239,68,68,.1); color: #ff7070; }

        /* ══ SIDEBAR ══ */
        .sidebar {
            position: fixed; top: 62px; left: 0; bottom: 0; z-index: 150;
            width: var(--sidebar-w);
            background: rgba(6,12,20,.95); backdrop-filter: blur(20px);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            transform: translateX(0); transition: transform .3s ease;
            overflow-y: auto;
        }
        .sidebar.collapsed { transform: translateX(calc(-1 * var(--sidebar-w))); }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 4px; }

        .nav-group { padding: .5rem .75rem; }
        .nav-group-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .6rem .5rem; cursor: pointer; border-radius: 8px;
            transition: .2s; user-select: none; margin-bottom: 2px;
        }
        .nav-group-header:hover { background: rgba(255,255,255,.03); }
        .nav-group-title {
            display: flex; align-items: center; gap: .6rem;
            font-family: 'Space Mono', monospace; font-size: .62rem; letter-spacing: .15em;
            text-transform: uppercase; color: var(--text-muted); font-weight: 700;
        }
        .nav-group-icon { font-size: .9rem; }
        .nav-group-arrow {
            font-size: .7rem; color: var(--text-muted); transition: transform .25s;
        }
        .nav-group.open .nav-group-arrow { transform: rotate(90deg); }
        .nav-group-items { display: none; padding-left: .5rem; }
        .nav-group.open .nav-group-items { display: block; }

        .nav-item {
            display: flex; align-items: center; gap: .65rem;
            padding: .6rem .75rem; border-radius: 8px; margin-bottom: 1px;
            color: var(--text-soft); text-decoration: none; font-size: .84rem;
            transition: .2s; cursor: pointer;
        }
        .nav-item:hover { background: rgba(255,255,255,.04); color: var(--text-main); transform: translateX(3px); }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(255,90,95,.15), transparent);
            color: white; border-left: 2px solid var(--primary);
        }
        .nav-item-icon { font-size: .9rem; width: 18px; text-align: center; }

        .sidebar-divider { height: 1px; background: var(--border); margin: .5rem .75rem; }

        .sidebar-footer {
            margin-top: auto; padding: .75rem; border-top: 1px solid var(--border);
        }
        .btn-logout {
            width: 100%; display: flex; align-items: center; gap: .6rem; padding: .7rem .9rem;
            background: rgba(239,68,68,.06); border: 1px solid rgba(239,68,68,.15); border-radius: 10px;
            color: #ff7070; cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: .84rem;
            transition: .2s;
        }
        .btn-logout:hover { background: rgba(239,68,68,.15); color: white; }

        /* ══ MAIN ══ */
        .main {
            margin-left: var(--sidebar-w); padding-top: 62px;
            min-height: 100vh; transition: margin-left .3s ease;
        }
        .main.expanded { margin-left: 0; }
        .content { padding: 1.8rem 2rem; }

        /* ══ PAGE HEADER ══ */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .page-title {
            font-family: 'Bebas Neue', cursive; font-size: 2rem; letter-spacing: .06em;
            color: white; display: flex; align-items: center; gap: .5rem;
        }
        .page-breadcrumb {
            font-family: 'Space Mono', monospace; font-size: .62rem; letter-spacing: .1em;
            color: var(--text-muted); text-transform: uppercase; margin-top: .2rem;
        }
        .page-breadcrumb span { color: var(--primary); }

        /* ══ STATS OVERVIEW ══ */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.8rem;
        }
        .stat-card {
            position: relative; overflow: hidden; background: var(--bg-card);
            border: 1px solid var(--border); border-radius: 16px; padding: 1.2rem 1.3rem;
            backdrop-filter: blur(14px); transition: .25s;
        }
        .stat-card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,.1); }
        .stat-card::after {
            content: ''; position: absolute; left: 0; bottom: 0; right: 0; height: 2px;
            background: var(--bar-color);
        }
        .stat-label {
            font-family: 'Space Mono', monospace; font-size: .58rem; letter-spacing: .15em;
            text-transform: uppercase; color: var(--text-muted); margin-bottom: .4rem;
        }
        .stat-value { font-family: 'Bebas Neue', cursive; font-size: 2.2rem; color: white; line-height: 1; }
        .stat-delta { font-size: .72rem; color: var(--accent); margin-top: .3rem; }
        .stat-icon { position: absolute; top: 1rem; right: 1rem; opacity: .15; font-size: 1.8rem; }

        /* ══ PANELS ══ */
        .panel {
            display: none; animation: fadeIn .25s ease;
        }
        .panel.active { display: block; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        /* ══ TOOLBAR ══ */
        .toolbar {
            display: flex; align-items: center; gap: .75rem; margin-bottom: 1.2rem; flex-wrap: wrap;
        }
        .search-box {
            flex: 1; min-width: 200px; display: flex; align-items: center; gap: .5rem;
            background: rgba(255,255,255,.04); border: 1px solid var(--border); border-radius: 10px;
            padding: .55rem 1rem;
        }
        .search-box input {
            background: none; border: none; outline: none; color: var(--text-main);
            font-family: 'DM Sans', sans-serif; font-size: .85rem; width: 100%;
        }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-icon { color: var(--text-muted); font-size: .9rem; }

        .btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .55rem 1.1rem; border-radius: 10px; font-size: .82rem; font-weight: 600;
            cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; transition: .2s; text-decoration: none;
        }
        .btn-accent { background: linear-gradient(135deg, var(--accent), #0ea5e9); color: #060d14; }
        .btn-accent:hover { box-shadow: 0 6px 18px rgba(45,212,191,.3); transform: translateY(-1px); }
        .btn-red { background: linear-gradient(135deg, var(--primary), #ff7878); color: white; }
        .btn-red:hover { box-shadow: 0 6px 18px rgba(255,90,95,.3); transform: translateY(-1px); }
        .btn-ghost {
            background: rgba(255,255,255,.04); border: 1px solid var(--border); color: var(--text-soft);
        }
        .btn-ghost:hover { background: rgba(255,255,255,.08); color: var(--text-main); }

        /* ══ TABLE ══ */
        .table-wrap {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
            overflow: hidden; box-shadow: var(--shadow-soft);
        }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: rgba(255,255,255,.03); border-bottom: 1px solid var(--border); }
        th {
            padding: .85rem 1rem; text-align: left;
            font-family: 'Space Mono', monospace; font-size: .6rem; letter-spacing: .15em;
            text-transform: uppercase; color: var(--text-muted); font-weight: 700;
        }
        td { padding: .85rem 1rem; font-size: .85rem; color: var(--text-soft); border-bottom: 1px solid rgba(255,255,255,.03); }
        tbody tr { transition: .15s; }
        tbody tr:hover { background: rgba(255,255,255,.02); }
        tbody tr:last-child td { border-bottom: none; }
        td.name { color: var(--text-main); font-weight: 600; }

        .chip {
            display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px;
            font-size: .68rem; font-weight: 700; font-family: 'Space Mono', monospace;
        }
        .chip-green { background: rgba(74,222,128,.12); color: var(--success); }
        .chip-red { background: rgba(239,68,68,.12); color: #ff7070; }
        .chip-yellow { background: rgba(245,158,11,.12); color: var(--gold); }
        .chip-blue { background: rgba(45,212,191,.12); color: var(--accent); }
        .chip-gray { background: rgba(255,255,255,.06); color: var(--text-muted); }

        .action-btns { display: flex; gap: .4rem; }
        .icon-btn {
            width: 30px; height: 30px; border-radius: 7px; border: 1px solid var(--border);
            background: rgba(255,255,255,.04); cursor: pointer; display: flex;
            align-items: center; justify-content: center; font-size: .85rem; transition: .15s;
        }
        .icon-btn:hover { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.12); }
        .icon-btn.del:hover { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.25); }

        /* ══ MODAL ══ */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 500;
            background: rgba(0,0,0,.7); backdrop-filter: blur(6px);
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: rgba(10,18,28,.98); border: 1px solid var(--border); border-radius: 20px;
            padding: 2rem; width: 100%; max-width: 480px; box-shadow: var(--shadow-soft);
            animation: fadeIn .25s ease;
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;
        }
        .modal-title { font-family: 'Bebas Neue', cursive; font-size: 1.6rem; letter-spacing: .06em; color: white; }
        .modal-close { cursor: pointer; color: var(--text-muted); font-size: 1.2rem; transition: .2s; }
        .modal-close:hover { color: var(--primary); }
        .form-group { margin-bottom: 1rem; }
        .form-label {
            display: block; font-family: 'Space Mono', monospace; font-size: .62rem;
            letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: .4rem;
        }
        .form-input {
            width: 100%; padding: .65rem 1rem; background: rgba(255,255,255,.04);
            border: 1px solid var(--border); border-radius: 10px; color: var(--text-main);
            font-family: 'DM Sans', sans-serif; font-size: .88rem; outline: none; transition: .2s;
        }
        .form-input:focus { border-color: rgba(45,212,191,.4); background: rgba(255,255,255,.06); }
        .form-select {
            width: 100%; padding: .65rem 1rem; background: rgba(255,255,255,.04);
            border: 1px solid var(--border); border-radius: 10px; color: var(--text-main);
            font-family: 'DM Sans', sans-serif; font-size: .88rem; outline: none;
            appearance: none; cursor: pointer;
        }
        .form-select option { background: #0a1220; }
        .modal-footer { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.5rem; }

        /* ══ DASHBOARD HOME ══ */
        .welcome-admin {
            background: linear-gradient(135deg, rgba(245,158,11,.08), rgba(255,90,95,.05));
            border: 1px solid rgba(245,158,11,.15); border-radius: 20px;
            padding: 2rem; margin-bottom: 1.8rem; position: relative; overflow: hidden;
        }
        .welcome-admin::before {
            content: '⚙️'; position: absolute; right: 2rem; top: 50%; transform: translateY(-50%);
            font-size: 5rem; opacity: .05;
        }
        .welcome-admin-title {
            font-family: 'Bebas Neue', cursive; font-size: 2.6rem; letter-spacing: .05em;
            background: linear-gradient(90deg, white, var(--gold));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .welcome-admin-sub { color: var(--text-soft); margin-top: .3rem; }

        .activity-feed { display: flex; flex-direction: column; gap: 0; }
        .activity-row {
            display: flex; align-items: center; gap: 1rem; padding: .85rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,.03); transition: .15s;
        }
        .activity-row:last-child { border-bottom: none; }
        .activity-row:hover { background: rgba(255,255,255,.02); }
        .activity-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .activity-text { flex: 1; font-size: .83rem; color: var(--text-soft); }
        .activity-text strong { color: white; }
        .activity-time { font-family: 'Space Mono', monospace; font-size: .65rem; color: var(--text-muted); }

        /* pagination */
        .pagination { display: flex; align-items: center; gap: .4rem; padding: 1rem; justify-content: flex-end; }
        .page-btn {
            width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border);
            background: rgba(255,255,255,.04); color: var(--text-soft); cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: .82rem; transition: .15s;
        }
        .page-btn:hover, .page-btn.active { background: rgba(255,90,95,.15); border-color: rgba(255,90,95,.3); color: white; }

        /* responsive */
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .sidebar { transform: translateX(calc(-1 * var(--sidebar-w))); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main { margin-left: 0; }
        }
        .chip-accent { background: rgba(45,212,191,.12); color: var(--accent); }
    </style>
    <meta name="api-token" content="{{ $token }}">
</head>
<body>

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
        <div class="notif-btn" title="Notificaciones">🔔<span class="notif-dot"></span></div>
        <div class="dropdown" onclick="this.classList.toggle('open')">
            <div class="avatar-btn">AD</div>
            <div class="dropdown-menu">
                <a href="{{ route('profile.edit') }}" class="dropdown-item">👤 Mi Perfil</a>
                <a href="#" class="dropdown-item" onclick="showPanel('usuarios')">⚙️ Configuración</a>
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

<aside class="sidebar" id="sidebar">

    <!-- 1. Acceso y Seguridad -->
    <div class="nav-group open" id="group-seguridad">
        <div class="nav-group-header" onclick="toggleGroup('seguridad')">
            <div class="nav-group-title"><span class="nav-group-icon">🔐</span> Acceso y Seguridad</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item active" onclick="showPanel('home')" href="#">
                <span class="nav-item-icon">🏠</span> Inicio
            </a>
            <a class="nav-item" onclick="showPanel('usuarios')" href="#">
    <span class="nav-item-icon">👥</span> Administrar Usuario
</a>

<a class="nav-item" onclick="showPanel('roles')" href="#">
    <span class="nav-item-icon">🛡️</span> Administrar Rol
</a>

<a class="nav-item" onclick="showPanel('asignar-rol')" href="#">
    <span class="nav-item-icon">🔗</span> Administrar Asignación de Rol
</a>
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
    <a class="nav-item" onclick="showPanel('categorias')" href="#">
    <span class="nav-item-icon">🏢</span> Administrar Categorías
</a>
<a class="nav-item" onclick="showPanel('publicaciones')" href="#">
    <span class="nav-item-icon">💼</span> Administrar Publicaciones
</a>
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
            <a class="nav-item" href="#"><span class="nav-item-icon">💰</span> Pagos</a>
            <a class="nav-item" href="#"><span class="nav-item-icon">🤝</span> Contrataciones</a>
            <a class="nav-item" href="#"><span class="nav-item-icon">📝</span> Postulaciones</a>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- 4. Reportes -->
    <div class="nav-group" id="group-report">
        <div class="nav-group-header" onclick="toggleGroup('report')">
            <div class="nav-group-title"><span class="nav-group-icon">📊</span> Reportes</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item" href="#"><span class="nav-item-icon">👤</span> Usuarios</a>
            <a class="nav-item" href="#"><span class="nav-item-icon">📌</span> Publicaciones</a>
            <a class="nav-item" href="#"><span class="nav-item-icon">🏷️</span> Categorías</a>
            <a class="nav-item" href="#"><span class="nav-item-icon">📈</span> Actividad</a>
            <a class="nav-item" href="#"><span class="nav-item-icon">💵</span> Ingresos/Comisión</a>
        </div>
    </div>

    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout">🚪 Cerrar Sesión</button>
        </form>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main" id="main">
<div class="content">

    <!-- ════ PANEL: HOME ════ -->
    <div class="panel active" id="panel-home">
        <div class="welcome-admin">
            <div class="welcome-admin-title">Panel de Administración</div>
            <div class="welcome-admin-sub">Control total del sistema RED-WORK — {{ now()->format('d/m/Y') }}</div>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="--bar-color:#FF5A5F;">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Usuarios</div>
                <div class="stat-value">{{ \App\Models\User::count() }}</div>
                <div class="stat-delta">↑ registros activos</div>
            </div>
            <div class="stat-card" style="--bar-color:#2DD4BF;">
                <div class="stat-icon">📋</div>
                <div class="stat-label">Publicaciones</div>
                <div class="stat-value">{{ \DB::table('publicaciones')->count() }}</div>
                <div class="stat-delta">En el sistema</div>
            </div>
            <div class="stat-card" style="--bar-color:#4ADE80;">
                <div class="stat-icon">🛡️</div>
                <div class="stat-label">Roles</div>
                <div class="stat-value">3</div>
                <div class="stat-delta">trabajador · contratador · admin</div>
            </div>
            <div class="stat-card" style="--bar-color:#F59E0B;">
                <div class="stat-icon">🔐</div>
                <div class="stat-label">Sesiones Hoy</div>
                <div class="stat-value">--</div>
                <div class="stat-delta">Actividad del día</div>
            </div>
        </div>

        <div class="table-wrap">
            <div style="padding:1rem 1.2rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                <span style="font-family:'Bebas Neue',cursive;font-size:1.2rem;letter-spacing:.06em;">Actividad Reciente</span>
            </div>
            <div class="activity-feed">
                <div class="activity-row">
                    <div class="activity-dot" style="background:#4ADE80;box-shadow:0 0 8px rgba(74,222,128,.5);"></div>
                    <div class="activity-text">Nuevo usuario registrado: <strong>empresa de gatos</strong> como contratador</div>
                    <div class="activity-time">hace 1h</div>
                </div>
                <div class="activity-row">
                    <div class="activity-dot" style="background:#2DD4BF;"></div>
                    <div class="activity-text">Nueva publicación creada en categoría <strong>Electricidad</strong></div>
                    <div class="activity-time">hace 3h</div>
                </div>
                <div class="activity-row">
                    <div class="activity-dot" style="background:#F59E0B;"></div>
                    <div class="activity-text">Rol <strong>administrador</strong> asignado a usuario via consola</div>
                    <div class="activity-time">hace 1d</div>
                </div>
                <div class="activity-row">
                    <div class="activity-dot" style="background:#FF5A5F;"></div>
                    <div class="activity-text">Sistema iniciado correctamente — Laravel <strong>13</strong></div>
                    <div class="activity-time">hace 2d</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════ PANEL: USUARIOS ════ -->
    <div class="panel" id="panel-usuarios">
        <div class="page-header">
            <div>
                <div class="page-title">👥 Usuarios</div>
                <div class="page-breadcrumb">Admin / <span>Acceso y Seguridad</span> / Usuarios</div>
            </div>
            <button class="btn btn-accent" onclick="openModal('modal-usuario')">+ Nuevo Usuario</button>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" placeholder="Buscar por nombre, email o cédula..." id="search-usuarios" oninput="filterTable('tabla-usuarios', this.value)">
            </div>
            <button class="btn btn-ghost">⬇️ Exportar</button>
            <select class="form-select" style="width:auto;padding:.5rem .8rem;" onchange="filterByRol(this.value)">
                <option value="">Todos los roles</option>
                <option value="trabajador">Trabajador</option>
                <option value="contratador">Contratador</option>
                <option value="administrador">Administrador</option>
            </select>
        </div>

        <div class="table-wrap">
            <table id="tabla-usuarios">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Cédula</th>
                        <th>Ciudad</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(\App\Models\User::with('roles')->get() as $u)
                    <tr data-rol="{{ $u->roles->first()?->name ?? '' }}">
                        <td>{{ $u->id }}</td>
                        <td class="name">{{ $u->nombre }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->cedula }}</td>
                        <td>{{ $u->ciudad ?? '—' }}</td>
                        <td>
                            @php $rol = $u->roles->first()?->name ?? null; @endphp
@if($rol)
    @php
        $chipColor = match($rol) {
            'administrador' => 'chip-yellow',
            'contratador'   => 'chip-blue',
            'trabajador'    => 'chip-green',
            default         => 'chip-accent'
        };
        $chipIcon = match($rol) {
            'administrador' => '⚙️',
            'contratador'   => '💼',
            'trabajador'    => '🔧',
            default         => '🏷️'
        };
    @endphp
    <span class="chip {{ $chipColor }}">{{ $chipIcon }} {{ ucfirst($rol) }}</span>
@else
    <span class="chip chip-gray">Sin rol</span>
@endif
                        </td>
                        <td>
                            @if($u->estado)
                                <span class="chip chip-green">Activo</span>
                            @else
                                <span class="chip chip-red">Inactivo</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="icon-btn" title="Ver" onclick="verUsuario({{ $u->id }})">👁️</button>
                                <button class="icon-btn" title="Editar" onclick="editarUsuario({{ $u->id }}, '{{ $u->nombre }}', '{{ $u->email }}', '{{ $u->ciudad }}', '{{ $u->estado }}')">✏️</button>
                                <button class="icon-btn del" title="Eliminar" onclick="confirmarEliminar({{ $u->id }}, '{{ $u->nombre }}', 'usuario')">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">
                <button class="page-btn">‹</button>
                <button class="page-btn active">1</button>
                <button class="page-btn">2</button>
                <button class="page-btn">›</button>
            </div>
        </div>
    </div>

<!-- ════ PANEL: ROLES ════ -->
<div class="panel" id="panel-roles">
    <div class="page-header">
        <div>
            <div class="page-title">🛡️ Roles</div>
            <div class="page-breadcrumb">Admin / <span>Acceso y Seguridad</span> / Roles</div>
        </div>
        <button class="btn btn-accent" onclick="abrirModalRol()">+ Nuevo Rol</button>
    </div>

    <div class="toolbar">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" placeholder="Buscar rol..." oninput="filterTable('tabla-roles', this.value)">
        </div>
    </div>

    <div class="table-wrap">
        <table id="tabla-roles">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre del Rol</th>
                    <th>Guard</th>
                    <th>Usuarios Asignados</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach(\Spatie\Permission\Models\Role::withCount('users')->get() as $r)
                <tr data-id="{{ $r->id }}" data-name="{{ $r->name }}">
                    <td>{{ $r->id }}</td>
                    <td class="name">
                        @if($r->name === 'administrador') <span class="chip chip-yellow" style="margin-right:.4rem;">⚙️</span>
                        @elseif($r->name === 'contratador') <span class="chip chip-blue" style="margin-right:.4rem;">💼</span>
                        @else <span class="chip chip-green" style="margin-right:.4rem;">🔧</span>
                        @endif
                        {{ ucfirst($r->name) }}
                    </td>
                    <td><span class="chip chip-gray">{{ $r->guard_name }}</span></td>
                    <td>
                        <span style="font-family:'Bebas Neue',cursive;font-size:1.1rem;color:var(--accent);">{{ $r->users_count }}</span>
                        <span style="font-size:.75rem;color:var(--text-muted);"> usuarios</span>
                    </td>
                    <td>{{ $r->created_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        <div class="action-btns">
                            <button class="icon-btn" title="Ver usuarios" onclick="verUsuariosRol('{{ $r->name }}')">👥</button>
                            <button class="icon-btn edit" title="Editar" onclick="editarRol({{ $r->id }}, '{{ $r->name }}')">✏️</button>
                            <button class="icon-btn del" title="Eliminar" onclick="confirmarEliminarRol({{ $r->id }}, '{{ $r->name }}')">🗑️</button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

    <!-- ════ PANEL: ASIGNAR ROL ════ -->
    <div class="panel" id="panel-asignar-rol">
        <div class="page-header">
            <div>
                <div class="page-title">🔗 Asignar Rol</div>
                <div class="page-breadcrumb">Admin / <span>Acceso y Seguridad</span> / Asignar Rol</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

            <!-- Formulario asignar -->
            <div class="table-wrap" style="padding:1.5rem;">
                <div style="font-family:'Bebas Neue',cursive;font-size:1.3rem;letter-spacing:.06em;margin-bottom:1.2rem;">Asignar Rol a Usuario</div>
                <form onsubmit="asignarRolFetch(event)">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Seleccionar Usuario</label>
                        <select class="form-select">
                            <option value="">— Seleccione usuario —</option>
                            @foreach(\App\Models\User::orderBy('nombre')->get() as $u)
                            <option value="{{ $u->id }}">{{ $u->nombre }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Seleccionar Rol</label>
                        <select class="form-select">
                            <option value="">— Seleccione rol —</option>
                            @foreach(\Spatie\Permission\Models\Role::all() as $r)
                            <option value="{{ $r->name }}">{{ ucfirst($r->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="margin-top:1rem;display:flex;gap:.7rem;">
                        <button type="submit" class="btn btn-accent" style="flex:1;">✅ Asignar Rol</button>
                        <button type="button" class="btn btn-ghost">🔄 Quitar Rol</button>
                    </div>
                </form>
            </div>

            <!-- Lista usuarios con roles -->
            <div class="table-wrap" style="overflow:hidden;">
                <div style="padding:.9rem 1.1rem;border-bottom:1px solid var(--border);font-family:'Bebas Neue',cursive;font-size:1.1rem;letter-spacing:.06em;">
                    Usuarios y sus Roles
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol Actual</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(\App\Models\User::with('roles')->get() as $u)
                        <tr>
                            <td class="name" style="font-size:.82rem;">{{ $u->nombre }}</td>
                            <td>
                                @php 
                            $r = $u->roles->first()?->name ?? null;
                            $chipColor = match($r) {
                                'administrador' => 'chip-yellow',
                                'contratador'   => 'chip-blue',
                                'trabajador'    => 'chip-green',
                                null            => 'chip-gray',
                                default         => 'chip-accent'
                            };
                            $chipIcon = match($r) {
                                'administrador' => '⚙️',
                                'contratador'   => '💼',
                                'trabajador'    => '🔧',
                                null            => '',
                                default         => '🏷️'
                            };
                        @endphp
<span class="chip {{ $chipColor }}">{{ $chipIcon }} {{ $r ? ucfirst($r) : 'Sin rol' }}</span>
                            </td>
                            <td>
                                <button class="icon-btn" title="Cambiar rol" onclick="editarRolUsuario({{ $u->id }}, '{{ $u->nombre }}')">✏️</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- ════ PANEL: CATEGORÍAS ════ -->
<div class="panel" id="panel-categorias">
    <div class="page-header">
        <div>
            <div class="page-title">🏢 Categorías</div>
            <div class="page-breadcrumb">Admin / <span>Parametrización</span> / Categorías</div>
        </div>
        <button class="btn btn-accent" onclick="openModal('modal-categoria')">+ Nueva Categoría</button>
    </div>
    <div class="toolbar">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" placeholder="Buscar categoría..." oninput="filterTable('tabla-categorias', this.value)">
        </div>
    </div>
    <div class="table-wrap">
        <table id="tabla-categorias">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-categorias">
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ════ PANEL: PUBLICACIONES ════ -->
<div class="panel" id="panel-publicaciones">
    <div class="page-header">
        <div>
            <div class="page-title">💼 Publicaciones</div>
            <div class="page-breadcrumb">Admin / <span>Parametrización</span> / Publicaciones</div>
        </div>
        <button class="btn btn-accent" onclick="openModal('modal-publicacion')">+ Nueva Publicación</button>
    </div>
    <div class="toolbar">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" placeholder="Buscar publicación..." oninput="filterTable('tabla-publicaciones', this.value)">
        </div>
    </div>
    <div class="table-wrap">
        <table id="tabla-publicaciones">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Pago</th>
                    <th>Lugar</th>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-publicaciones">
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>
</div><!-- /content -->
</div><!-- /main -->

<!-- ══ MODAL: NUEVO/EDITAR USUARIO ══ -->
<div class="modal-overlay" id="modal-usuario">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modal-usuario-title">Nuevo Usuario</div>
            <span class="modal-close" onclick="closeModal('modal-usuario')">✕</span>
        </div>
        <form id="form-usuario">
            <input type="hidden" id="usuario-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
                <div class="form-group">
                    <label class="form-label">Nombre</label>
                    <input class="form-input" type="text" id="f-nombre" placeholder="Nombre completo">
                </div>
                <div class="form-group">
                    <label class="form-label">Cédula</label>
                    <input class="form-input" type="number" id="f-cedula" placeholder="12345678">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-input" type="email" id="f-email" placeholder="correo@ejemplo.com">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
                <div class="form-group">
                    <label class="form-label">Ciudad</label>
                    <input class="form-input" type="text" id="f-ciudad" placeholder="Santa Cruz">
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="f-estado">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </  select>
                </div>
            </div>
            <div class="form-group" id="grupo-password">
                <label class="form-label">Contraseña</label>
                <input class="form-input" type="password" id="f-password" placeholder="Mínimo 8 caracteres">
            </div>
            <div class="form-group">
                <label class="form-label">Rol</label>
                <select class="form-select" id="f-rol">
    <option value="">Cargando roles...</option>
</select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-usuario')">Cancelar</button>
                <button type="button" class="btn btn-accent" onclick="guardarUsuario()">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL: CONFIRMAR ELIMINAR ══ -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Confirmar</div>
            <span class="modal-close" onclick="closeModal('modal-eliminar')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">
            ¿Seguro que deseas eliminar a <strong id="eliminar-nombre" style="color:white;"></strong>? Esta acción no se puede deshacer.
        </p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-eliminar')">Cancelar</button>
            <button class="btn btn-red" onclick="ejecutarEliminar()">🗑️ Eliminar</button>
        </div>
    </div>
</div>

<!-- ══ MODAL: VER USUARIO ══ -->
<div class="modal-overlay" id="modal-ver-usuario">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">👤 Detalle Usuario</div>
            <span class="modal-close" onclick="closeModal('modal-ver-usuario')">✕</span>
        </div>
        <div id="detalle-usuario-content" style="color:var(--text-soft);font-size:.88rem;">
            Cargando...
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-ver-usuario')">Cerrar</button>
        </div>
    </div>
</div>
<!-- MODAL NUEVO/EDITAR ROL -->
<div class="modal-overlay" id="modal-rol">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <div class="modal-title" id="modal-rol-title">Nuevo Rol</div>
            <span class="modal-close" onclick="closeModal('modal-rol')">✕</span>
        </div>
        <input type="hidden" id="rol-id">
        <div class="form-group">
            <label class="form-label">Nombre del Rol</label>
            <input class="form-input" type="text" id="rol-nombre" placeholder="ej: supervisor">
            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.4rem;">Se guardará en minúsculas.</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-rol')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarRol()">Guardar</button>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR ELIMINAR ROL -->
<div class="modal-overlay" id="modal-eliminar-rol">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Eliminar Rol</div>
            <span class="modal-close" onclick="closeModal('modal-eliminar-rol')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">
            ¿Eliminar el rol <strong id="eliminar-rol-nombre" style="color:white;"></strong>? Los usuarios con este rol quedarán sin rol asignado.
        </p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-eliminar-rol')">Cancelar</button>
            <button class="btn btn-red" onclick="ejecutarEliminarRol()">🗑️ Eliminar</button>
        </div>
    </div>
</div>
<!-- MODAL CATEGORIA -->
<div class="modal-overlay" id="modal-categoria">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modal-categoria-title">Nueva Categoría</div>
            <span class="modal-close" onclick="closeModal('modal-categoria')">✕</span>
        </div>
        <input type="hidden" id="categoria-id">
        <div class="form-group">
            <label class="form-label">Nombre</label>
            <input class="form-input" type="text" id="cat-nombre" placeholder="ej: Electricidad">
        </div>
        <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea class="form-input" id="cat-descripcion" placeholder="Descripción de la categoría..." rows="3" style="resize:vertical;"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Estado</label>
            <select class="form-select" id="cat-estado">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-categoria')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarCategoria()">Guardar</button>
        </div>
    </div>
</div>

<!-- MODAL PUBLICACION -->
<div class="modal-overlay" id="modal-publicacion">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <div class="modal-title" id="modal-publicacion-title">Nueva Publicación</div>
            <span class="modal-close" onclick="closeModal('modal-publicacion')">✕</span>
        </div>
        <input type="hidden" id="pub-id">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
            <div class="form-group">
                <label class="form-label">Título</label>
                <input class="form-input" type="text" id="pub-titulo" placeholder="Título del trabajo">
            </div>
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select class="form-select" id="pub-tipo">
                    <option value="recurrente">Recurrente</option>
                    <option value="temporal">Temporal</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea class="form-input" id="pub-descripcion" placeholder="Describe el trabajo..." rows="3" style="resize:vertical;"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
            <div class="form-group">
                <label class="form-label">Pago (Bs)</label>
                <input class="form-input" type="number" id="pub-pago" placeholder="350">
            </div>
            <div class="form-group">
                <label class="form-label">Lugar</label>
                <input class="form-input" type="text" id="pub-lugar" placeholder="Santa Cruz">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Categoría</label>
            <select class="form-select" id="pub-categoria">
                <option value="">Cargando...</option>
            </select>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-publicacion')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarPublicacion()">Guardar</button>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR ELIMINAR CATEGORIA/PUBLICACION -->
<div class="modal-overlay" id="modal-eliminar-item">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Confirmar</div>
            <span class="modal-close" onclick="closeModal('modal-eliminar-item')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">
            ¿Eliminar <strong id="eliminar-item-nombre" style="color:white;"></strong>?
        </p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-eliminar-item')">Cancelar</button>
            <button class="btn btn-red" onclick="ejecutarEliminarItem()">🗑️ Eliminar</button>
        </div>
    </div>
</div></div>
<script>
    const TOKEN = document.querySelector('meta[name="api-token"]').content;

// Cargar usuarios desde API
async function cargarUsuarios() {
    const res = await fetch('/api/usuarios', {
        headers: {
            'Authorization': 'Bearer ' + TOKEN,
            'Accept': 'application/json'
        }
    });
    const data = await res.json();
    renderTablaUsuarios(data);
}
    // ── Sidebar toggle ──
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        sidebar.classList.toggle('collapsed');
        main.classList.toggle('expanded');
    }

    // ── Grupos desplegables ──
    function toggleGroup(id) {
        const group = document.getElementById('group-' + id);
        group.classList.toggle('open');
    }

    // ── Cambiar panel activo ──
    function showPanel(name) {
        document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.getElementById('panel-' + name)?.classList.add('active');
        event?.currentTarget?.classList.add('active');
    }

    // ── Modales ──
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }
    // Cerrar modal al hacer click fuera
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('open');
        });
    });

    // ── Buscar en tabla ──
    function filterTable(tableId, query) {
        const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
        const q = query.toLowerCase();
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    // ── Filtrar por rol ──
    function filterByRol(rol) {
        const rows = document.querySelectorAll('#tabla-usuarios tbody tr');
        rows.forEach(row => {
            if (!rol || row.dataset.rol === rol) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // ── Ver usuario ──
    function verUsuario(id) {
        // En producción esto haría un fetch a /admin/usuarios/{id}
        const rows = document.querySelectorAll('#tabla-usuarios tbody tr');
        let found = null;
        rows.forEach(row => {
            if (row.cells[0].textContent == id) found = row;
        });
        if (found) {
            document.getElementById('detalle-usuario-content').innerHTML = `
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:.8rem;">
                    <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">ID</div><div style="color:white;font-weight:600;">${found.cells[0].textContent}</div></div>
                    <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Nombre</div><div style="color:white;font-weight:600;">${found.cells[1].textContent}</div></div>
                    <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Email</div><div style="color:white;">${found.cells[2].textContent}</div></div>
                    <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Cédula</div><div style="color:white;">${found.cells[3].textContent}</div></div>
                    <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Ciudad</div><div style="color:white;">${found.cells[4].textContent}</div></div>
                    <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Rol</div><div>${found.cells[5].innerHTML}</div></div>
                </div>
            `;
        }
        openModal('modal-ver-usuario');
    }

    // ── Editar usuario ──
    function editarUsuario(id, nombre, email, ciudad, estado) {
        document.getElementById('modal-usuario-title').textContent = 'Editar Usuario';
        document.getElementById('usuario-id').value = id;
        document.getElementById('f-nombre').value = nombre;
        document.getElementById('f-email').value = email;
        document.getElementById('f-ciudad').value = ciudad === '—' ? '' : ciudad;
        document.getElementById('f-estado').value = estado;
        document.getElementById('grupo-password').style.display = 'none';
        openModal('modal-usuario');
    }
    async function guardarUsuario() {
    const id = document.getElementById('usuario-id').value;
    const body = {
        nombre:   document.getElementById('f-nombre').value,
        email:    document.getElementById('f-email').value,
        cedula:   document.getElementById('f-cedula').value,
        ciudad:   document.getElementById('f-ciudad').value,
        estado:   document.getElementById('f-estado').value,
        rol:      document.getElementById('f-rol').value,
        password: document.getElementById('f-password').value,
    };

    const url    = id ? `/api/admin/usuarios/${id}` : '/api/admin/usuarios';
    const method = id ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: {
            'Authorization': 'Bearer ' + TOKEN,
            'Accept':        'application/json',
            'Content-Type':  'application/json'
        },
        body: JSON.stringify(body)
    });

    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Guardado correctamente.');
        closeModal('modal-usuario');
        setTimeout(() => location.reload(), 1500);
    } else {
        const errores = Object.values(data.errors || {}).flat().join(' | ');
        toast(errores || data.message || 'Error al guardar.', 'error');
    }
}

    // ── Confirmar eliminar ──
    let eliminarId = null, eliminarTipo = null;
    function confirmarEliminar(id, nombre, tipo) {
        eliminarId = id;
        eliminarTipo = tipo;
        document.getElementById('eliminar-nombre').textContent = nombre;
        openModal('modal-eliminar');
    }
    async function ejecutarEliminar() {
    const res = await fetch('/api/admin/usuarios/' + eliminarId, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
    });
    const data = await res.json();
    if (res.ok) { toast(data.message); closeModal('modal-eliminar'); setTimeout(() => location.reload(), 1500); }
    else { toast('Error al eliminar.', 'error'); }
}
async function cargarRoles() {
    const res = await fetch('/api/admin/roles', {
        headers: {
            'Authorization': 'Bearer ' + TOKEN,
            'Accept': 'application/json'
        }
    });
    const roles = await res.json();
    const select = document.getElementById('f-rol');
    select.innerHTML = '<option value="">Sin rol</option>';
    roles.forEach(r => {
        const chip = r.name === 'administrador' ? '⚙️' : r.name === 'contratador' ? '💼' : '🔧';
        select.innerHTML += `<option value="${r.name}">${chip} ${r.name.charAt(0).toUpperCase() + r.name.slice(1)}</option>`;
    });
}
async function asignarRolFetch(e) {
    e.preventDefault();
    const selects = e.target.querySelectorAll('select');
    const res = await fetch('/api/admin/asignar-rol', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ usuario_id: selects[0].value, rol: selects[1].value })
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message);
        setTimeout(() => location.reload(), 1500);
    } else {
        toast(data.message || 'Error.', 'error');
    }
}

function toast(msg, tipo = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:2rem;right:2rem;z-index:9999;padding:.9rem 1.4rem;border-radius:12px;font-size:.88rem;font-weight:600;backdrop-filter:blur(12px);${tipo==='success'?'background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.3);color:#4ADE80;':'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#ff7070;'}`;
    t.textContent = (tipo==='success'?'✅ ':'❌ ') + msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

    // ── Editar rol usuario ──
    function editarRolUsuario(id, nombre) {
        showPanel('asignar-rol');
    }

    // ── Ver usuarios de un rol ──
    function verUsuariosRol(rolName) {
        showPanel('usuarios');
        document.querySelector('select[onchange]').value = rolName;
        filterByRol(rolName);
    }
    // ── Roles ──
let eliminarRolId = null;

function abrirModalRol() {
    document.getElementById('modal-rol-title').textContent = 'Nuevo Rol';
    document.getElementById('rol-id').value = '';
    document.getElementById('rol-nombre').value = '';
    openModal('modal-rol');
}

function editarRol(id, nombre) {
    document.getElementById('modal-rol-title').textContent = 'Editar Rol';
    document.getElementById('rol-id').value = id;
    document.getElementById('rol-nombre').value = nombre;
    openModal('modal-rol');
}

async function guardarRol() {
    const id     = document.getElementById('rol-id').value;
    const nombre = document.getElementById('rol-nombre').value.trim();
    if (!nombre) { toast('Ingresa un nombre para el rol.', 'error'); return; }

    const url    = id ? `/api/admin/roles/${id}` : '/api/admin/roles';
    const method = id ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: {
            'Authorization': 'Bearer ' + TOKEN,
            'Accept':        'application/json',
            'Content-Type':  'application/json'
        },
        body: JSON.stringify({ name: nombre })
    });

    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Guardado.');
        closeModal('modal-rol');
        setTimeout(() => location.reload(), 1500);
    } else {
        const errores = Object.values(data.errors || {}).flat().join(' | ');
        toast(errores || data.message || 'Error.', 'error');
    }
}

function confirmarEliminarRol(id, nombre) {
    eliminarRolId = id;
    document.getElementById('eliminar-rol-nombre').textContent = nombre;
    openModal('modal-eliminar-rol');
}

async function ejecutarEliminarRol() {
    const res = await fetch('/api/admin/roles/' + eliminarRolId, {
        method: 'DELETE',
        headers: {
            'Authorization': 'Bearer ' + TOKEN,
            'Accept':        'application/json'
        }
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Rol eliminado.');
        closeModal('modal-eliminar-rol');
        setTimeout(() => location.reload(), 1500);
    } else {
        toast(data.message || 'Error al eliminar.', 'error');
    }
}

    // Dropdown topbar
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.dropdown.open').forEach(d => {
            if (!d.contains(e.target)) d.classList.remove('open');
        });
    });
// ══ CATEGORÍAS ══
let eliminarItemId = null, eliminarItemTipo = null;

async function cargarCategorias() {
    try {
        const res = await fetch('/api/admin/categorias', {
            headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
        });
        const data = await res.json();
        const tbody = document.getElementById('tbody-categorias');
        if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Sin categorías.</td></tr>';
            return;
        }
        tbody.innerHTML = data.map(c => `
            <tr>
                <td>${c.id_categoria}</td>
                <td class="name">${c.nombre_categoria}</td>
                <td style="color:var(--text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${c.descripcion ?? '—'}</td>
                <td>${c.estado ? '<span class="chip chip-green">Activo</span>' : '<span class="chip chip-red">Inactivo</span>'}</td>
                <td>
                    <div class="action-btns">
                        <button class="icon-btn" onclick="editarCategoria(${c.id_categoria},'${c.nombre_categoria?.replace(/'/g,"\\'")}','${c.descripcion?.replace(/'/g,"\\'") ?? ''}',${c.estado})">✏️</button>
                        <button class="icon-btn del" onclick="confirmarEliminarItem(${c.id_categoria},'${c.nombre_categoria}','categoria')">🗑️</button>
                    </div>
                </td>
            </tr>`).join('');
        // También llenar select de publicación
        const selCat = document.getElementById('pub-categoria');
        if (selCat) {
            selCat.innerHTML = '<option value="">— Seleccione —</option>';
            data.forEach(c => selCat.innerHTML += `<option value="${c.id_categoria}">${c.nombre_categoria}</option>`);
        }
    } catch(e) { console.log('Error categorías:', e); }
}

function editarCategoria(id, nombre, desc, estado) {
    document.getElementById('modal-categoria-title').textContent = 'Editar Categoría';
    document.getElementById('categoria-id').value = id;
    document.getElementById('cat-nombre').value = nombre;
    document.getElementById('cat-descripcion').value = desc;
    document.getElementById('cat-estado').value = estado;
    openModal('modal-categoria');
}

async function guardarCategoria() {
    const id = document.getElementById('categoria-id').value;
    const body = {
        nombre_categoria: document.getElementById('cat-nombre').value,
        descripcion:      document.getElementById('cat-descripcion').value,
        estado:           document.getElementById('cat-estado').value,
    };
    const url    = id ? `/api/admin/categorias/${id}` : '/api/admin/categorias';
    const method = id ? 'PUT' : 'POST';
    const res = await fetch(url, {
        method,
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Guardado.');
        closeModal('modal-categoria');
        document.getElementById('categoria-id').value = '';
        document.getElementById('modal-categoria-title').textContent = 'Nueva Categoría';
        cargarCategorias();
    } else {
        const errores = Object.values(data.errors || {}).flat().join(' | ');
        toast(errores || data.message || 'Error.', 'error');
    }
}

// ══ PUBLICACIONES ══
async function cargarPublicaciones() {
    try {
        const res = await fetch('/api/admin/publicaciones', {
            headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
        });
        const data = await res.json();
        const tbody = document.getElementById('tbody-publicaciones');
        if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Sin publicaciones.</td></tr>';
            return;
        }
        tbody.innerHTML = data.map(p => `
            <tr>
                <td>${p.id_labor}</td>
                <td class="name">${p.titulo}</td>
                <td><span class="chip chip-blue">${p.tipo}</span></td>
                <td style="color:var(--accent);font-family:'Bebas Neue',cursive;font-size:1.1rem;">Bs ${p.pago}</td>
                <td>${p.lugar}</td>
                <td>${p.categoria?.nombre_categoria ?? '—'}</td>
                <td>${p.estado ? '<span class="chip chip-green">Activo</span>' : '<span class="chip chip-red">Inactivo</span>'}</td>
                <td>
                    <div class="action-btns">
                        <button class="icon-btn" onclick="editarPublicacion(${JSON.stringify(p).replace(/"/g,'&quot;')})">✏️</button>
                        <button class="icon-btn del" onclick="confirmarEliminarItem(${p.id_labor},'${p.titulo?.replace(/'/g,"\\'")}','publicacion')">🗑️</button>
                    </div>
                </td>
            </tr>`).join('');
    } catch(e) { console.log('Error publicaciones:', e); }
}

function editarPublicacion(p) {
    if (typeof p === 'string') p = JSON.parse(p.replace(/&quot;/g,'"'));
    document.getElementById('modal-publicacion-title').textContent = 'Editar Publicación';
    document.getElementById('pub-id').value = p.id_labor;
    document.getElementById('pub-titulo').value = p.titulo;
    document.getElementById('pub-tipo').value = p.tipo;
    document.getElementById('pub-descripcion').value = p.descripcion;
    document.getElementById('pub-pago').value = p.pago;
    document.getElementById('pub-lugar').value = p.lugar;
    document.getElementById('pub-categoria').value = p.id_categoria;
    openModal('modal-publicacion');
}

async function guardarPublicacion() {
    const id = document.getElementById('pub-id').value;
    const body = {
        titulo:       document.getElementById('pub-titulo').value,
        tipo:         document.getElementById('pub-tipo').value,
        descripcion:  document.getElementById('pub-descripcion').value,
        pago:         document.getElementById('pub-pago').value,
        lugar:        document.getElementById('pub-lugar').value,
        id_categoria: document.getElementById('pub-categoria').value,
    };
    const url    = id ? `/api/admin/publicaciones/${id}` : '/api/admin/publicaciones';
    const method = id ? 'PUT' : 'POST';
    const res = await fetch(url, {
        method,
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Guardado.');
        closeModal('modal-publicacion');
        document.getElementById('pub-id').value = '';
        cargarPublicaciones();
    } else {
        const errores = Object.values(data.errors || {}).flat().join(' | ');
        toast(errores || data.message || 'Error.', 'error');
    }
}

function confirmarEliminarItem(id, nombre, tipo) {
    eliminarItemId = id;
    eliminarItemTipo = tipo;
    document.getElementById('eliminar-item-nombre').textContent = nombre;
    openModal('modal-eliminar-item');
}

async function ejecutarEliminarItem() {
    const url = eliminarItemTipo === 'categoria'
        ? `/api/admin/categorias/${eliminarItemId}`
        : `/api/admin/publicaciones/${eliminarItemId}`;
    const res = await fetch(url, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Eliminado.');
        closeModal('modal-eliminar-item');
        eliminarItemTipo === 'categoria' ? cargarCategorias() : cargarPublicaciones();
    } else {
        toast(data.message || 'Error.', 'error');
    }
}
    // ── Cargar roles en todos los selects dinámicamente ──
async function cargarTodosRoles() {
    try {
        const res = await fetch('/api/admin/roles', {
            headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
        });
        const roles = await res.json();

        // Select del modal usuario
        const fRol = document.getElementById('f-rol');
        if (fRol) {
            fRol.innerHTML = '<option value="">Sin rol</option>';
            roles.forEach(r => {
                const icon = r.name === 'administrador' ? '⚙️' : r.name === 'contratador' ? '💼' : '🔧';
                fRol.innerHTML += `<option value="${r.name}">${icon} ${r.name.charAt(0).toUpperCase() + r.name.slice(1)}</option>`;
            });
        }

        // Select del filtro de tabla usuarios
        const filtroRol = document.querySelector('select[onchange="filterByRol(this.value)"]');
        if (filtroRol) {
            filtroRol.innerHTML = '<option value="">Todos los roles</option>';
            roles.forEach(r => {
                filtroRol.innerHTML += `<option value="${r.name}">${r.name.charAt(0).toUpperCase() + r.name.slice(1)}</option>`;
            });
        }

        // Select de rol en panel asignar-rol (el segundo select del form)
        const selects = document.querySelectorAll('#panel-asignar-rol select');
        if (selects[1]) {
            selects[1].innerHTML = '<option value="">— Seleccione rol —</option>';
            roles.forEach(r => {
                selects[1].innerHTML += `<option value="${r.name}">${r.name.charAt(0).toUpperCase() + r.name.slice(1)}</option>`;
            });
        }
        
    } catch(e) {
        console.log('Error cargando roles:', e);
    }
}

// ── Init: todo en paralelo ──
Promise.all([
    cargarTodosRoles(),
    cargarCategorias(),
    cargarPublicaciones()
]);
</script>

</body>
</html>