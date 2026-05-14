<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red-900: #7f0000;
            --red-800: #991b1b;
            --red-700: #b91c1c;
            --red-600: #dc2626;
            --red-500: #ef4444;
            --red-400: #f87171;
            --red-300: #fca5a5;
            --red-100: #fee2e2;
            --red-50:  #fff5f5;
            --coal:    #1a0a0a;
            --dark:    #0f0505;
            --white:   #ffffff;
            --gray-light: #f9f0f0;
            --accent:  #ff6b35;
            --gold:    #f59e0b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--white);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── TOPBAR ── */
        .topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            background: linear-gradient(135deg, var(--coal) 0%, #2a0a0a 100%);
            border-bottom: 2px solid var(--red-700);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            height: 64px;
            box-shadow: 0 4px 30px rgba(220,38,38,0.3);
        }

        .logo {
            font-family: 'Bebas Neue', cursive;
            font-size: 2rem;
            letter-spacing: 0.1em;
            background: linear-gradient(90deg, var(--red-500), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            user-select: none;
        }

        .logo-icon { font-size: 1.6rem; -webkit-text-fill-color: initial; }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }

        .btn-help {
            font-family: 'Space Mono', monospace;
            font-size: 0.7rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--red-300);
            background: transparent;
            border: 1px solid var(--red-800);
            padding: 0.4rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-help:hover {
            background: var(--red-900);
            border-color: var(--red-500);
            color: var(--white);
        }

        .avatar-btn {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red-600), var(--red-900));
            border: 2px solid var(--red-500);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.9rem;
            cursor: pointer;
            position: relative;
            transition: box-shadow 0.2s;
        }
        .avatar-btn:hover { box-shadow: 0 0 12px rgba(239,68,68,0.6); }

        /* dropdown */
        .dropdown { position: relative; }
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0; top: calc(100% + 10px);
            background: var(--coal);
            border: 1px solid var(--red-800);
            border-radius: 8px;
            min-width: 180px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.6);
            z-index: 999;
        }
        .dropdown:hover .dropdown-menu,
        .dropdown.open .dropdown-menu { display: block; }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            color: var(--red-300);
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
            border-bottom: 1px solid rgba(153,27,27,0.2);
        }
        .dropdown-item:last-child { border-bottom: none; }
        .dropdown-item:hover { background: var(--red-900); color: var(--white); }
        .dropdown-item.danger:hover { background: var(--red-700); color: var(--white); }
        .dropdown-item .icon { font-size: 1rem; }

        /* ── SIDEBAR ── */
        .layout {
            display: flex;
            padding-top: 64px;
            min-height: 100vh;
        }

        .sidebar {
            width: 220px;
            background: linear-gradient(180deg, #1a0808 0%, #0f0505 100%);
            border-right: 1px solid rgba(153,27,27,0.3);
            padding: 1.5rem 0;
            position: fixed;
            top: 64px; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .nav-section {
            padding: 0 1rem;
            margin-bottom: 1rem;
        }

        .nav-label {
            font-family: 'Space Mono', monospace;
            font-size: 0.6rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--red-800);
            padding: 0 0.5rem;
            margin-bottom: 0.5rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.75rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 500;
            color: #c9a0a0;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 2px;
            position: relative;
        }
        .nav-item:hover {
            background: rgba(220,38,38,0.12);
            color: var(--white);
        }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(220,38,38,0.25), transparent);
            color: var(--white);
            border-left: 3px solid var(--red-500);
        }
        .nav-item .nav-icon { font-size: 1.1rem; }

        .badge {
            margin-left: auto;
            background: var(--red-600);
            color: var(--white);
            font-size: 0.65rem;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
            font-family: 'Space Mono', monospace;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(153,27,27,0.3);
        }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.65rem 0.75rem;
            border-radius: 8px;
            background: rgba(220,38,38,0.08);
            border: 1px solid rgba(220,38,38,0.2);
            color: var(--red-400);
            font-size: 0.85rem;
            font-weight: 500;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }
        .btn-logout:hover {
            background: rgba(220,38,38,0.2);
            border-color: var(--red-500);
            color: var(--white);
        }

        /* ── MAIN CONTENT ── */
        .main {
            margin-left: 220px;
            flex: 1;
            padding: 2rem 2.5rem;
            background: radial-gradient(ellipse at 80% 20%, rgba(153,27,27,0.08) 0%, transparent 60%);
        }

        /* WELCOME BANNER */
        .welcome-banner {
            background: linear-gradient(135deg, #2a0808 0%, #1a0505 60%, rgba(220,38,38,0.08) 100%);
            border: 1px solid rgba(220,38,38,0.2);
            border-radius: 16px;
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        .welcome-banner::before {
            content: '';
            position: absolute;
            right: -40px; top: -40px;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(239,68,68,0.15), transparent 70%);
            pointer-events: none;
        }
        .welcome-banner::after {
            content: '🔧';
            position: absolute;
            right: 2.5rem;
            font-size: 5rem;
            opacity: 0.06;
            top: 50%;
            transform: translateY(-50%);
        }

        .welcome-title {
            font-family: 'Bebas Neue', cursive;
            font-size: 2.8rem;
            letter-spacing: 0.05em;
            line-height: 1;
            background: linear-gradient(90deg, var(--white), var(--red-400));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .welcome-sub {
            font-size: 0.9rem;
            color: #a07070;
            margin-top: 0.4rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--red-600), var(--red-800));
            color: var(--white);
            border: none;
            padding: 0.7rem 1.6rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'DM Sans', sans-serif;
            box-shadow: 0 4px 15px rgba(220,38,38,0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--red-500), var(--red-700));
            box-shadow: 0 6px 20px rgba(220,38,38,0.5);
            transform: translateY(-1px);
        }

        /* STAT CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #1e0808, #150505);
            border: 1px solid rgba(153,27,27,0.25);
            border-radius: 12px;
            padding: 1.25rem 1.4rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220,38,38,0.15);
            border-color: rgba(220,38,38,0.4);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--bar-color, var(--red-600));
        }

        .stat-label {
            font-family: 'Space Mono', monospace;
            font-size: 0.62rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #8a5050;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-family: 'Bebas Neue', cursive;
            font-size: 2.4rem;
            letter-spacing: 0.05em;
            color: var(--white);
            line-height: 1;
        }
        .stat-delta {
            font-size: 0.75rem;
            color: var(--gold);
            margin-top: 0.3rem;
        }
        .stat-icon {
            position: absolute;
            top: 1rem; right: 1rem;
            font-size: 1.5rem;
            opacity: 0.35;
        }

        /* CONTENT GRID */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
        }

        /* JOB CARDS */
        .section-title {
            font-family: 'Bebas Neue', cursive;
            font-size: 1.4rem;
            letter-spacing: 0.08em;
            color: var(--red-400);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .jobs-list { display: flex; flex-direction: column; gap: 0.75rem; }

        .job-card {
            background: #1a0808;
            border: 1px solid rgba(153,27,27,0.2);
            border-radius: 10px;
            padding: 1.1rem 1.3rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .job-card:hover {
            background: #220a0a;
            border-color: rgba(220,38,38,0.4);
            transform: translateX(4px);
        }

        .job-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--red-800), var(--red-900));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .job-info { flex: 1; min-width: 0; }
        .job-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--white);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .job-meta {
            font-size: 0.78rem;
            color: #8a5050;
            margin-top: 2px;
        }

        .job-price {
            font-family: 'Bebas Neue', cursive;
            font-size: 1.3rem;
            color: var(--red-400);
            flex-shrink: 0;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.68rem;
            font-weight: 600;
            font-family: 'Space Mono', monospace;
            margin-top: 4px;
        }
        .tag-recurrent { background: rgba(245,158,11,0.15); color: var(--gold); }
        .tag-temp { background: rgba(220,38,38,0.15); color: var(--red-400); }
        .tag-new { background: rgba(34,197,94,0.15); color: #4ade80; }

        /* SIDEBAR WIDGETS */
        .widget {
            background: #1a0808;
            border: 1px solid rgba(153,27,27,0.2);
            border-radius: 12px;
            padding: 1.3rem;
            margin-bottom: 1rem;
        }
        .widget-title {
            font-family: 'Space Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--red-700);
            margin-bottom: 1rem;
        }

        .profile-widget { text-align: center; }
        .profile-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red-600), var(--red-900));
            border: 3px solid var(--red-700);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 700;
            margin: 0 auto 0.75rem;
            box-shadow: 0 0 20px rgba(239,68,68,0.3);
        }
        .profile-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--white);
        }
        .profile-role {
            font-size: 0.78rem;
            color: #8a5050;
            margin-bottom: 0.75rem;
        }
        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .pstat {
            background: rgba(220,38,38,0.08);
            border-radius: 8px;
            padding: 0.5rem;
            text-align: center;
        }
        .pstat-val {
            font-family: 'Bebas Neue', cursive;
            font-size: 1.4rem;
            color: var(--red-400);
        }
        .pstat-lbl {
            font-size: 0.65rem;
            color: #8a5050;
            font-family: 'Space Mono', monospace;
            letter-spacing: 0.05em;
        }

        /* Activity feed */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.65rem 0;
            border-bottom: 1px solid rgba(153,27,27,0.1);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--red-500);
            margin-top: 5px;
            flex-shrink: 0;
            box-shadow: 0 0 6px rgba(239,68,68,0.5);
        }
        .activity-text { font-size: 0.82rem; color: #b08080; line-height: 1.4; }
        .activity-text strong { color: var(--white); }
        .activity-time { font-size: 0.7rem; color: #6a3030; font-family: 'Space Mono', monospace; margin-top: 2px; }

        /* FAB */
        .fab {
            position: fixed;
            bottom: 2rem; right: 2rem;
            width: 56px; height: 56px;
            background: linear-gradient(135deg, var(--red-500), var(--red-700));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(220,38,38,0.5);
            transition: all 0.2s;
            border: none;
            color: var(--white);
            z-index: 50;
        }
        .fab:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 8px 30px rgba(220,38,38,0.7);
        }

        /* PROGRESS BAR */
        .progress-bar {
            height: 5px;
            background: rgba(153,27,27,0.2);
            border-radius: 10px;
            overflow: hidden;
            margin: 0.4rem 0 0.75rem;
        }
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, var(--red-600), var(--accent));
        }

        .skill-row { margin-bottom: 0.6rem; }
        .skill-row-top {
            display: flex;
            justify-content: space-between;
            font-size: 0.78rem;
            color: #a07070;
        }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
    <div class="logo">
        <span class="logo-icon">🔧</span> RED-WORK
    </div>
    <div class="topbar-actions">
        <a href="#" class="btn-help">Ayuda</a>

        <!-- Dropdown usuario -->
        <div class="dropdown" onclick="this.classList.toggle('open')">
            <div class="avatar-btn">JD</div>
            <div class="dropdown-menu">
                <a href="/perfil" class="dropdown-item"><span class="icon">👤</span> Mi Perfil</a>
                <a href="/configuracion" class="dropdown-item"><span class="icon">⚙️</span> Configuración</a>
                <a href="/notificaciones" class="dropdown-item"><span class="icon">🔔</span> Notificaciones</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item danger" style="width:100%;background:none;border:none;font-family:inherit;">
                        <span class="icon">🚪</span> Cerrar Sesión
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
            <a href="/trabajos/recurrentes" class="nav-item">
                <span class="nav-icon">🔄</span> Recurrentes
                <span class="badge">3</span>
            </a>
            <a href="/trabajos/temporales" class="nav-item">
                <span class="nav-icon">⏳</span> Temporales
            </a>
            <a href="/publicar" class="nav-item">
                <span class="nav-icon">➕</span> Publicar Trabajo
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-label">Gestión</div>
            <a href="/mis-trabajos" class="nav-item">
                <span class="nav-icon">📋</span> Mis Trabajos
            </a>
            <a href="/mensajes" class="nav-item">
                <span class="nav-icon">💬</span> Mensajes
                <span class="badge">5</span>
            </a>
            <a href="/pagos" class="nav-item">
                <span class="nav-icon">💳</span> Pagos
            </a>
            <a href="/calificaciones" class="nav-item">
                <span class="nav-icon">⭐</span> Calificaciones
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
                <div class="welcome-title">Bienvenido 👋</div>
                <div class="welcome-sub">Explora los trabajos y empieza a publicar.</div>
            </div>
            <a href="/publicar" class="btn-primary">➕ Publicar Trabajo</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--bar-color: #ef4444;">
                <div class="stat-icon">💼</div>
                <div class="stat-label">Trabajos Activos</div>
                <div class="stat-value">12</div>
                <div class="stat-delta">↑ 4 esta semana</div>
            </div>
            <div class="stat-card" style="--bar-color: #f59e0b;">
                <div class="stat-icon">⏳</div>
                <div class="stat-label">En Progreso</div>
                <div class="stat-value">5</div>
                <div class="stat-delta">↑ 1 nuevo hoy</div>
            </div>
            <div class="stat-card" style="--bar-color: #4ade80;">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Completados</div>
                <div class="stat-value">38</div>
                <div class="stat-delta">↑ 94% satisfacción</div>
            </div>
            <div class="stat-card" style="--bar-color: #ff6b35;">
                <div class="stat-icon">💰</div>
                <div class="stat-label">Ingresos</div>
                <div class="stat-value">Bs 4.2K</div>
                <div class="stat-delta">↑ 18% vs mes ant.</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">

            <!-- Jobs List -->
            <div>
                <div class="section-title">📌 Trabajos Recientes</div>
                <div class="jobs-list">

                    <div class="job-card">
                        <div class="job-icon">🔧</div>
                        <div class="job-info">
                            <div class="job-title">Reparación de Plomería Domicilio</div>
                            <div class="job-meta">📍 Equipetrol Norte · Solicitado hace 2h</div>
                            <span class="tag tag-recurrent">RECURRENTE</span>
                        </div>
                        <div class="job-price">Bs 350</div>
                    </div>

                    <div class="job-card">
                        <div class="job-icon">⚡</div>
                        <div class="job-info">
                            <div class="job-title">Instalación Eléctrica — 3 Ambientes</div>
                            <div class="job-meta">📍 Plan 3000 · Solicitado hace 5h</div>
                            <span class="tag tag-temp">TEMPORAL</span>
                        </div>
                        <div class="job-price">Bs 620</div>
                    </div>

                    <div class="job-card">
                        <div class="job-icon">🎨</div>
                        <div class="job-info">
                            <div class="job-title">Pintura Exterior — Casa 2 Pisos</div>
                            <div class="job-meta">📍 Urbarí · Solicitado hace 1d</div>
                            <span class="tag tag-new">NUEVO</span>
                        </div>
                        <div class="job-price">Bs 980</div>
                    </div>

                    <div class="job-card">
                        <div class="job-icon">🏗️</div>
                        <div class="job-info">
                            <div class="job-title">Albañilería — Ampliación Cuarto</div>
                            <div class="job-meta">📍 Villa 1ro de Mayo · Solicitado hace 2d</div>
                            <span class="tag tag-temp">TEMPORAL</span>
                        </div>
                        <div class="job-price">Bs 1.4K</div>
                    </div>

                    <div class="job-card">
                        <div class="job-icon">🧹</div>
                        <div class="job-info">
                            <div class="job-title">Limpieza Post-Obra — Departamento</div>
                            <div class="job-meta">📍 Hamacas · Solicitado hace 3d</div>
                            <span class="tag tag-recurrent">RECURRENTE</span>
                        </div>
                        <div class="job-price">Bs 200</div>
                    </div>

                </div>
            </div>

            <!-- Right Widgets -->
            <div>

                <!-- Profile widget -->
                <div class="widget profile-widget">
                    <div class="widget-title">Mi Perfil</div>
                    <div class="profile-avatar">JD</div>
                    <div class="profile-name">Juan Díaz</div>
                    <div class="profile-role">Maestro Electricista · Santa Cruz</div>
                    <div style="display:flex;gap:4px;justify-content:center;font-size:0.9rem;">⭐⭐⭐⭐⭐ <span style="color:#8a5050;font-size:0.75rem;margin-left:4px;">4.9 / 5</span></div>
                    <div class="profile-stats">
                        <div class="pstat">
                            <div class="pstat-val">38</div>
                            <div class="pstat-lbl">Completos</div>
                        </div>
                        <div class="pstat">
                            <div class="pstat-val">96%</div>
                            <div class="pstat-lbl">Éxito</div>
                        </div>
                    </div>
                </div>

                <!-- Skills widget -->
                <div class="widget">
                    <div class="widget-title">Mis Habilidades</div>
                    <div class="skill-row">
                        <div class="skill-row-top"><span>Electricidad</span><span>92%</span></div>
                        <div class="progress-bar"><div class="progress-fill" style="width:92%"></div></div>
                    </div>
                    <div class="skill-row">
                        <div class="skill-row-top"><span>Plomería</span><span>75%</span></div>
                        <div class="progress-bar"><div class="progress-fill" style="width:75%"></div></div>
                    </div>
                    <div class="skill-row">
                        <div class="skill-row-top"><span>Albañilería</span><span>60%</span></div>
                        <div class="progress-bar"><div class="progress-fill" style="width:60%"></div></div>
                    </div>
                </div>

                <!-- Activity widget -->
                <div class="widget">
                    <div class="widget-title">Actividad Reciente</div>
                    <div class="activity-item">
                        <div class="activity-dot"></div>
                        <div>
                            <div class="activity-text"><strong>Carlos M.</strong> solicitó tu servicio de plomería</div>
                            <div class="activity-time">hace 20 min</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-dot" style="background:#4ade80;box-shadow:0 0 6px rgba(74,222,128,0.5);"></div>
                        <div>
                            <div class="activity-text">Pago de <strong>Bs 350</strong> recibido correctamente</div>
                            <div class="activity-time">hace 2h</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-dot" style="background:#f59e0b;box-shadow:0 0 6px rgba(245,158,11,0.5);"></div>
                        <div>
                            <div class="activity-text"><strong>Ana R.</strong> te dejó una calificación de ⭐5</div>
                            <div class="activity-time">hace 5h</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-dot"></div>
                        <div>
                            <div class="activity-text">Nuevo trabajo disponible en <strong>Urbarí</strong></div>
                            <div class="activity-time">hace 1d</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>
</div>

<!-- FAB -->
<button class="fab" onclick="window.location='/publicar'" title="Publicar trabajo">+</button>

<script>
    // Cerrar dropdown al hacer click fuera
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.dropdown.open').forEach(d => {
            if (!d.contains(e.target)) d.classList.remove('open');
        });
    });
</script>

</body>
</html>