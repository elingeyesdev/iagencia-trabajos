<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — Contratador</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #071018;
            --bg-card: rgba(14, 24, 38, 0.88);
            --primary: #FF5A5F;
            --primary-soft: rgba(255,90,95,0.12);
            --accent: #2DD4BF;
            --accent-soft: rgba(45,212,191,0.12);
            --success: #4ADE80;
            --warning: #F59E0B;
            --text-main: #F8FAFC;
            --text-soft: #94A3B8;
            --text-muted: #64748B;
            --border: rgba(255,255,255,0.06);
            --shadow-red: 0 10px 30px rgba(255,90,95,0.18);
            --shadow-soft: 0 10px 40px rgba(0,0,0,0.35);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(255,90,95,.08), transparent 35%),
                radial-gradient(circle at bottom right, rgba(45,212,191,.06), transparent 30%),
                var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── TOPBAR ── */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            height: 68px; padding: 0 2rem;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(7,16,24,.72);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 8px 30px rgba(0,0,0,.25);
        }

        .logo {
            font-family: 'Bebas Neue', cursive; font-size: 2rem; letter-spacing: 0.1em;
            background: linear-gradient(90deg, #ff4d6d 0%, #ff5a5f 45%, #ff7a7f 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            display: flex; align-items: center; gap: 0.4rem; user-select: none;
            filter: drop-shadow(0 0 10px rgba(255,90,95,.18));
        }
        .logo-icon { -webkit-text-fill-color: initial; filter: drop-shadow(0 0 8px rgba(255,90,95,.35)); }

        .topbar-actions { display: flex; align-items: center; gap: 1rem; }

        .btn-help {
            font-family: 'Space Mono', monospace; font-size: .7rem; letter-spacing: .12em; text-transform: uppercase;
            color: var(--text-soft); border: 1px solid var(--border); background: rgba(255,255,255,.03);
            padding: .45rem 1rem; border-radius: 8px; text-decoration: none; transition: .25s ease;
        }
        .btn-help:hover { background: rgba(255,255,255,.06); border-color: rgba(45,212,191,.4); color: var(--text-main); }

        .role-badge {
            font-family: 'Space Mono', monospace; font-size: .62rem; letter-spacing: .1em; text-transform: uppercase;
            background: rgba(45,212,191,.12); color: var(--accent);
            border: 1px solid rgba(45,212,191,.2); padding: .35rem .9rem; border-radius: 20px;
        }

        .avatar-btn {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, #2DD4BF, #0ea5e9);
            border: 2px solid rgba(255,255,255,.08);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; cursor: pointer; transition: .25s ease;
            box-shadow: 0 10px 30px rgba(45,212,191,.2);
        }
        .avatar-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(45,212,191,.35); }

        /* ── DROPDOWN ── */
        .dropdown { position: relative; }
        .dropdown-menu {
            display: none; position: absolute; right: 0; top: calc(100% + 12px);
            min-width: 190px; background: rgba(11,22,35,.92); backdrop-filter: blur(16px);
            border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-soft);
        }
        .dropdown:hover .dropdown-menu, .dropdown.open .dropdown-menu { display: block; }
        .dropdown-item {
            display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem;
            color: var(--text-soft); text-decoration: none; font-size: .85rem;
            border-bottom: 1px solid rgba(255,255,255,.03); transition: .2s ease;
        }
        .dropdown-item:hover { background: rgba(255,255,255,.03); color: var(--text-main); }
        .dropdown-item.danger:hover { background: rgba(255,90,95,.12); }

        /* ── LAYOUT ── */
        .layout { display: flex; padding-top: 68px; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 230px; position: fixed; top: 68px; left: 0; bottom: 0;
            background: rgba(8,14,22,.82); backdrop-filter: blur(18px);
            border-right: 1px solid var(--border); padding: 1.5rem 0;
            display: flex; flex-direction: column;
        }
        .nav-section { padding: 0 1rem; margin-bottom: 1rem; }
        .nav-label {
            font-family: 'Space Mono', monospace; font-size: .6rem; letter-spacing: .22em;
            text-transform: uppercase; color: var(--text-muted); margin-bottom: .7rem; padding-left: .5rem;
        }
        .nav-item {
            display: flex; align-items: center; gap: .75rem; padding: .75rem .9rem;
            border-radius: 12px; color: var(--text-soft); text-decoration: none;
            font-size: .88rem; font-weight: 500; transition: .25s ease; margin-bottom: 4px;
        }
        .nav-item:hover { background: rgba(255,255,255,.03); color: var(--text-main); transform: translateX(3px); }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(45,212,191,.15), rgba(255,90,95,.06));
            border: 1px solid rgba(255,255,255,.05); color: white;
        }
        .nav-icon { font-size: 1rem; }
        .badge {
            margin-left: auto;
            background: linear-gradient(135deg, var(--accent), #0ea5e9);
            color: #071018; font-size: .65rem; font-weight: 700;
            padding: 2px 8px; border-radius: 30px; font-family: 'Space Mono', monospace;
        }
        .sidebar-footer { margin-top: auto; padding: 1rem; border-top: 1px solid var(--border); }
        .btn-logout {
            width: 100%; display: flex; align-items: center; gap: .7rem; padding: .75rem 1rem;
            background: rgba(255,255,255,.03); border: 1px solid var(--border); border-radius: 12px;
            color: var(--text-soft); cursor: pointer; transition: .25s ease; font-family: 'DM Sans', sans-serif;
        }
        .btn-logout:hover { background: rgba(255,90,95,.12); color: white; }

        /* ── MAIN ── */
        .main { margin-left: 230px; flex: 1; padding: 2rem 2.5rem; }

        /* ── WELCOME ── */
        .welcome-banner {
            position: relative; overflow: hidden;
            background: linear-gradient(135deg, rgba(45,212,191,.08), rgba(255,90,95,.04));
            border: 1px solid rgba(45,212,191,.15); border-radius: 24px;
            padding: 2.3rem 2.5rem; margin-bottom: 2rem;
            display: flex; justify-content: space-between; align-items: center;
            backdrop-filter: blur(14px); box-shadow: var(--shadow-soft);
        }
        .welcome-banner::before {
            content: ''; position: absolute; width: 240px; height: 240px; right: -60px; top: -80px;
            background: radial-gradient(circle, rgba(45,212,191,.12), transparent 70%);
        }
        .welcome-title {
            font-family: 'Bebas Neue', cursive; font-size: 3rem; letter-spacing: .05em;
            background: linear-gradient(90deg, #ffffff 0%, #7ffff4 45%, #2DD4BF 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .welcome-sub { color: var(--text-soft); margin-top: .45rem; }

        .btn-primary {
            background: linear-gradient(135deg, #2DD4BF, #0ea5e9);
            color: #071018; border: none; padding: .85rem 1.5rem; border-radius: 12px;
            font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: .5rem;
            text-decoration: none; transition: .25s ease; font-family: 'DM Sans', sans-serif;
            box-shadow: 0 10px 30px rgba(45,212,191,.25);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 35px rgba(45,212,191,.4); }

        /* ── STATS ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 2rem;
        }
        .stat-card {
            position: relative; overflow: hidden; background: var(--bg-card);
            border: 1px solid var(--border); border-radius: 18px; padding: 1.3rem 1.4rem;
            backdrop-filter: blur(14px); transition: .25s ease; box-shadow: var(--shadow-soft);
        }
        .stat-card:hover { transform: translateY(-4px); border-color: rgba(255,255,255,.1); }
        .stat-card::before {
            content: ''; position: absolute; left: 0; right: 0; bottom: 0;
            height: 3px; background: var(--bar-color);
        }
        .stat-label {
            font-family: 'Space Mono', monospace; font-size: .62rem; letter-spacing: .15em;
            text-transform: uppercase; color: var(--text-muted); margin-bottom: .5rem;
        }
        .stat-value { font-family: 'Bebas Neue', cursive; font-size: 2.4rem; color: white; }
        .stat-delta { margin-top: .35rem; color: var(--accent); font-size: .75rem; }
        .stat-icon { position: absolute; top: 1rem; right: 1rem; opacity: .2; font-size: 1.6rem; }

        /* ── CONTENT GRID ── */
        .content-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; }

        .section-title {
            font-family: 'Bebas Neue', cursive; font-size: 1.5rem; letter-spacing: .08em;
            color: white; margin-bottom: 1rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .section-title a {
            font-family: 'Space Mono', monospace; font-size: .65rem; letter-spacing: .1em;
            color: var(--accent); text-decoration: none; text-transform: uppercase;
            border: 1px solid rgba(45,212,191,.2); padding: .3rem .8rem; border-radius: 6px;
            transition: .2s;
        }
        .section-title a:hover { background: rgba(45,212,191,.1); }

        /* ── PUBLICACIONES ── */
        .pub-list { display: flex; flex-direction: column; gap: .9rem; }

        .pub-card {
            background: rgba(11,22,35,.72); border: 1px solid var(--border);
            border-radius: 16px; padding: 1.15rem 1.3rem;
            display: flex; align-items: center; gap: 1rem;
            transition: .25s ease; backdrop-filter: blur(12px);
        }
        .pub-card:hover {
            transform: translateX(4px); border-color: rgba(45,212,191,.25);
            background: rgba(14,24,38,.95);
        }
        .pub-icon {
            width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0;
            background: linear-gradient(135deg, rgba(45,212,191,.18), rgba(255,90,95,.12));
            border: 1px solid rgba(255,255,255,.05);
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .pub-info { flex: 1; min-width: 0; }
        .pub-title { font-weight: 600; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pub-meta { color: var(--text-muted); font-size: .78rem; margin-top: 4px; }
        .pub-actions { display: flex; gap: .5rem; flex-shrink: 0; }

        .btn-sm {
            font-family: 'Space Mono', monospace; font-size: .65rem; letter-spacing: .08em;
            text-transform: uppercase; padding: .4rem .8rem; border-radius: 8px;
            cursor: pointer; transition: .2s; text-decoration: none; border: none;
            display: inline-flex; align-items: center; gap: .3rem;
        }
        .btn-ver { background: rgba(45,212,191,.12); color: var(--accent); border: 1px solid rgba(45,212,191,.2); }
        .btn-ver:hover { background: rgba(45,212,191,.22); }
        .btn-editar { background: rgba(255,90,95,.12); color: var(--primary); border: 1px solid rgba(255,90,95,.2); }
        .btn-editar:hover { background: rgba(255,90,95,.22); }

        .tag {
            display: inline-flex; padding: 3px 8px; border-radius: 6px; margin-top: 6px;
            font-size: .65rem; font-weight: 700; font-family: 'Space Mono', monospace;
        }
        .tag-abierto { background: rgba(74,222,128,.12); color: var(--success); }
        .tag-proceso { background: rgba(245,158,11,.12); color: var(--warning); }
        .tag-cerrado { background: rgba(255,90,95,.12); color: var(--primary); }

        /* ── WIDGETS ── */
        .widget {
            background: rgba(11,22,35,.72); border: 1px solid var(--border);
            border-radius: 18px; padding: 1.3rem; margin-bottom: 1rem;
            backdrop-filter: blur(14px); box-shadow: var(--shadow-soft);
        }
        .widget-title {
            font-family: 'Space Mono', monospace; font-size: .65rem; letter-spacing: .2em;
            text-transform: uppercase; color: var(--text-muted); margin-bottom: 1rem;
        }

        /* perfil contratador */
        .profile-widget { text-align: center; }
        .profile-avatar {
            width: 68px; height: 68px; border-radius: 50%;
            background: linear-gradient(135deg, #2DD4BF, #0ea5e9);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto .8rem; font-size: 1.5rem; font-weight: 700; color: #071018;
            box-shadow: 0 10px 30px rgba(45,212,191,.25);
        }
        .profile-name { font-weight: 600; font-size: 1rem; color: white; }
        .profile-role { color: var(--text-muted); font-size: .82rem; margin-bottom: .75rem; }
        .profile-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-top: .75rem; }
        .pstat { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.04); border-radius: 12px; padding: .7rem; }
        .pstat-val { font-family: 'Bebas Neue', cursive; font-size: 1.5rem; color: var(--accent); }
        .pstat-lbl { font-size: .65rem; color: var(--text-muted); font-family: 'Space Mono', monospace; }

        /* postulantes */
        .postulante-item {
            display: flex; align-items: center; gap: .75rem;
            padding: .75rem 0; border-bottom: 1px solid rgba(255,255,255,.04);
        }
        .postulante-item:last-child { border-bottom: none; }
        .post-avatar {
            width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary), #ff7a70);
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; font-weight: 700;
        }
        .post-info { flex: 1; }
        .post-name { font-size: .85rem; font-weight: 600; color: white; }
        .post-job { font-size: .72rem; color: var(--text-muted); margin-top: 2px; }
        .post-rating { font-size: .8rem; color: var(--warning); }

        /* pagos recientes */
        .pago-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 0; border-bottom: 1px solid rgba(255,255,255,.04);
        }
        .pago-item:last-child { border-bottom: none; }
        .pago-info { font-size: .82rem; }
        .pago-desc { color: var(--text-soft); }
        .pago-fecha { font-size: .7rem; color: var(--text-muted); font-family: 'Space Mono', monospace; margin-top: 2px; }
        .pago-monto { font-family: 'Bebas Neue', cursive; font-size: 1.2rem; color: var(--success); }
        .pago-monto.pendiente { color: var(--warning); }

        /* ── RESPONSIVE ── */
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .content-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 700px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<nav class="topbar">
    <div class="logo"><span class="logo-icon">🔧</span> RED-WORK</div>
    <div class="topbar-actions">
        <span class="role-badge">💼 Contratador</span>
        <a href="#" class="btn-help">Ayuda</a>
        <div class="dropdown" onclick="this.classList.toggle('open')">
            <div class="avatar-btn" style="color:#071018;">{{ strtoupper(substr(auth()->user()->nombre, 0, 2)) }}</div>
            <div class="dropdown-menu">
                <a href="{{ route('profile.edit') }}" class="dropdown-item"><span>👤</span> Mi Perfil</a>
                <a href="/notificaciones" class="dropdown-item"><span>🔔</span> Notificaciones</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item danger" style="width:100%;background:none;border:none;font-family:inherit;cursor:pointer;">
                        <span>🚪</span> Cerrar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<!-- ══ LAYOUT ══ -->
<div class="layout">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
        <div class="nav-section">
            <div class="nav-label">Principal</div>
            <a href="/dashboard" class="nav-item active">
                <span class="nav-icon">🏠</span> Dashboard
            </a>
            <a href="/mis-publicaciones" class="nav-item">
                <span class="nav-icon">📋</span> Mis Publicaciones
                <span class="badge">4</span>
            </a>
            <a href="/publicar" class="nav-item">
                <span class="nav-icon">➕</span> Nueva Publicación
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-label">Gestión</div>
            <a href="/postulantes" class="nav-item">
                <span class="nav-icon">👥</span> Postulantes
                <span class="badge">7</span>
            </a>
            <a href="/mensajes" class="nav-item">
                <span class="nav-icon">💬</span> Mensajes
                <span class="badge">3</span>
            </a>
            <a href="/pagos" class="nav-item">
                <span class="nav-icon">💳</span> Pagos
            </a>
            <a href="/calificaciones" class="nav-item">
                <span class="nav-icon">⭐</span> Mis Calificaciones
            </a>
        </div>

        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">
                    <span>🚪</span> Cerrar Sesión
                </button>
            </form>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="main">

        <!-- Welcome -->
        <div class="welcome-banner">
            <div>
                <div class="welcome-title">Hola, {{ auth()->user()->nombre }}  </div><p>👋</p>
                <div class="welcome-sub">Gestiona tus publicaciones y encuentra al trabajador ideal.</div>
            </div>
            <a href="/publicar" class="btn-primary">➕ Nueva Publicación</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--bar-color: #2DD4BF;">
                <div class="stat-icon">📋</div>
                <div class="stat-label">Publicaciones Activas</div>
                <div class="stat-value">4</div>
                <div class="stat-delta">↑ 2 esta semana</div>
            </div>
            <div class="stat-card" style="--bar-color: #F59E0B;">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Postulantes</div>
                <div class="stat-value">17</div>
                <div class="stat-delta">↑ 5 nuevos hoy</div>
            </div>
            <div class="stat-card" style="--bar-color: #4ADE80;">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Trabajos Cerrados</div>
                <div class="stat-value">11</div>
                <div class="stat-delta">↑ 91% satisfacción</div>
            </div>
            <div class="stat-card" style="--bar-color: #FF5A5F;">
                <div class="stat-icon">💳</div>
                <div class="stat-label">Total Pagado</div>
                <div class="stat-value">Bs 8.6K</div>
                <div class="stat-delta">Este mes</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">

            <!-- Mis Publicaciones -->
            <div>
                <div class="section-title">
                    📌 Mis Publicaciones
                    <a href="/publicar">+ Nueva</a>
                </div>
                <div class="pub-list">

                    <div class="pub-card">
                        <div class="pub-icon">🔧</div>
                        <div class="pub-info">
                            <div class="pub-title">Reparación de Plomería — Domicilio</div>
                            <div class="pub-meta">📍 Equipetrol Norte · 3 postulantes</div>
                            <span class="tag tag-abierto">ABIERTO</span>
                        </div>
                        <div class="pub-actions">
                            <a href="#" class="btn-sm btn-ver">👥 Ver</a>
                            <a href="#" class="btn-sm btn-editar">✏️ Editar</a>
                        </div>
                    </div>

                    <div class="pub-card">
                        <div class="pub-icon">⚡</div>
                        <div class="pub-info">
                            <div class="pub-title">Instalación Eléctrica — 3 Ambientes</div>
                            <div class="pub-meta">📍 Plan 3000 · 7 postulantes</div>
                            <span class="tag tag-proceso">EN PROCESO</span>
                        </div>
                        <div class="pub-actions">
                            <a href="#" class="btn-sm btn-ver">👥 Ver</a>
                            <a href="#" class="btn-sm btn-editar">✏️ Editar</a>
                        </div>
                    </div>

                    <div class="pub-card">
                        <div class="pub-icon">🎨</div>
                        <div class="pub-info">
                            <div class="pub-title">Pintura Exterior — Casa 2 Pisos</div>
                            <div class="pub-meta">📍 Urbarí · 5 postulantes</div>
                            <span class="tag tag-abierto">ABIERTO</span>
                        </div>
                        <div class="pub-actions">
                            <a href="#" class="btn-sm btn-ver">👥 Ver</a>
                            <a href="#" class="btn-sm btn-editar">✏️ Editar</a>
                        </div>
                    </div>

                    <div class="pub-card">
                        <div class="pub-icon">🏗️</div>
                        <div class="pub-info">
                            <div class="pub-title">Albañilería — Ampliación Cuarto</div>
                            <div class="pub-meta">📍 Villa 1ro de Mayo · Trabajo finalizado</div>
                            <span class="tag tag-cerrado">CERRADO</span>
                        </div>
                        <div class="pub-actions">
                            <a href="#" class="btn-sm btn-ver">👁️ Ver</a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Widgets -->
            <div>

                <!-- Perfil -->
                <div class="widget profile-widget">
                    <div class="widget-title">Mi Cuenta</div>
                    <div class="profile-avatar">{{ strtoupper(substr(auth()->user()->nombre ?? 'CO', 0, 2)) }}</div>
                    <div class="profile-name">{{ auth()->user()->nombre ?? 'Contratador' }}</div>
                    <div class="profile-role">💼 Contratador · {{ auth()->user()->ciudad ?? 'Santa Cruz' }}</div>
                    <div style="display:flex;gap:4px;justify-content:center;font-size:0.9rem;margin-top:.4rem;">⭐⭐⭐⭐⭐ <span style="color:var(--text-muted);font-size:.75rem;margin-left:4px;">4.8 / 5</span></div>
                    <div class="profile-stats">
                        <div class="pstat">
                            <div class="pstat-val">11</div>
                            <div class="pstat-lbl">Cerrados</div>
                        </div>
                        <div class="pstat">
                            <div class="pstat-val">4</div>
                            <div class="pstat-lbl">Activos</div>
                        </div>
                    </div>
                </div>

                <!-- Postulantes recientes -->
                <div class="widget">
                    <div class="widget-title">Postulantes Recientes</div>

                    <div class="postulante-item">
                        <div class="post-avatar">JD</div>
                        <div class="post-info">
                            <div class="post-name">Juan Díaz</div>
                            <div class="post-job">Plomería — Equipetrol</div>
                        </div>
                        <div class="post-rating">⭐4.9</div>
                    </div>

                    <div class="postulante-item">
                        <div class="post-avatar" style="background:linear-gradient(135deg,#2DD4BF,#0ea5e9);color:#071018;">MR</div>
                        <div class="post-info">
                            <div class="post-name">María Rojas</div>
                            <div class="post-job">Electricidad — Plan 3000</div>
                        </div>
                        <div class="post-rating">⭐4.7</div>
                    </div>

                    <div class="postulante-item">
                        <div class="post-avatar" style="background:linear-gradient(135deg,#F59E0B,#ef4444);">CP</div>
                        <div class="post-info">
                            <div class="post-name">Carlos Peña</div>
                            <div class="post-job">Pintura — Urbarí</div>
                        </div>
                        <div class="post-rating">⭐4.5</div>
                    </div>

                    <div class="postulante-item">
                        <div class="post-avatar" style="background:linear-gradient(135deg,#4ADE80,#0ea5e9);color:#071018;">LV</div>
                        <div class="post-info">
                            <div class="post-name">Luis Vargas</div>
                            <div class="post-job">Electricidad — Plan 3000</div>
                        </div>
                        <div class="post-rating">⭐4.8</div>
                    </div>
                </div>

                <!-- Pagos recientes -->
                <div class="widget">
                    <div class="widget-title">Pagos Recientes</div>

                    <div class="pago-item">
                        <div class="pago-info">
                            <div class="pago-desc">Albañilería — Luis V.</div>
                            <div class="pago-fecha">hace 2 días</div>
                        </div>
                        <div class="pago-monto">Bs 1.4K</div>
                    </div>

                    <div class="pago-item">
                        <div class="pago-info">
                            <div class="pago-desc">Plomería — Juan D.</div>
                            <div class="pago-fecha">hace 5 días</div>
                        </div>
                        <div class="pago-monto">Bs 350</div>
                    </div>

                    <div class="pago-item">
                        <div class="pago-info">
                            <div class="pago-desc">Electricidad — pendiente</div>
                            <div class="pago-fecha">vence en 3 días</div>
                        </div>
                        <div class="pago-monto pendiente">Bs 620</div>
                    </div>
                </div>

            </div>
        </div>

    </main>
</div>

<script>
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.dropdown.open').forEach(d => {
            if (!d.contains(e.target)) d.classList.remove('open');
        });
    });
</script>

</body>
</html>