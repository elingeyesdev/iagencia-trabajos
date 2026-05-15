<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        
    :root {
    --bg-main: #071018;
    --bg-secondary: #0B1623;
    --bg-card: rgba(14, 24, 38, 0.88);

    --primary: #FF5A5F;
    --primary-hover: #FF6B6B;
    --primary-soft: rgba(255,90,95,0.12);

    --accent: #2DD4BF;
    --accent-soft: rgba(45,212,191,0.12);

    --success: #4ADE80;
    --warning: #F59E0B;

    --text-main: #F8FAFC;
    --text-soft: #94A3B8;
    --text-muted: #64748B;

    --border: rgba(255,255,255,0.06);
    --border-strong: rgba(255,255,255,0.1);

    --shadow-red: 0 10px 30px rgba(255,90,95,0.18);
    --shadow-soft: 0 10px 40px rgba(0,0,0,0.35);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'DM Sans', sans-serif;
    background:
        radial-gradient(circle at top left,
        rgba(255,90,95,.08),
        transparent 35%),
        radial-gradient(circle at bottom right,
        rgba(45,212,191,.06),
        transparent 30%),
        var(--bg-main);

    color: var(--text-main);
    min-height: 100vh;
    overflow-x: hidden;
}

/* ───────── TOPBAR ───────── */

.topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;

    height: 68px;
    padding: 0 2rem;

    display: flex;
    align-items: center;
    justify-content: space-between;

    background: rgba(7,16,24,.72);

    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);

    border-bottom: 1px solid var(--border);

    box-shadow: 0 8px 30px rgba(0,0,0,.25);
}

.logo {
    font-family: 'Bebas Neue', cursive;
    font-size: 2rem;
    letter-spacing: 0.1em;

    background: linear-gradient(
        90deg,
        #ff4d6d 0%,
        #ff5a5f 45%,
        #ff7a7f 100%
    );

    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;

    display: flex;
    align-items: center;
    gap: 0.4rem;

    user-select: none;

    filter: drop-shadow(0 0 10px rgba(255,90,95,.18));
}

.logo-icon {
    -webkit-text-fill-color: initial;

    filter:
        drop-shadow(0 0 8px rgba(255,90,95,.35));
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.btn-help {
    font-family: 'Space Mono', monospace;
    font-size: .7rem;
    letter-spacing: .12em;
    text-transform: uppercase;

    color: var(--text-soft);

    border: 1px solid var(--border);
    background: rgba(255,255,255,.03);

    padding: .45rem 1rem;
    border-radius: 8px;

    text-decoration: none;

    transition: .25s ease;
}

.btn-help:hover {
    background: rgba(255,255,255,.06);
    border-color: rgba(45,212,191,.4);
    color: var(--text-main);
}

.avatar-btn {
    width: 40px;
    height: 40px;

    border-radius: 50%;

    background: linear-gradient(
        135deg,
        var(--primary),
        #FF7B72
    );

    border: 2px solid rgba(255,255,255,.08);

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 700;

    cursor: pointer;

    transition: .25s ease;

    box-shadow: var(--shadow-red);
}

.avatar-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(255,90,95,.35);
}

/* ───────── DROPDOWN ───────── */

.dropdown {
    position: relative;
}

.dropdown-menu {
    display: none;

    position: absolute;
    right: 0;
    top: calc(100% + 12px);

    min-width: 190px;

    background: rgba(11,22,35,.92);

    backdrop-filter: blur(16px);

    border: 1px solid var(--border);
    border-radius: 14px;

    overflow: hidden;

    box-shadow: var(--shadow-soft);
}

.dropdown:hover .dropdown-menu,
.dropdown.open .dropdown-menu {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: .75rem;

    padding: .85rem 1rem;

    color: var(--text-soft);

    text-decoration: none;

    font-size: .85rem;

    border-bottom: 1px solid rgba(255,255,255,.03);

    transition: .2s ease;
}

.dropdown-item:hover {
    background: rgba(255,255,255,.03);
    color: var(--text-main);
}

.dropdown-item.danger:hover {
    background: rgba(255,90,95,.12);
}

/* ───────── LAYOUT ───────── */

.layout {
    display: flex;
    padding-top: 68px;
    min-height: 100vh;
}

/* ───────── SIDEBAR ───────── */

.sidebar {
    width: 230px;

    position: fixed;
    top: 68px;
    left: 0;
    bottom: 0;

    background: rgba(8,14,22,.82);

    backdrop-filter: blur(18px);

    border-right: 1px solid var(--border);

    padding: 1.5rem 0;

    display: flex;
    flex-direction: column;
}

.nav-section {
    padding: 0 1rem;
    margin-bottom: 1rem;
}

.nav-label {
    font-family: 'Space Mono', monospace;

    font-size: .6rem;
    letter-spacing: .22em;
    text-transform: uppercase;

    color: var(--text-muted);

    margin-bottom: .7rem;
    padding-left: .5rem;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: .75rem;

    padding: .75rem .9rem;

    border-radius: 12px;

    color: var(--text-soft);

    text-decoration: none;
    font-size: .88rem;
    font-weight: 500;

    transition: .25s ease;

    margin-bottom: 4px;

    position: relative;
}

.nav-item:hover {
    background: rgba(255,255,255,.03);
    color: var(--text-main);

    transform: translateX(3px);
}

.nav-item.active {
    background:
        linear-gradient(
            90deg,
            rgba(255,90,95,.15),
            rgba(45,212,191,.06)
        );

    border: 1px solid rgba(255,255,255,.05);

    color: white;

    box-shadow: inset 0 0 0 1px rgba(255,255,255,.02);
}

.nav-icon {
    font-size: 1rem;
}

.badge {
    margin-left: auto;

    background: linear-gradient(
        135deg,
        var(--primary),
        #ff7a70
    );

    color: white;

    font-size: .65rem;
    font-weight: 700;

    padding: 2px 8px;

    border-radius: 30px;

    font-family: 'Space Mono', monospace;
}

.sidebar-footer {
    margin-top: auto;

    padding: 1rem;

    border-top: 1px solid var(--border);
}

.btn-logout {
    width: 100%;

    display: flex;
    align-items: center;
    gap: .7rem;

    padding: .75rem 1rem;

    background: rgba(255,255,255,.03);

    border: 1px solid var(--border);

    border-radius: 12px;

    color: var(--text-soft);

    cursor: pointer;

    transition: .25s ease;
}

.btn-logout:hover {
    background: rgba(255,90,95,.12);
    color: white;
}

/* ───────── MAIN ───────── */

.main {
    margin-left: 230px;

    flex: 1;

    padding: 2rem 2.5rem;
}

/* ───────── WELCOME ───────── */

.welcome-banner {
    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            rgba(255,90,95,.08),
            rgba(45,212,191,.04)
        );

    border: 1px solid var(--border);

    border-radius: 24px;

    padding: 2.3rem 2.5rem;

    margin-bottom: 2rem;

    display: flex;
    justify-content: space-between;
    align-items: center;

    backdrop-filter: blur(14px);

    box-shadow: var(--shadow-soft);
}

.welcome-banner::before {
    content: '';

    position: absolute;

    width: 240px;
    height: 240px;

    right: -60px;
    top: -80px;

    background:
        radial-gradient(
            circle,
            rgba(45,212,191,.12),
            transparent 70%
        );
}

.welcome-title {
    font-family: 'Bebas Neue', cursive;
    font-size: 3rem;
    letter-spacing: .05em;

    background: linear-gradient(
        90deg,
        #ffffff 0%,
        #FF8DA1 45%,
        #FF5A5F 100%
    );

    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;

    text-shadow:
        0 0 25px rgba(255,90,95,.15);
}

.welcome-sub {
    color: var(--text-soft);
    margin-top: .45rem;
}

.btn-primary {
    background:
        linear-gradient(
            135deg,
            var(--primary),
            #ff7878
        );

    color: white;

    border: none;

    padding: .85rem 1.5rem;

    border-radius: 12px;

    font-weight: 600;

    cursor: pointer;

    display: inline-flex;
    align-items: center;
    gap: .5rem;

    text-decoration: none;

    transition: .25s ease;

    box-shadow: var(--shadow-red);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 35px rgba(255,90,95,.35);
}

/* ───────── STATS ───────── */

.stats-grid {
    display: grid;

    grid-template-columns: repeat(4,1fr);

    gap: 1rem;

    margin-bottom: 2rem;
}

.stat-card {
    position: relative;

    overflow: hidden;

    background: var(--bg-card);

    border: 1px solid var(--border);

    border-radius: 18px;

    padding: 1.3rem 1.4rem;

    backdrop-filter: blur(14px);

    transition: .25s ease;

    box-shadow: var(--shadow-soft);
}

.stat-card:hover {
    transform: translateY(-4px);

    border-color: rgba(255,255,255,.1);
}

.stat-card::before {
    content: '';

    position: absolute;

    left: 0;
    right: 0;
    bottom: 0;

    height: 3px;

    background: var(--bar-color);
}

.stat-label {
    font-family: 'Space Mono', monospace;

    font-size: .62rem;
    letter-spacing: .15em;
    text-transform: uppercase;

    color: var(--text-muted);

    margin-bottom: .5rem;
}

.stat-value {
    font-family: 'Bebas Neue', cursive;

    font-size: 2.4rem;

    color: white;
}

.stat-delta {
    margin-top: .35rem;

    color: var(--accent);

    font-size: .75rem;
}

.stat-icon {
    position: absolute;

    top: 1rem;
    right: 1rem;

    opacity: .2;

    font-size: 1.6rem;
}

/* ───────── CONTENT GRID ───────── */

.content-grid {
    display: grid;

    grid-template-columns: 1fr 340px;

    gap: 1.5rem;
}

.section-title {
    font-family: 'Bebas Neue', cursive;

    font-size: 1.5rem;
    letter-spacing: .08em;

    color: white;

    margin-bottom: 1rem;
}

/* ───────── JOB CARDS ───────── */

.jobs-list {
    display: flex;
    flex-direction: column;
    gap: .9rem;
}

.job-card {
    background: rgba(11,22,35,.72);

    border: 1px solid var(--border);

    border-radius: 16px;

    padding: 1.15rem 1.3rem;

    display: flex;
    align-items: center;
    gap: 1rem;

    cursor: pointer;

    transition: .25s ease;

    backdrop-filter: blur(12px);
}

.job-card:hover {
    transform: translateX(4px);

    border-color: rgba(45,212,191,.25);

    background: rgba(14,24,38,.95);
}

.job-icon {
    width: 48px;
    height: 48px;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            rgba(255,90,95,.18),
            rgba(45,212,191,.12)
        );

    border: 1px solid rgba(255,255,255,.05);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 1.3rem;

    flex-shrink: 0;
}

.job-info {
    flex: 1;
    min-width: 0;
}

.job-title {
    font-weight: 600;
    color: white;
}

.job-meta {
    color: var(--text-muted);
    font-size: .78rem;
    margin-top: 4px;
}

.job-price {
    font-family: 'Bebas Neue', cursive;

    font-size: 1.4rem;

    color: var(--accent);
}

.tag {
    display: inline-flex;

    padding: 3px 8px;

    border-radius: 6px;

    margin-top: 6px;

    font-size: .65rem;
    font-weight: 700;

    font-family: 'Space Mono', monospace;
}

.tag-recurrent {
    background: rgba(45,212,191,.12);
    color: var(--accent);
}

.tag-temp {
    background: rgba(255,90,95,.12);
    color: var(--primary);
}

.tag-new {
    background: rgba(74,222,128,.12);
    color: var(--success);
}

/* ───────── WIDGETS ───────── */

.widget {
    background: rgba(11,22,35,.72);

    border: 1px solid var(--border);

    border-radius: 18px;

    padding: 1.3rem;

    margin-bottom: 1rem;

    backdrop-filter: blur(14px);

    box-shadow: var(--shadow-soft);
}

.widget-title {
    font-family: 'Space Mono', monospace;

    font-size: .65rem;
    letter-spacing: .2em;
    text-transform: uppercase;

    color: var(--text-muted);

    margin-bottom: 1rem;
}

.profile-widget {
    text-align: center;
}

.profile-avatar {
    width: 68px;
    height: 68px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            var(--primary),
            #ff7a70
        );

    display: flex;
    align-items: center;
    justify-content: center;

    margin: 0 auto .8rem;

    font-size: 1.5rem;
    font-weight: 700;

    box-shadow: var(--shadow-red);
}

.profile-role {
    color: var(--text-muted);
}

.pstat {
    background: rgba(255,255,255,.03);

    border: 1px solid rgba(255,255,255,.04);

    border-radius: 12px;

    padding: .7rem;
}

.pstat-val {
    font-family: 'Bebas Neue', cursive;

    font-size: 1.5rem;

    color: white;
}

.pstat-lbl {
    font-size: .65rem;

    color: var(--text-muted);

    font-family: 'Space Mono', monospace;
}

/* ───────── ACTIVITY ───────── */

.activity-item {
    display: flex;
    gap: .8rem;

    padding: .8rem 0;

    border-bottom: 1px solid rgba(255,255,255,.04);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: var(--primary);

    margin-top: 6px;

    box-shadow: 0 0 10px rgba(255,90,95,.5);
}

.activity-text {
    font-size: .82rem;

    color: var(--text-soft);

    line-height: 1.5;
}

.activity-time {
    font-size: .7rem;

    color: var(--text-muted);

    margin-top: 3px;

    font-family: 'Space Mono', monospace;
}

/* ───────── PROGRESS ───────── */

.progress-bar {
    height: 6px;

    background: rgba(255,255,255,.05);

    border-radius: 20px;

    overflow: hidden;

    margin-top: .4rem;
}

.progress-fill {
    height: 100%;

    border-radius: 20px;

    background:
        linear-gradient(
            90deg,
            var(--primary),
            var(--accent)
        );
}

/* ───────── FAB ───────── */

.fab {
    position: fixed;

    right: 2rem;
    bottom: 2rem;

    width: 58px;
    height: 58px;

    border: none;
    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            var(--primary),
            #ff7a70
        );

    color: white;

    font-size: 1.5rem;

    cursor: pointer;

    box-shadow: 0 14px 35px rgba(255,90,95,.35);

    transition: .3s ease;

    z-index: 50;
}

.fab:hover {
    transform: scale(1.08) rotate(90deg);
}

/* ───────── RESPONSIVE ───────── */

@media (max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(2,1fr);
    }

    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .sidebar {
        display: none;
    }

    .main {
        margin-left: 0;
        padding: 1rem;
    }

    .welcome-banner {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}
</style>
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