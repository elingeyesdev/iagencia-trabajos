<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <script>/* Tema antes de pintar (sin parpadeo). Ver resources/js/themes.js */try{document.documentElement.setAttribute('data-theme',localStorage.getItem('redwork-theme')==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — Administrador</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <meta name="api-token" content="{{ $token }}">

    @vite(['resources/css/admin-dashboard.css', 'resources/js/admin-dashboard.js', 'resources/css/themes.css', 'resources/js/themes.js'])
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

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">

    <!-- 1. Acceso y Seguridad -->
    <div class="nav-group open" id="group-seguridad">
        <div class="nav-group-header" onclick="toggleGroup('seguridad')">
            <div class="nav-group-title"><span class="nav-group-icon">🔐</span> Acceso y Seguridad</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item active" onclick="showPanel('home')" href="#"><span class="nav-item-icon">🏠</span> Inicio</a>
            <a class="nav-item" onclick="showPanel('usuarios')" href="#"><span class="nav-item-icon">👥</span> Administrar Usuario</a>
            <a class="nav-item" onclick="showPanel('roles')" href="#"><span class="nav-item-icon">🛡️</span> Administrar Rol</a>
            <a class="nav-item" onclick="showPanel('asignar-rol')" href="#"><span class="nav-item-icon">🔗</span> Asignar Rol</a>
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
            <a class="nav-item" onclick="showPanel('categorias')" href="#"><span class="nav-item-icon">🏢</span>Administrar Categoría</a>
            <a class="nav-item" onclick="showPanel('publicaciones')" href="#"><span class="nav-item-icon">💼</span> Administrar Publicacion</a>
            <a class="nav-item" onclick="showPanel('postulantes')" href="#"><span class="nav-item-icon">🧰</span> Administrar Postulante</a>
            <a class="nav-item" onclick="showPanel('calificaciones-trans')" href="#"><span class="nav-item-icon">⭐</span>Administrar Calificacion</a>
            <!-- <a class="nav-item" onclick="showPanel('comision')" href="#"><span class="nav-item-icon">💲</span> Comisión</a> -->
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- 3. Transaccional (CONECTADO CON LA API) -->
    <div class="nav-group" id="group-trans">
        <div class="nav-group-header" onclick="toggleGroup('trans')">
            <div class="nav-group-title"><span class="nav-group-icon">🔄</span> Transaccional</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item" onclick="showPanel('pagos-trans')" href="#"><span class="nav-item-icon">💳</span> Administrar Pagos</a>
            <a class="nav-item" onclick="showPanel('postulaciones-trans')" href="#"><span class="nav-item-icon">📝</span> Administrar Postulaciones</a>
            <a class="nav-item" onclick="showPanel('contrataciones-trans')" href="#"><span class="nav-item-icon">🤝</span> Administrar Contrataciones</a>
            
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

    @if (\Illuminate\Support\Facades\Route::has('admin.kpis'))
    <div class="sidebar-divider"></div>

    <!-- 5. Business Intelligence -->
    <div class="nav-group" id="group-bi">
        <div class="nav-group-header" onclick="toggleGroup('bi')">
            <div class="nav-group-title"><span class="nav-group-icon">📊</span> Business Intelligence</div>
            <span class="nav-group-arrow">▶</span>
        </div>
        <div class="nav-group-items">
            <a class="nav-item" href="{{ route('admin.kpis') }}"><span class="nav-item-icon">📈</span> Dashboard de KPIs</a>
        </div>
    </div>
    @endif

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

        <!-- PANEL: HOME -->
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

            <!-- El sistema de KPIs está en su propia página: Business Intelligence → Dashboard de KPIs (route admin.kpis) -->

            <!-- Estadísticas auxiliares de contexto (no son KPIs estratégicos), alimentadas por GET /api/admin/kpis -->
            <div class="page-breadcrumb" style="margin-top:1.4rem;">Estadísticas auxiliares de contexto (estado actual, sin comparación)</div>
            <div class="stats-grid" id="kpis-grid" style="margin-top:.6rem;">
                <div class="stat-card" style="--bar-color:#2DD4BF;">
                    <div class="stat-icon">📢</div>
                    <div class="stat-label">Publicaciones activas</div>
                    <div class="stat-value" id="kpi-publicaciones-activas">…</div>
                    <div class="stat-delta">Fuente: API de indicadores</div>
                </div>
                <div class="stat-card" style="--bar-color:#4ADE80;">
                    <div class="stat-icon">🤝</div>
                    <div class="stat-label">Contrataciones activas</div>
                    <div class="stat-value" id="kpi-contrataciones-activas">…</div>
                    <div class="stat-delta">Fuente: API de indicadores</div>
                </div>
                <div class="stat-card" style="--bar-color:#F59E0B;">
                    <div class="stat-icon">💳</div>
                    <div class="stat-label">Pagos simulados confirmados (Bs) <span class="chip chip-yellow">simulado</span></div>
                    <div class="stat-value" id="kpi-pagos-confirmados">…</div>
                    <div class="stat-delta" id="kpi-generado">Registros simulados, no dinero real</div>
                </div>
            </div>
        </div>

        <!-- PANEL: USUARIOS -->
        <div class="panel" id="panel-usuarios">
            <div class="page-header">
                <div>
                    <div class="page-title">👥 Usuarios</div>
                    <div class="page-breadcrumb">Admin / <span>Acceso y Seguridad</span> / Usuarios</div>
                </div>
                <button class="btn btn-accent" onclick="abrirModalUsuario()">Guardar +</button>
            </div>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" placeholder="Buscar por nombre, email o cédula..." oninput="filterTable('tabla-usuarios', this.value)">
                </div>
                <select class="form-select" style="width:auto;padding:.5rem .8rem;" onchange="filterByRol(this.value)">
                    <option value="">Todos los roles</option>
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
                            <td>{{ $loop->iteration }}</td>
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
                                @if($u->estado) <span class="chip chip-green">Activo</span>
                                @else <span class="chip chip-red">Inactivo</span> @endif
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="icon-btn" title="Editar" aria-label="Editar usuario" onclick="editarUsuario({{ $u->cedula }}, {{ \Illuminate\Support\Js::from($u->nombre) }}, {{ \Illuminate\Support\Js::from($u->email) }}, {{ \Illuminate\Support\Js::from($u->ciudad) }}, {{ \Illuminate\Support\Js::from($u->estado) }}, {{ \Illuminate\Support\Js::from($u->roles->first()?->name ?? '') }})">✏️</button>
                                    <button class="icon-btn del" title="Eliminar" aria-label="Eliminar usuario" onclick="confirmarEliminar({{ $u->cedula }}, {{ \Illuminate\Support\Js::from($u->nombre) }}, 'usuario')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL: ROLES -->
        <div class="panel" id="panel-roles">
            <div class="page-header">
                <div>
                    <div class="page-title">🛡️ Roles</div>
                    <div class="page-breadcrumb">Admin / <span>Acceso y Seguridad</span> / Roles</div>
                </div>
                <button class="btn btn-accent" onclick="abrirModalRol()">Guardar +</button>
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
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td class="name">{{ ucfirst($r->name) }}</td>
                            <td><span class="chip chip-gray">{{ $r->guard_name }}</span></td>
                            <td><span style="font-family:'Bebas Neue',cursive;font-size:1.1rem;color:var(--accent);">{{ $r->users_count }}</span> usuarios</td>
                            <td>{{ $r->created_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                <div class="action-btns">
                                    <button class="icon-btn" title="Ver usuarios" aria-label="Ver usuarios del rol" onclick="verUsuariosRol({{ \Illuminate\Support\Js::from($r->name) }})">👥</button>
                                    <button class="icon-btn" title="Editar" aria-label="Editar rol" onclick="editarRol({{ $r->id }}, {{ \Illuminate\Support\Js::from($r->name) }})">✏️</button>
                                    <button class="icon-btn del" title="Eliminar" aria-label="Eliminar rol" onclick="confirmarEliminarRol({{ $r->id }}, {{ \Illuminate\Support\Js::from($r->name) }})">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL: ASIGNAR ROL -->
        <div class="panel" id="panel-asignar-rol">
            <div class="page-header">
                <div>
                    <div class="page-title">🔗 Asignar Rol</div>
                    <div class="ar-subtitulo">Gestiona los roles y permisos de los usuarios de RedWork.</div>
                    <div class="page-breadcrumb">Admin / <span>Acceso y Seguridad</span> / Asignar Rol</div>
                </div>
            </div>

            <!-- Resumen (GET /api/admin/asignaciones/resumen) -->
            <div class="stats-grid ar-resumen">
                <div class="stat-card" style="--bar-color:#FF5A5F;">
                    <div class="stat-icon">👥</div>
                    <div class="stat-label">Total usuarios</div>
                    <div class="stat-value" id="ar-resumen-total">…</div>
                </div>
                <div class="stat-card" style="--bar-color:#4ADE80;">
                    <div class="stat-icon">🔗</div>
                    <div class="stat-label">Con rol</div>
                    <div class="stat-value" id="ar-resumen-con">…</div>
                </div>
                <div class="stat-card" style="--bar-color:#2DD4BF;">
                    <div class="stat-icon">🛡️</div>
                    <div class="stat-label">Roles disponibles</div>
                    <div class="stat-value" id="ar-resumen-roles">…</div>
                </div>
                <div class="stat-card" style="--bar-color:#F59E0B;">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-label">Sin rol</div>
                    <div class="stat-value" id="ar-resumen-sin">…</div>
                </div>
            </div>

            <div class="ar-grid">
                <!-- Izquierda: formulario de asignación -->
                <div class="table-wrap ar-card" id="ar-col-form">
                    <form id="ar-form" onsubmit="asignarRolFetch(event)" autocomplete="off">
                        @csrf
                        <div class="form-group ar-combo">
                            <label class="form-label" for="ar-usuario-buscar">Seleccionar usuario</label>
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="ar-usuario-buscar" placeholder="Busca por nombre, correo o rol..."
                                       role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ar-usuario-lista"
                                       oninput="buscarUsuarioAsignar(this.value)" onkeydown="teclaUsuarioAsignar(event)">
                            </div>
                            <ul class="ar-lista" id="ar-usuario-lista" role="listbox" aria-label="Resultados de usuarios" hidden></ul>
                            <input type="hidden" id="ar-usuario-id" value="">
                        </div>

                        <div class="ar-usuario-info" id="ar-usuario-info" aria-live="polite">
                            <div class="ar-vacio">Busca y selecciona un usuario para ver su información.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ar-rol-select">Seleccionar rol</label>
                            <select class="form-select" id="ar-rol-select" onchange="cambiarRolAsignar(this.value)">
                                <option value="">— Seleccione rol —</option>
                            </select>
                        </div>
                        <div class="ar-aviso" id="ar-aviso" role="status" hidden></div>
                        <button type="submit" class="btn btn-accent ar-guardar" id="ar-guardar" disabled>Guardar +</button>
                    </form>
                </div>

                <!-- Derecha: información y permisos del rol -->
                <div class="ar-col-rol" id="ar-col-rol">
                    <div class="table-wrap ar-card">
                        <div class="ar-card-titulo">🛡️ Información del rol</div>
                        <div id="ar-rol-info"><div class="ar-vacio">Selecciona un rol para ver su información.</div></div>
                    </div>
                    <div class="table-wrap ar-card">
                        <div class="ar-card-titulo">🔐 Permisos del rol</div>
                        <div id="ar-rol-permisos"><div class="ar-vacio">Selecciona un rol para ver a qué módulos da acceso.</div></div>
                    </div>
                </div>
            </div>

            <!-- Usuarios y roles asignados (GET /api/admin/asignaciones) -->
            <div class="ar-seccion-titulo">👥 Usuarios y roles asignados</div>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="ar-tabla-buscar" placeholder="Buscar por nombre, correo o rol..." aria-label="Buscar usuarios" oninput="buscarTablaAsignaciones(this.value)">
                    <button type="button" class="ar-limpiar" id="ar-tabla-limpiar" title="Limpiar búsqueda" aria-label="Limpiar búsqueda" onclick="limpiarBusquedaAsignaciones()" hidden>✕</button>
                </div>
                <select class="form-select ar-filtro" id="ar-filtro-rol" aria-label="Filtrar por rol" onchange="filtrarAsignaciones()">
                    <option value="">Todos los roles</option>
                </select>
                <select class="form-select ar-filtro" id="ar-filtro-estado" aria-label="Filtrar por estado" onchange="filtrarAsignaciones()">
                    <option value="">Todos los estados</option>
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
                <select class="form-select ar-filtro" id="ar-filtro-conrol" aria-label="Filtrar por asignación" onchange="filtrarAsignaciones()">
                    <option value="">Con y sin rol</option>
                    <option value="si">Con rol</option>
                    <option value="no">Sin rol</option>
                </select>
            </div>
            <div class="table-wrap ar-tabla-wrap">
                <table id="tabla-asignaciones">
                    <thead><tr><th>Nombre</th><th>Correo</th><th>Rol actual</th><th>Estado</th><th>Fecha de asignación</th><th>Acciones</th></tr></thead>
                    <tbody id="tbody-asignaciones"></tbody>
                </table>
            </div>
            <div class="ar-paginacion">
                <span id="ar-paginacion-info"></span>
                <div style="display:flex;gap:.5rem;">
                    <button class="btn btn-ghost" id="ar-prev" onclick="paginaAsignaciones(-1)">◀ Anterior</button>
                    <button class="btn btn-ghost" id="ar-next" onclick="paginaAsignaciones(1)">Siguiente ▶</button>
                </div>
            </div>

            <!-- Historial de asignaciones (GET /api/admin/asignaciones/historial, solo lectura) -->
            <section class="ar-historial" id="ar-historial" aria-labelledby="ar-historial-titulo">
                <div class="ar-seccion-titulo" id="ar-historial-titulo">🕘 Historial de Asignaciones</div>
                <div class="ar-subtitulo ar-historial-sub">Consulta los cambios de roles realizados por los administradores de RedWork.</div>
                <div class="toolbar ar-historial-filtros">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="arh-buscar" maxlength="100" placeholder="Buscar por usuario, cédula o administrador..." aria-label="Buscar en el historial" oninput="buscarHistorialAsignaciones(this.value)">
                        <button type="button" class="ar-limpiar" id="arh-buscar-limpiar" title="Limpiar búsqueda" aria-label="Limpiar búsqueda del historial" onclick="limpiarBusquedaHistorial()" hidden>✕</button>
                    </div>
                    <select class="form-select ar-filtro" id="arh-accion" aria-label="Filtrar por tipo de acción" onchange="filtrarHistorial()">
                        <option value="">Todas las acciones</option>
                        <option value="asignacion">➕ Asignación</option>
                        <option value="cambio">🔄 Cambio</option>
                        <option value="retirada">➖ Retirada</option>
                    </select>
                    <label class="ar-fecha"><span>Fecha inicial</span><input type="date" class="form-input ar-input-fecha" id="arh-desde" aria-label="Fecha inicial" onchange="filtrarHistorial()"></label>
                    <label class="ar-fecha"><span>Fecha final</span><input type="date" class="form-input ar-input-fecha" id="arh-hasta" aria-label="Fecha final" onchange="filtrarHistorial()"></label>
                    <button type="button" class="btn btn-ghost" id="arh-limpiar-filtros" onclick="limpiarFiltrosHistorial()">Limpiar filtros</button>
                </div>
                <div class="ar-error ar-historial-error" id="arh-error" role="alert" hidden></div>
                <div class="table-wrap ar-tabla-wrap" aria-busy="false" id="arh-tabla-wrap">
                    <table id="tabla-historial-asignaciones">
                        <thead><tr><th>Usuario</th><th>Rol anterior</th><th>Nuevo rol</th><th>Acción</th><th>Administrador responsable</th><th>Fecha y hora</th><th>Opciones</th></tr></thead>
                        <tbody id="tbody-historial"></tbody>
                    </table>
                </div>
                <div class="ar-paginacion">
                    <span id="arh-paginacion-info" aria-live="polite"></span>
                    <div style="display:flex;gap:.5rem;">
                        <button class="btn btn-ghost" id="arh-prev" onclick="paginaHistorial(-1)">◀ Anterior</button>
                        <button class="btn btn-ghost" id="arh-next" onclick="paginaHistorial(1)">Siguiente ▶</button>
                    </div>
                </div>
            </section>
        </div>

        <!-- PANEL: CATEGORÍAS -->
        <div class="panel" id="panel-categorias">
            <div class="page-header">
                <div><div class="page-title">🏢 Categorías</div></div>
                <button class="btn btn-accent" onclick="openModal('modal-categoria')">Guardar +</button>
            </div>
            <div class="table-wrap">
                <table id="tabla-categorias">
                    <thead><tr><th>#</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="tbody-categorias"></tbody>
                </table>
            </div>
        </div>

        <!-- PANEL: PUBLICACIONES -->
        <div class="panel" id="panel-publicaciones">
            <div class="page-header">
                <div><div class="page-title">💼 Publicaciones</div></div>
                <button class="btn btn-accent" onclick="openModal('modal-publicacion')">Guardar +</button>
            </div>
            <div class="table-wrap">
                <table id="tabla-publicaciones">
                    <thead><tr><th>#</th><th>Título</th><th>Tipo</th><th>Pago</th><th>Lugar</th><th>Categoría</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="tbody-publicaciones"></tbody>
                </table>
            </div>
        </div>

        <!-- PANEL: POSTULANTES -->
        <div class="panel" id="panel-postulantes">
            <div class="page-header">
                <div><div class="page-title">🧰 Postulantes</div></div>
                <button class="btn btn-accent" onclick="abrirModalPostulante()">Guardar +</button>
            </div>
            <div class="table-wrap">
                <table id="tabla-postulantes">
                    <thead><tr><th>#</th><th>Trabajador</th><th>Habilidades</th><th>Disponibilidad</th><th>Tarifa</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="tbody-postulantes"></tbody>
                </table>
            </div>
        </div>

        <!-- ══ PANELES TRANSACCIONALES (API CONECTADA) ══ -->

        <!-- PANEL TRANSACCIONAL: POSTULACIONES -->
        <div class="panel" id="panel-postulaciones-trans">
            <div class="page-header">
                <div>
                    <div class="page-title">📝 Monitoreo de Postulaciones</div>
                    <div class="page-breadcrumb">Admin / <span>Transaccional</span> / Postulaciones</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th># ID</th>
                            <th>Trabajador</th>
                            <th>Publicación</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-postulaciones">
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Cargando postulaciones...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL TRANSACCIONAL: CONTRATACIONES -->
        <div class="panel" id="panel-contrataciones-trans">
            <div class="page-header">
                <div>
                    <div class="page-title">🤝 Monitoreo de Contrataciones</div>
                    <div class="page-breadcrumb">Admin / <span>Transaccional</span> / Contrataciones</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th># ID</th>
                            <th>Contratador</th>
                            <th>Trabajador</th>
                            <th>Labor / Publicación</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-contrataciones">
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Cargando contrataciones...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL TRANSACCIONAL: PAGOS (registros SIMULADOS) -->
        <div class="panel" id="panel-pagos-trans">
            <div class="page-header">
                <div>
                    <div class="page-title">💳 Pagos <span class="chip chip-yellow">SIMULADO</span></div>
                    <div class="page-breadcrumb">Admin / <span>Transaccional</span> / Pagos</div>
                </div>
            </div>
            <div style="padding:.7rem 1rem;border:1px solid rgba(245,158,11,.3);border-radius:10px;color:var(--gold);font-size:.82rem;margin-bottom:1rem;" id="pagos-aviso">OPERACIONES SIMULADAS — no representan cobros ni dinero real.</div>

            <div class="stats-grid" id="pagos-totales">
                <div class="stat-card" style="--bar-color:#F59E0B;">
                    <div class="stat-label">Pendientes</div>
                    <div class="stat-value" id="pagos-tot-pendiente-monto">…</div>
                    <div class="stat-delta" id="pagos-tot-pendiente-cant"></div>
                </div>
                <div class="stat-card" style="--bar-color:#4ADE80;">
                    <div class="stat-label">Confirmados</div>
                    <div class="stat-value" id="pagos-tot-confirmado-monto">…</div>
                    <div class="stat-delta" id="pagos-tot-confirmado-cant"></div>
                </div>
                <div class="stat-card" style="--bar-color:#FF5A5F;">
                    <div class="stat-label">Anulados</div>
                    <div class="stat-value" id="pagos-tot-anulado-monto">…</div>
                    <div class="stat-delta" id="pagos-tot-anulado-cant"></div>
                </div>
            </div>

            <div class="toolbar">
                <select class="form-select" style="width:auto;padding:.5rem .8rem;" id="pagos-filtro-estado" onchange="filtrarPagos()">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="confirmado">Confirmado</option>
                    <option value="anulado">Anulado</option>
                </select>
                <input class="form-input" style="width:auto;padding:.5rem .8rem;" type="date" id="pagos-filtro-desde" title="Desde" onchange="filtrarPagos()">
                <input class="form-input" style="width:auto;padding:.5rem .8rem;" type="date" id="pagos-filtro-hasta" title="Hasta" onchange="filtrarPagos()">
            </div>
            <div class="table-wrap">
                <table id="tabla-pagos">
                    <thead>
                        <tr>
                            <th># ID</th>
                            <th>Fecha</th>
                            <th>Contratación / Labor</th>
                            <th>Contratador</th>
                            <th>Trabajador</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-pagos">
                        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);">Cargando pagos...</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;color:var(--text-muted);font-size:.8rem;">
                <span id="pagos-paginacion-info"></span>
                <div style="display:flex;gap:.5rem;">
                    <button class="btn btn-ghost" id="pagos-prev" onclick="paginaPagos(-1)">◀ Anterior</button>
                    <button class="btn btn-ghost" id="pagos-next" onclick="paginaPagos(1)">Siguiente ▶</button>
                </div>
            </div>
        </div>

        <!-- PANEL PARAMETRIZACIÓN: CALIFICACIONES (id heredado "calificaciones-trans" conservado) -->
        <div class="panel" id="panel-calificaciones-trans">
            <div class="page-header">
                <div>
                    <div class="page-title">⭐ Monitoreo de Calificaciones</div>
                    <div class="page-breadcrumb">Admin / <span>Parametrización</span> / Calificaciones</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th># ID</th>
                            <th>Emisor</th>
                            <th>Receptor</th>
                            <th>Puntuación</th>
                            <th>Comentario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-calificaciones">
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Cargando calificaciones...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL PARAMETRIZACIÓN: COMISIÓN (solo lectura, valor fijo aprobado 0%) -->
        <div class="panel" id="panel-comision">
            <div class="page-header">
                <div>
                    <div class="page-title">💲 Comisión</div>
                    <div class="page-breadcrumb">Admin / <span>Parametrización</span> / Comisión</div>
                </div>
            </div>
            <div class="table-wrap" style="padding:1.5rem;">
                <div class="form-label">Comisión inicial de RedWork: <strong id="comision-valor">0%</strong></div>
                <div style="color:var(--text-muted);font-size:.85rem;margin-top:.5rem;">Solo lectura. Configurable en una versión futura.</div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ══ MODALES DE FORMULARIOS ══ -->
<div class="modal-overlay" id="modal-usuario">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modal-usuario-title">Nuevo Usuario</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-usuario')">✕</span>
        </div>
        <form id="form-usuario">
            <input type="hidden" id="usuario-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
                <div class="form-group"><label class="form-label">Nombre</label><input class="form-input" type="text" id="f-nombre"></div>
                <div class="form-group"><label class="form-label">Cédula</label><input class="form-input" type="number" id="f-cedula"></div>
            </div>
            <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" id="f-email"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
                <div class="form-group"><label class="form-label">Ciudad</label><input class="form-input" type="text" id="f-ciudad"></div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="f-estado"><option value="1">Activo</option><option value="0">Inactivo</option></select>
                </div>
            </div>
            <div class="form-group" id="grupo-password"><label class="form-label">Contraseña</label><input class="form-input" type="password" id="f-password"></div>
            <div class="form-group" id="grupo-fechanac"><label class="form-label">Fecha de nacimiento</label><input class="form-input" type="date" name="fechanac" id="f-fechanac"></div>
            <div class="form-group"><label class="form-label">Rol</label><select class="form-select" id="f-rol"></select></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-usuario')">Cancelar</button>
                <button type="button" class="btn btn-accent" onclick="guardarUsuario()">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-categoria">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modal-categoria-title">Nueva Categoría</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-categoria')">✕</span>
        </div>
        <input type="hidden" id="categoria-id">
        <div class="form-group"><label class="form-label">Nombre</label><input class="form-input" type="text" id="cat-nombre"></div>
        <div class="form-group"><label class="form-label">Descripción</label><textarea class="form-input" id="cat-descripcion" rows="3"></textarea></div>
        <div class="form-group">
            <label class="form-label">Estado</label>
            <select class="form-select" id="cat-estado"><option value="1">Activo</option><option value="0">Inactivo</option></select>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-categoria')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarCategoria()">Guardar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-publicacion">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <div class="modal-title" id="modal-publicacion-title">Nueva Publicación</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-publicacion')">✕</span>
        </div>
        <input type="hidden" id="pub-id">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
            <div class="form-group"><label class="form-label">Título</label><input class="form-input" type="text" id="pub-titulo"></div>
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select class="form-select" id="pub-tipo"><option value="recurrente">Recurrente</option><option value="temporal">Temporal</option></select>
            </div>
        </div>
        <div class="form-group"><label class="form-label">Descripción</label><textarea class="form-input" id="pub-descripcion" rows="3"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
            <div class="form-group"><label class="form-label">Pago (Bs)</label><input class="form-input" type="number" id="pub-pago"></div>
            <div class="form-group"><label class="form-label">Lugar</label><input class="form-input" type="text" id="pub-lugar"></div>
        </div>
        <div class="form-group"><label class="form-label">Categoría</label><select class="form-select" id="pub-categoria"></select></div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-publicacion')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarPublicacion()">Guardar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-postulante">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <div class="modal-title" id="modal-postulante-title">Nuevo Postulante</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-postulante')">✕</span>
        </div>
        <input type="hidden" id="post-id">
        <div class="form-group"><label class="form-label">Trabajador</label><select class="form-select" id="post-cedula"></select></div>
        <div class="form-group"><label class="form-label">Habilidades</label><textarea class="form-input" id="post-habilidades" rows="2"></textarea></div>
        <div class="form-group"><label class="form-label">Experiencia</label><textarea class="form-input" id="post-experiencia" rows="2"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
            <div class="form-group">
                <label class="form-label">Disponibilidad</label>
                <select class="form-select" id="post-disponibilidad">
                    <option value="Tiempo completo">Tiempo completo</option>
                    <option value="Medio tiempo">Medio tiempo</option>
                    <option value="Fines de semana">Fines de semana</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Tarifa (Bs)</label><input class="form-input" type="number" id="post-tarifa"></div>
        </div>
        <div class="form-group">
            <label class="form-label">Estado</label>
            <select class="form-select" id="post-estado"><option value="1">Activo</option><option value="0">Inactivo</option></select>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-postulante')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarPostulante()">Guardar</button>
        </div>
    </div>
</div>

<!-- DETALLE DEL HISTORIAL DE ASIGNACIONES (solo lectura) -->
<div class="modal-overlay" id="modal-historial-detalle">
    <div class="modal ar-modal-historial" role="dialog" aria-modal="true" aria-labelledby="arh-detalle-titulo" tabindex="-1">
        <div class="modal-header">
            <div class="modal-title" id="arh-detalle-titulo">👁️ Detalle del cambio de rol</div>
            <button type="button" class="modal-close ar-modal-x" id="arh-detalle-x" title="Cerrar" aria-label="Cerrar detalle" onclick="cerrarDetalleHistorial()">✕</button>
        </div>
        <div id="arh-detalle-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" id="arh-detalle-cerrar" onclick="cerrarDetalleHistorial()">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODALES DE PAGOS (SIMULADOS) -->
<div class="modal-overlay" id="modal-pago-detalle">
    <div class="modal" style="max-width:720px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <div class="modal-title">💳 Pago <span id="pago-detalle-id"></span> <span class="chip chip-yellow">SIMULADO</span></div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-pago-detalle')">✕</span>
        </div>
        <div id="pago-detalle-body" style="color:var(--text-soft);font-size:.86rem;">Cargando...</div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-pago-detalle')">Cerrar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-pago-confirmar">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <div class="modal-title">✅ Confirmar pago</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-pago-confirmar')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">¿Confirmar el pago simulado <strong id="pago-confirmar-nombre" style="color:white;"></strong>? No se mueve dinero real.</p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-pago-confirmar')">Cancelar</button>
            <button class="btn btn-accent" id="pago-confirmar-btn" onclick="ejecutarConfirmarPago()">Confirmar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-pago-anular">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Anular pago <span id="pago-anular-nombre"></span></div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-pago-anular')">✕</span>
        </div>
        <div class="form-group">
            <label class="form-label">Motivo de anulación (obligatorio)</label>
            <textarea class="form-input" id="pago-anular-motivo" rows="3" maxlength="255"></textarea>
            <div id="pago-anular-error" style="color:#ff7070;font-size:.75rem;margin-top:.25rem;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-pago-anular')">Cancelar</button>
            <button class="btn btn-red" id="pago-anular-btn" onclick="ejecutarAnularPago()">Anular pago</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Confirmar</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-eliminar')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">¿Eliminar <strong id="eliminar-nombre" style="color:white;"></strong>?</p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-eliminar')">Cancelar</button>
            <button class="btn btn-red" onclick="ejecutarEliminar()">🗑️ Eliminar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-eliminar-item">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Confirmar</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-eliminar-item')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">¿Eliminar <strong id="eliminar-item-nombre" style="color:white;"></strong>?</p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-eliminar-item')">Cancelar</button>
            <button class="btn btn-red" onclick="ejecutarEliminarItem()">🗑️ Eliminar</button>
        </div>
    </div>
</div>


<div class="modal-overlay" id="modal-quitar-rol">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Quitar rol</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-quitar-rol')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">¿Quitar el rol <strong id="quitar-rol-rol" style="color:white;"></strong> a <strong id="quitar-rol-nombre" style="color:white;"></strong>? El usuario quedará sin rol y sin acceso a sus módulos.</p>
        <div id="quitar-rol-error" class="ar-error" role="alert"></div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-quitar-rol')">Cancelar</button>
            <button class="btn btn-red" id="quitar-rol-btn" onclick="ejecutarQuitarRol()">🗑️ Quitar rol</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-rol">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <div class="modal-title" id="modal-rol-title">Nuevo Rol</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-rol')">✕</span>
        </div>
        <input type="hidden" id="rol-id">
        <div class="form-group">
            <label class="form-label">Nombre del Rol</label>
            <input class="form-input" type="text" id="rol-nombre">
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-rol')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarRol()">Guardar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-eliminar-rol">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Eliminar Rol</div>
            <span class="modal-close" title="Cerrar" onclick="closeModal('modal-eliminar-rol')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">¿Eliminar el rol <strong id="eliminar-rol-nombre" style="color:white;"></strong>?</p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-eliminar-rol')">Cancelar</button>
            <button class="btn btn-red" onclick="ejecutarEliminarRol()">🗑️ Eliminar</button>
        </div>
    </div>
</div>

</body>
</html>