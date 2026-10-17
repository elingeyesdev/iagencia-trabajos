@php
    $user = auth()->user();
    $user->tokens()->where('name', 'dashboard-trabajador')->delete();
    $token = $user->createToken('dashboard-trabajador')->plainTextToken;
    $me = $user->only(['cedula', 'nombre', 'email', 'telefono', 'ciudad', 'direccion']);
    $ini = mb_strtoupper(mb_substr($user->nombre ?? 'T', 0, 2));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="api-token" content="{{ $token }}">
<title>RED-WORK — Trabajador</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{--bg-main:#071018;--bg-card:rgba(14,24,38,.88);--primary:#FF5A5F;--primary-soft:rgba(255,90,95,.12);--accent:#2DD4BF;--success:#4ADE80;--warning:#F59E0B;--text-main:#F8FAFC;--text-soft:#94A3B8;--text-muted:#64748B;--border:rgba(255,255,255,.06);--shadow-red:0 10px 30px rgba(255,90,95,.18);--shadow-soft:0 10px 40px rgba(0,0,0,.35)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:radial-gradient(circle at top left,rgba(255,90,95,.08),transparent 35%),radial-gradient(circle at bottom right,rgba(45,212,191,.06),transparent 30%),var(--bg-main);color:var(--text-main);min-height:100vh;overflow-x:hidden}
button{font-family:inherit}
.muted{color:var(--text-muted);font-size:.8rem}

/* TOPBAR */
.topbar{position:fixed;top:0;left:0;right:0;z-index:100;height:68px;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;background:rgba(7,16,24,.72);backdrop-filter:blur(18px);border-bottom:1px solid var(--border)}
.topbar-left{display:flex;align-items:center;gap:1rem}
.menu-btn{display:none;background:none;border:1px solid var(--border);color:white;border-radius:8px;padding:.35rem .6rem;font-size:1.1rem;cursor:pointer}
.logo{font-family:'Bebas Neue',cursive;font-size:2rem;letter-spacing:.1em;background:linear-gradient(90deg,#ff4d6d,#ff5a5f 45%,#ff7a7f);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:flex;align-items:center;gap:.4rem;user-select:none}
.logo-icon{-webkit-text-fill-color:initial}
.topbar-actions{display:flex;align-items:center;gap:1rem}
.rol-badge{font-family:'Space Mono',monospace;font-size:.62rem;letter-spacing:.12em;text-transform:uppercase;padding:.35rem .8rem;border-radius:20px;background:var(--primary-soft);border:1px solid rgba(255,90,95,.25);color:var(--primary)}
.avatar-btn{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#FF7B72);border:2px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-weight:700;cursor:pointer;transition:.25s;box-shadow:var(--shadow-red)}
.avatar-btn:hover{transform:translateY(-2px)}
.dropdown{position:relative}
.dropdown-menu{display:none;position:absolute;right:0;top:calc(100% + 12px);min-width:190px;background:rgba(11,22,35,.95);backdrop-filter:blur(16px);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow-soft)}
.dropdown.open .dropdown-menu{display:block}
.dropdown-item{display:flex;align-items:center;gap:.75rem;padding:.85rem 1rem;color:var(--text-soft);text-decoration:none;font-size:.85rem;border-bottom:1px solid rgba(255,255,255,.03);transition:.2s;cursor:pointer;width:100%;background:none;border-left:0;border-right:0;border-top:0;text-align:left}
.dropdown-item:hover{background:rgba(255,255,255,.03);color:white}
.dropdown-item.danger:hover{background:var(--primary-soft)}

/* SIDEBAR */
.layout{display:flex;padding-top:68px;min-height:100vh}
.sidebar{width:230px;position:fixed;top:68px;left:0;bottom:0;z-index:90;background:rgba(8,14,22,.92);backdrop-filter:blur(18px);border-right:1px solid var(--border);padding:1.5rem 0;display:flex;flex-direction:column;transition:transform .25s}
.nav-section{padding:0 1rem;margin-bottom:1rem}
.nav-label{font-family:'Space Mono',monospace;font-size:.6rem;letter-spacing:.22em;text-transform:uppercase;color:var(--text-muted);margin-bottom:.7rem;padding-left:.5rem}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.75rem .9rem;border-radius:12px;color:var(--text-soft);text-decoration:none;font-size:.88rem;font-weight:500;transition:.25s;margin-bottom:4px;cursor:pointer;border:1px solid transparent}
.nav-item:hover{background:rgba(255,255,255,.03);color:white;transform:translateX(3px)}
.nav-item.active{background:linear-gradient(90deg,rgba(255,90,95,.15),rgba(45,212,191,.06));border-color:rgba(255,255,255,.05);color:white}
.badge{margin-left:auto;background:linear-gradient(135deg,var(--primary),#ff7a70);color:white;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:30px;font-family:'Space Mono',monospace}
.badge:empty{display:none}
.sidebar-footer{margin-top:auto;padding:1rem;border-top:1px solid var(--border)}
.btn-logout{width:100%;display:flex;align-items:center;gap:.7rem;padding:.75rem 1rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;color:var(--text-soft);cursor:pointer;transition:.25s;font-size:.88rem}
.btn-logout:hover{background:var(--primary-soft);color:white}

/* MAIN / PANELS */
.main{margin-left:230px;flex:1;padding:2rem 2.5rem;min-width:0}
.panel{display:none}.panel.active{display:block}
.page-title,.section-title{font-family:'Bebas Neue',cursive;font-size:1.6rem;letter-spacing:.08em;color:white;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.8rem;margin-bottom:1rem}
.page-header .page-title{margin-bottom:0}
.link{color:var(--accent);font-size:.8rem;cursor:pointer;margin-left:auto;font-family:'DM Sans',sans-serif;letter-spacing:0}

.welcome-banner{position:relative;overflow:hidden;background:linear-gradient(135deg,rgba(255,90,95,.08),rgba(45,212,191,.04));border:1px solid var(--border);border-radius:24px;padding:2.2rem 2.5rem;margin-bottom:2rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;box-shadow:var(--shadow-soft)}
.welcome-title{font-family:'Bebas Neue',cursive;font-size:2.8rem;letter-spacing:.05em;background:linear-gradient(90deg,#fff,#FF8DA1 45%,#FF5A5F);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.welcome-sub{color:var(--text-soft);margin-top:.45rem}
.disponibilidad{display:flex;align-items:center;gap:.5rem;border-radius:20px;padding:.5rem 1rem;font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;border:1px solid;transition:.2s;white-space:nowrap}
.disponibilidad.on{background:rgba(74,222,128,.1);border-color:rgba(74,222,128,.25);color:var(--success)}
.disponibilidad.off{background:rgba(148,163,184,.1);border-color:rgba(148,163,184,.25);color:var(--text-soft)}
.disponibilidad.new{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.3);color:var(--warning)}
.dot{width:8px;height:8px;border-radius:50%;background:currentColor;box-shadow:0 0 8px currentColor}
.on .dot{animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}

.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}
.stat-card{position:relative;overflow:hidden;background:var(--bg-card);border:1px solid var(--border);border-radius:18px;padding:1.3rem 1.4rem;box-shadow:var(--shadow-soft);transition:.25s}
.stat-card:hover{transform:translateY(-4px)}
.stat-card::before{content:'';position:absolute;left:0;right:0;bottom:0;height:3px;background:var(--bar-color)}
.stat-label{font-family:'Space Mono',monospace;font-size:.62rem;letter-spacing:.15em;text-transform:uppercase;color:var(--text-muted);margin-bottom:.5rem}
.stat-value{font-family:'Bebas Neue',cursive;font-size:2.4rem;color:white}
.stat-delta{margin-top:.35rem;color:var(--accent);font-size:.75rem}
.stat-icon{position:absolute;top:1rem;right:1rem;opacity:.2;font-size:1.6rem}
.content-grid{display:grid;grid-template-columns:1fr 320px;gap:1.5rem}

/* FILTROS */
.toolbar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center}
.filtro-btn{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;padding:.45rem .9rem;border-radius:20px;border:1px solid var(--border);background:transparent;color:var(--text-muted);cursor:pointer;transition:.2s}
.filtro-btn:hover,.filtro-btn.active{background:var(--primary-soft);border-color:rgba(255,90,95,.3);color:var(--primary)}
.input,.select,.textarea{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;color:white;padding:.6rem .9rem;font-family:inherit;font-size:.88rem;width:100%;outline:none;transition:.2s}
.input:focus,.select:focus,.textarea:focus{border-color:rgba(45,212,191,.5)}
.select option{background:#0B1623}
.textarea{resize:vertical;min-height:80px}
.toolbar .input{max-width:260px}.toolbar .select{max-width:200px}

/* JOBS */
.jobs-list{display:flex;flex-direction:column;gap:.9rem}
.job-card{background:rgba(11,22,35,.72);border:1px solid var(--border);border-radius:16px;padding:1.15rem 1.3rem;display:flex;align-items:center;gap:1rem;transition:.25s;cursor:pointer}
.job-card:hover{transform:translateX(4px);border-color:rgba(45,212,191,.25);background:rgba(14,24,38,.95)}
.job-icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,rgba(255,90,95,.18),rgba(45,212,191,.12));border:1px solid rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.job-info{flex:1;min-width:0}
.job-title{font-weight:600;color:white;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.job-meta{color:var(--text-muted);font-size:.78rem;margin-top:4px}
.job-side{display:flex;flex-direction:column;align-items:flex-end;gap:.4rem}
.job-price{font-family:'Bebas Neue',cursive;font-size:1.4rem;color:var(--accent)}
.tag{display:inline-flex;padding:3px 8px;border-radius:6px;margin:6px 6px 0 0;font-size:.65rem;font-weight:700;font-family:'Space Mono',monospace}
.tag-recurrent{background:rgba(45,212,191,.12);color:var(--accent)}
.tag-temp{background:var(--primary-soft);color:var(--primary)}
.tag-new{background:rgba(74,222,128,.12);color:var(--success)}
.btn-aplicar{background:rgba(45,212,191,.12);border:1px solid rgba(45,212,191,.25);color:var(--accent);padding:.45rem 1rem;border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap}
.btn-aplicar:hover{background:rgba(45,212,191,.22);border-color:var(--accent)}
.empty{text-align:center;color:var(--text-muted);padding:2rem 1rem;border:1px dashed var(--border);border-radius:14px;font-size:.88rem}

/* WIDGETS */
.widget{background:rgba(11,22,35,.72);border:1px solid var(--border);border-radius:18px;padding:1.3rem;margin-bottom:1rem;box-shadow:var(--shadow-soft)}
.widget-title{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:var(--text-muted);margin-bottom:1rem}
.profile-widget{text-align:center}
.profile-avatar{width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#ff7a70);display:flex;align-items:center;justify-content:center;margin:0 auto .8rem;font-size:1.5rem;font-weight:700;box-shadow:var(--shadow-red)}
.profile-name{font-weight:600;color:white}
.profile-role{color:var(--text-muted);font-size:.82rem;margin-bottom:.5rem}
.stars{color:var(--warning);letter-spacing:2px}
.profile-stats{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.75rem}
.pstat{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.04);border-radius:12px;padding:.7rem}
.pstat-val{font-family:'Bebas Neue',cursive;font-size:1.5rem;color:white}
.pstat-lbl{font-size:.65rem;color:var(--text-muted);font-family:'Space Mono',monospace}
.skills{display:flex;flex-wrap:wrap;gap:.4rem}
.skill{background:var(--primary-soft);border:1px solid rgba(255,90,95,.2);color:#ffb3b5;border-radius:20px;padding:.25rem .7rem;font-size:.75rem}
.rating-item{display:flex;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid rgba(255,255,255,.04)}
.rating-item:last-child{border-bottom:none}
.rating-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,rgba(255,90,95,.3),rgba(45,212,191,.2));display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0}
.rating-info{flex:1;min-width:0}
.rating-name{font-size:.85rem;color:white;font-weight:500}
.rating-comment{font-size:.75rem;color:var(--text-muted);margin-top:2px}

/* TABLAS / CHIPS / BOTONES */
.table-wrap{background:rgba(11,22,35,.72);border:1px solid var(--border);border-radius:16px;overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.85rem}
th{font-family:'Space Mono',monospace;font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);text-align:left;padding:.9rem 1rem;border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:.9rem 1rem;border-bottom:1px solid rgba(255,255,255,.03);color:var(--text-soft);vertical-align:middle}
td.name{color:white;font-weight:600}
tr:last-child td{border-bottom:none}
.chip{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.65rem;font-weight:700;font-family:'Space Mono',monospace;white-space:nowrap}
.chip-green{background:rgba(74,222,128,.12);color:var(--success)}
.chip-red{background:var(--primary-soft);color:var(--primary)}
.chip-yellow{background:rgba(245,158,11,.12);color:var(--warning)}
.chip-blue{background:rgba(45,212,191,.12);color:var(--accent)}
.chip-gray{background:rgba(148,163,184,.12);color:var(--text-soft)}
.btn{border:1px solid var(--border);background:rgba(255,255,255,.04);color:white;padding:.5rem 1rem;border-radius:10px;font-size:.8rem;font-weight:600;cursor:pointer;transition:.2s}
.btn:hover{background:rgba(255,255,255,.08)}
.btn[disabled]{opacity:.5;cursor:not-allowed}
.btn-accent{background:rgba(45,212,191,.15);border-color:rgba(45,212,191,.3);color:var(--accent)}
.btn-danger{background:var(--primary-soft);border-color:rgba(255,90,95,.3);color:var(--primary)}
.btn-gold{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:var(--warning)}

/* FORMS / PERFIL */
.card{background:rgba(11,22,35,.72);border:1px solid var(--border);border-radius:18px;padding:1.5rem;margin-bottom:1.2rem}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{margin-bottom:.9rem}.form-group.full{grid-column:1/-1}
.form-label{display:block;font-family:'Space Mono',monospace;font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);margin-bottom:.4rem}
.check{display:flex;align-items:center;gap:.6rem;font-size:.88rem;color:var(--text-soft);cursor:pointer}
.check input{accent-color:var(--accent);width:16px;height:16px}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal{background:#0B1623;border:1px solid var(--border);border-radius:20px;padding:1.8rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-soft)}
.modal h3{font-family:'Bebas Neue',cursive;font-size:1.7rem;letter-spacing:.05em;color:white;margin-bottom:.3rem}
.modal-actions{display:flex;gap:.6rem;justify-content:flex-end;margin-top:1.2rem}
.offer-desc{color:var(--text-soft);font-size:.9rem;line-height:1.55;margin:1rem 0;white-space:pre-line}
.offer-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1rem}
.offer-grid div{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.04);border-radius:10px;padding:.6rem .8rem;font-size:.85rem;color:white}
.offer-grid span{display:block;font-family:'Space Mono',monospace;font-size:.58rem;letter-spacing:.12em;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px}
.star-pick{display:flex;gap:.3rem;margin:.6rem 0 1rem}
.star-pick button{background:none;border:none;font-size:2rem;color:#334155;cursor:pointer;transition:.15s}
.star-pick button.on{color:var(--warning)}

@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr)}.content-grid{grid-template-columns:1fr}}
@media(max-width:900px){.menu-btn{display:block}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:none}.main{margin-left:0;padding:1.2rem}.topbar{padding:0 1rem}.rol-badge{display:none}}
@media(max-width:700px){.welcome-banner{flex-direction:column;align-items:flex-start;padding:1.5rem}.welcome-title{font-size:2.2rem}.form-grid{grid-template-columns:1fr}.job-card{flex-wrap:wrap}.toolbar .input,.toolbar .select{max-width:none}}
</style>
</head>
<body>

<nav class="topbar">
    <div class="topbar-left">
        <button class="menu-btn" data-action="menu">☰</button>
        <div class="logo"><span class="logo-icon">🔧</span> RED-WORK</div>
    </div>
    <div class="topbar-actions">
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
            <a class="nav-item" data-panel="buscar" data-filtro="todos"><span>🔍</span> Buscar Trabajos <span class="badge" id="badge-buscar"></span></a>
            <a class="nav-item" data-panel="buscar" data-filtro="recurrente"><span>🔄</span> Recurrentes</a>
            <a class="nav-item" data-panel="buscar" data-filtro="temporal"><span>⏳</span> Temporales</a>
        </div>
        <div class="nav-section">
            <div class="nav-label">Mi Actividad</div>
            <a class="nav-item" data-panel="postulaciones"><span>📋</span> Mis Postulaciones <span class="badge" id="badge-post"></span></a>
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
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Oferta</th><th>Contratador</th><th>Pago</th><th>Fecha</th><th>Estado</th><th>Contratación</th><th>Acciones</th></tr></thead>
                    <tbody id="tbody-post"><tr><td colspan="7" class="muted" style="text-align:center">Cargando…</td></tr></tbody>
                </table>
            </div>
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

<script>
const TOKEN = document.querySelector('meta[name="api-token"]').content;
const ME = @json($me);
const S = { pubs: [], post: [], califs: [], postulante: null, promedio: null, totalCalif: 0, completados: 0, filtro: 'todos', ofertaActual: null };
const $ = id => document.getElementById(id);

/* ── Utilidades ── */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const bs = n => 'Bs ' + Number(n || 0).toLocaleString('es-BO', { maximumFractionDigits: 2 });
const stars = n => '★'.repeat(n) + '☆'.repeat(5 - n);
const parseDate = s => { if (!s) return null; const [y, m, d] = String(s).slice(0, 10).split('-').map(Number); return new Date(y, m - 1, d); };
const fecha = s => { const d = parseDate(s); return d ? d.toLocaleDateString('es-BO') : '—'; };
function hace(s) {
    const d = parseDate(s); if (!d) return '';
    const dias = Math.floor((new Date().setHours(0,0,0,0) - d) / 86400000);
    if (dias <= 0) return 'hoy'; if (dias === 1) return 'ayer'; if (dias < 30) return 'hace ' + dias + 'd';
    return 'hace ' + Math.floor(dias / 30) + ' mes(es)';
}
const esNuevo = s => { const d = parseDate(s); return d && (new Date().setHours(0,0,0,0) - d) / 86400000 <= 2; };
function icono(cat) {
    const n = (cat || '').toLowerCase();
    const map = [['plom','🔧'],['elect','⚡'],['pint','🎨'],['alba','🏗️'],['constr','🏗️'],['limp','🧹'],['carpint','🪚'],['jard','🌿'],['mecan','🔩'],['cerraj','🔑'],['transp','🚚'],['cocin','🍳']];
    for (const [k, v] of map) if (n.includes(k)) return v;
    return '🛠️';
}
const chipPost = e => e === 'aceptada' ? 'chip-green' : e === 'rechazada' ? 'chip-red' : 'chip-yellow';
const chipContr = e => e === 'finalizada' ? 'chip-green' : e === 'cancelada' ? 'chip-red' : 'chip-blue';

function toast(msg, tipo = 'success') {
    const t = document.createElement('div');
    const ok = tipo === 'success';
    t.style.cssText = 'position:fixed;bottom:2rem;right:2rem;z-index:9999;padding:.9rem 1.4rem;border-radius:12px;font-size:.88rem;font-weight:600;backdrop-filter:blur(12px);max-width:90vw;' +
        (ok ? 'background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.3);color:#4ADE80;' : 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#ff7070;');
    t.textContent = (ok ? '✅ ' : '❌ ') + msg;
    document.body.appendChild(t); setTimeout(() => t.remove(), 3500);
}

async function api(url, method = 'GET', body = null) {
    const res = await fetch(url, {
        method,
        headers: Object.assign({ 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }, body ? { 'Content-Type': 'application/json' } : {}),
        body: body ? JSON.stringify(body) : null
    });
    let data = null; try { data = await res.json(); } catch (e) {}
    if (!res.ok) {
        const err = (data && data.errors) ? Object.values(data.errors)[0][0] : (data && data.message) || 'Error de conexión.';
        throw new Error(err);
    }
    return data;
}

/* ── Navegación ── */
function showPanel(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + name));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const nav = [...document.querySelectorAll('.nav-item')].find(n => n.dataset.panel === name && (name !== 'buscar' || n.dataset.filtro === S.filtro))
             || document.querySelector('.nav-item[data-panel="' + name + '"]');
    nav && nav.classList.add('active');
    $('sidebar').classList.remove('open'); $('dropdown').classList.remove('open');
    history.replaceState(null, '', '#' + name); window.scrollTo(0, 0);
}
function setFiltro(f) {
    S.filtro = f;
    document.querySelectorAll('#filtros .filtro-btn').forEach(b => b.classList.toggle('active', b.dataset.filtro === f));
    renderBuscar();
}
const openModal = id => $(id).classList.add('open');
const closeModal = id => $(id).classList.remove('open');

/* ── Carga de datos ── */
async function cargar() {
    try {
        const [dash, post] = await Promise.all([api('/api/trabajador/dashboard'), api('/api/trabajador/postulaciones')]);
        const d = dash.data;
        S.pubs = d.publicaciones || []; S.califs = d.calificaciones || []; S.postulante = d.postulante_info || null;
        S.promedio = d.promedio_calificacion; S.totalCalif = d.total_calificaciones || 0; S.completados = d.completados_count || 0;
        S.post = post.data || [];
        renderTodo();
    } catch (e) { toast(e.message, 'error'); }
}
const estadoDe = id => (S.post.find(p => p.id_labor == id) || {}).estado;
const disponibles = () => S.pubs.filter(p => !estadoDe(p.id_labor));

/* ── Render ── */
function jobCard(p) {
    const est = estadoDe(p.id_labor);
    const tag = String(p.tipo).toLowerCase() === 'recurrente' ? '<span class="tag tag-recurrent">RECURRENTE</span>' : '<span class="tag tag-temp">TEMPORAL</span>';
    const nuevo = esNuevo(p.fecha_publicacion) ? '<span class="tag tag-new">NUEVO</span>' : '';
    const accion = est ? `<span class="chip ${chipPost(est)}">${esc(est).toUpperCase()}</span>`
                       : `<button class="btn-aplicar" data-action="oferta" data-id="${p.id_labor}">Aplicar →</button>`;
    return `<div class="job-card" data-action="oferta" data-id="${p.id_labor}">
        <div class="job-icon">${icono(p.categoria && p.categoria.nombre_categoria)}</div>
        <div class="job-info">
            <div class="job-title">${esc(p.titulo)}</div>
            <div class="job-meta">📍 ${esc(p.lugar)} · ${hace(p.fecha_publicacion)}${p.categoria ? ' · ' + esc(p.categoria.nombre_categoria) : ''}</div>
            ${tag}${nuevo}
        </div>
        <div class="job-side"><div class="job-price">${bs(p.pago)}</div>${accion}</div>
    </div>`;
}

function renderBuscar() {
    const q = $('f-busqueda').value.trim().toLowerCase();
    const cat = $('f-categoria').value;
    let list = S.pubs.filter(p => {
        if (cat && String(p.id_categoria) !== cat) return false;
        if (q && !(`${p.titulo} ${p.lugar} ${p.descripcion}`.toLowerCase().includes(q))) return false;
        if (S.filtro === 'recurrente' || S.filtro === 'temporal') return String(p.tipo).toLowerCase() === S.filtro;
        if (S.filtro === 'ciudad') return ME.ciudad && String(p.lugar).toLowerCase().includes(String(ME.ciudad).toLowerCase());
        return true;
    });
    if (S.filtro === 'mejor') list = [...list].sort((a, b) => b.pago - a.pago);
    let vacio = 'No hay trabajos que coincidan con tu búsqueda.';
    if (S.filtro === 'ciudad' && !ME.ciudad) vacio = 'Configura tu ciudad en Mi Perfil para usar este filtro.';
    $('lista-jobs').innerHTML = list.length ? list.map(jobCard).join('') : `<div class="empty">${vacio}</div>`;
}

function renderCategorias() {
    const sel = $('f-categoria'), actual = sel.value, vistos = new Map();
    S.pubs.forEach(p => p.categoria && vistos.set(String(p.id_categoria), p.categoria.nombre_categoria));
    sel.innerHTML = '<option value="">Todas las categorías</option>' + [...vistos].map(([id, n]) => `<option value="${id}">${esc(n)}</option>`).join('');
    sel.value = actual;
}

function renderStats() {
    const disp = disponibles().length, pend = S.post.filter(p => p.estado === 'pendiente').length;
    const acept = S.post.filter(p => p.estado === 'aceptada').length;
    $('st-disp').textContent = disp;
    $('st-disp-d').textContent = S.pubs.filter(p => esNuevo(p.fecha_publicacion)).length + ' nuevos recientes';
    $('st-post').textContent = S.post.length;
    $('st-post-d').textContent = pend + ' en revisión';
    $('st-comp').textContent = S.completados;
    $('st-comp-d').textContent = S.post.filter(p => p.contratacion && p.contratacion.estado === 'activa').length + ' en curso';
    $('st-cal').textContent = S.promedio ?? '—';
    $('st-cal-d').textContent = S.totalCalif ? S.totalCalif + ' calificación(es)' : 'Aún sin calificaciones';
    $('badge-buscar').textContent = disp || '';
    $('badge-post').textContent = pend || '';
    $('w-comp').textContent = S.completados;
    $('w-acep').textContent = S.post.length ? Math.round(acept / S.post.length * 100) + '%' : '—';
    $('w-stars').innerHTML = S.promedio ? `<span class="stars">${stars(Math.round(S.promedio))}</span> <span class="muted">${S.promedio} / 5</span>` : '<span class="muted">Sin calificaciones</span>';
}

function ratingItem(c, conFecha) {
    const n = (c.calificador && c.calificador.nombre) || 'Usuario';
    return `<div class="rating-item"><div class="rating-avatar">${esc(n.slice(0, 2).toUpperCase())}</div>
        <div class="rating-info"><div class="rating-name">${esc(n)}</div><div class="rating-comment">${c.comentario ? '"' + esc(c.comentario) + '"' : 'Sin comentario'}</div>${conFecha ? `<div class="muted">${fecha(c.fecha)}</div>` : ''}</div>
        <div class="stars">${stars(c.puntuacion)}</div></div>`;
}

function renderWidgets() {
    const h = S.postulante && S.postulante.habilidades ? S.postulante.habilidades.split(',').map(x => x.trim()).filter(Boolean) : [];
    $('w-skills').innerHTML = h.length ? `<div class="skills">${h.map(x => `<span class="skill">${esc(x)}</span>`).join('')}</div>`
        : '<div class="muted">Agrega tus habilidades en <a class="link" style="margin:0" data-panel="perfil">Mi Perfil</a> para destacar ante los contratadores.</div>';
    $('w-califs').innerHTML = S.califs.length ? S.califs.slice(0, 3).map(c => ratingItem(c)).join('') : '<div class="muted">Aún no tienes calificaciones.</div>';

    const disp = $('disp'), txt = $('disp-txt');
    if (!S.postulante) { disp.className = 'disponibilidad new'; txt.textContent = 'Completa tu perfil'; }
    else if (Number(S.postulante.estado) === 1) { disp.className = 'disponibilidad on'; txt.textContent = 'Disponible'; }
    else { disp.className = 'disponibilidad off'; txt.textContent = 'No disponible'; }
}

function renderPostulaciones() {
    if (!S.post.length) { $('tbody-post').innerHTML = '<tr><td colspan="7" class="muted" style="text-align:center">Aún no te has postulado a ningún trabajo.</td></tr>'; return; }
    $('tbody-post').innerHTML = S.post.map(p => {
        const pub = p.publicacion || {}, c = p.contratacion;
        let contr = '—', acc = '—';
        if (c) {
            contr = `<span class="chip ${chipContr(c.estado)}">${esc(c.estado).toUpperCase()}</span>`;
            if (c.estado === 'finalizada') {
                const ya = (c.calificaciones || []).some(x => String(x.cedula_calificador) === String(ME.cedula));
                acc = ya ? '<span class="muted">Ya calificaste ✓</span>'
                         : `<button class="btn btn-gold" data-action="calificar" data-id="${c.id_contratacion}" data-nombre="${esc((pub.usuario && pub.usuario.nombre) || 'el contratador')}">⭐ Calificar</button>`;
            }
        } else if (p.estado === 'pendiente') {
            acc = `<button class="btn btn-danger" data-action="cancelar" data-id="${p.id_postulacion}">Cancelar</button>`;
        }
        return `<tr>
            <td class="name">${esc(pub.titulo || 'Oferta eliminada')}</td>
            <td>${esc((pub.usuario && pub.usuario.nombre) || '—')}</td>
            <td style="color:var(--accent);font-weight:600">${pub.pago != null ? bs(pub.pago) : '—'}</td>
            <td>${fecha(p.fecha_postulacion)}</td>
            <td><span class="chip ${chipPost(p.estado)}">${esc(p.estado).toUpperCase()}</span></td>
            <td>${contr}</td><td>${acc}</td></tr>`;
    }).join('');
}

function renderCalifs() {
    $('calif-resumen').innerHTML = S.totalCalif
        ? `<div style="display:flex;align-items:center;gap:1.2rem"><div class="stat-value" style="font-size:3rem">${S.promedio}</div><div><div class="stars" style="font-size:1.2rem">${stars(Math.round(S.promedio))}</div><div class="muted">${S.totalCalif} calificación(es) recibidas</div></div></div>`
        : '<div class="muted">Aún no has recibido calificaciones. Aparecerán cuando un contratador califique un trabajo finalizado.</div>';
    $('calif-lista').innerHTML = S.califs.length ? S.califs.map(c => ratingItem(c, true)).join('') : '<div class="muted">Nada que mostrar todavía.</div>';
}

function renderPerfilProfesional() {
    const p = S.postulante || {};
    $('pp-habilidades').value = p.habilidades || ''; $('pp-experiencia').value = p.experiencia || '';
    $('pp-disponibilidad').value = p.disponibilidad || ''; $('pp-tarifa').value = p.tarifa ?? '';
    $('pp-estado').checked = S.postulante ? Number(p.estado) === 1 : true;
}

function renderTodo() {
    const rec = disponibles().slice(0, 5);
    $('home-jobs').innerHTML = rec.length ? rec.map(jobCard).join('') : '<div class="empty">No hay trabajos nuevos por ahora. ¡Vuelve pronto!</div>';
    renderCategorias(); renderBuscar(); renderStats(); renderWidgets(); renderPostulaciones(); renderCalifs(); renderPerfilProfesional();
}

/* ── Acciones ── */
function abrirOferta(id) {
    const p = S.pubs.find(x => x.id_labor == id); if (!p) return;
    S.ofertaActual = p; const est = estadoDe(id);
    $('of-titulo').textContent = p.titulo;
    $('of-tags').innerHTML = (String(p.tipo).toLowerCase() === 'recurrente' ? '<span class="tag tag-recurrent">RECURRENTE</span>' : '<span class="tag tag-temp">TEMPORAL</span>') + (est ? `<span class="chip ${chipPost(est)}" style="margin-top:6px">${esc(est).toUpperCase()}</span>` : '');
    $('of-desc').textContent = p.descripcion;
    $('of-grid').innerHTML = [['Pago', bs(p.pago)], ['Lugar', p.lugar], ['Categoría', p.categoria ? p.categoria.nombre_categoria : '—'], ['Contratador', p.usuario ? p.usuario.nombre : '—'], ['Publicado', fecha(p.fecha_publicacion)]]
        .map(([k, v]) => `<div><span>${k}</span>${esc(v)}</div>`).join('');
    $('of-form').style.display = est ? 'none' : 'block';
    $('of-enviar').style.display = est ? 'none' : 'inline-block';
    $('of-mensaje').value = '';
    openModal('modal-oferta');
}

async function enviarPostulacion(btn) {
    btn.disabled = true;
    try {
        await api('/api/trabajador/postulaciones', 'POST', { id_labor: S.ofertaActual.id_labor, mensaje: $('of-mensaje').value.trim() || null });
        toast('Postulación enviada.'); closeModal('modal-oferta'); await cargar();
    } catch (e) { toast(e.message, 'error'); } finally { btn.disabled = false; }
}

async function cancelarPostulacion(id) {
    if (!confirm('¿Cancelar esta postulación?')) return;
    try { await api('/api/trabajador/postulaciones/' + id, 'DELETE'); toast('Postulación cancelada.'); await cargar(); }
    catch (e) { toast(e.message, 'error'); }
}

function abrirCalificar(id, nombre) {
    $('cal-id').value = id; $('cal-nombre').textContent = nombre; $('cal-comentario').value = '';
    setPuntos(0); openModal('modal-calificar');
}
function setPuntos(n) {
    $('cal-puntos').value = n;
    document.querySelectorAll('#star-pick button').forEach(b => b.classList.toggle('on', Number(b.dataset.star) <= n));
}
async function enviarCalificacion() {
    const puntos = Number($('cal-puntos').value);
    if (!puntos) return toast('Selecciona una puntuación.', 'error');
    try {
        await api('/api/trabajador/calificaciones', 'POST', { id_contratacion: Number($('cal-id').value), puntuacion: puntos, comentario: $('cal-comentario').value.trim() || null });
        toast('Calificación enviada.'); closeModal('modal-calificar'); await cargar();
    } catch (e) { toast(e.message, 'error'); }
}

async function guardarDatos() {
    try {
        const r = await api('/api/perfil', 'PUT', { nombre: $('p-nombre').value.trim(), email: $('p-email').value.trim(), telefono: $('p-telefono').value.trim() || null, ciudad: $('p-ciudad').value.trim() || null, direccion: $('p-direccion').value.trim() || null });
        Object.assign(ME, r.user);
        document.querySelectorAll('.u-nombre').forEach(e => e.textContent = ME.nombre);
        document.querySelectorAll('.u-ciudad').forEach(e => e.textContent = ME.ciudad || 'Sin ciudad');
        const ini = (ME.nombre || 'T').slice(0, 2).toUpperCase();
        $('avatar-top').textContent = ini; $('avatar-widget').textContent = ini;
        toast('Datos actualizados.'); renderBuscar();
    } catch (e) { toast(e.message, 'error'); }
}

async function guardarProfesional(extra) {
    const body = { habilidades: $('pp-habilidades').value.trim(), experiencia: $('pp-experiencia').value.trim() || null, disponibilidad: $('pp-disponibilidad').value.trim() || null, tarifa: $('pp-tarifa').value === '' ? null : $('pp-tarifa').value, estado: $('pp-estado').checked ? 1 : 0 };
    if (!body.habilidades && S.postulante) delete body.habilidades;
    try {
        const r = await api('/api/trabajador/perfil-postulante', 'PUT', Object.assign(body, extra || {}));
        S.postulante = r.data; toast('Perfil profesional guardado.'); renderWidgets(); renderPerfilProfesional();
    } catch (e) { toast(e.message, 'error'); }
}

async function toggleDisponibilidad() {
    if (!S.postulante) { toast('Primero completa tu perfil profesional.', 'error'); return showPanel('perfil'); }
    try {
        const r = await api('/api/trabajador/perfil-postulante', 'PUT', { estado: Number(S.postulante.estado) === 1 ? 0 : 1 });
        S.postulante = r.data; renderWidgets(); renderPerfilProfesional();
        toast(Number(S.postulante.estado) === 1 ? 'Ahora estás disponible.' : 'Marcado como no disponible.');
    } catch (e) { toast(e.message, 'error'); }
}

/* ── Eventos (delegación) ── */
document.addEventListener('click', ev => {
    const t = ev.target;
    if (!t.closest('#dropdown')) $('dropdown').classList.remove('open');
    if (t.classList.contains('modal-overlay')) return t.classList.remove('open');

    const star = t.closest('[data-star]'); if (star) return setPuntos(Number(star.dataset.star));

    const nav = t.closest('[data-panel]');
    if (nav) { if (nav.dataset.filtro) setFiltro(nav.dataset.filtro); return showPanel(nav.dataset.panel); }

    const fb = t.closest('#filtros [data-filtro]'); if (fb) return setFiltro(fb.dataset.filtro);

    const el = t.closest('[data-action]'); if (!el) return;
    const id = el.dataset.id;
    switch (el.dataset.action) {
        case 'menu': $('sidebar').classList.toggle('open'); break;
        case 'dropdown': $('dropdown').classList.toggle('open'); break;
        case 'oferta': abrirOferta(id); break;
        case 'cerrar': closeModal(id); break;
        case 'enviar-postulacion': enviarPostulacion(el); break;
        case 'cancelar': cancelarPostulacion(id); break;
        case 'calificar': abrirCalificar(id, el.dataset.nombre); break;
        case 'enviar-calificacion': enviarCalificacion(); break;
        case 'guardar-datos': guardarDatos(); break;
        case 'guardar-profesional': guardarProfesional(); break;
        case 'disponibilidad': toggleDisponibilidad(); break;
    }
});
$('f-busqueda').addEventListener('input', renderBuscar);
$('f-categoria').addEventListener('change', renderBuscar);

const inicial = location.hash.replace('#', '');
if (document.getElementById('panel-' + inicial)) showPanel(inicial);
cargar();
</script>
</body>
</html>