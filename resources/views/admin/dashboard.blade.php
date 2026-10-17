<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — Administrador</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <meta name="api-token" content="{{ $token }}">

    @vite(['resources/css/admin-dashboard.css', 'resources/js/admin-dashboard.js'])
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
    </div>x
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
            <a class="nav-item" onclick="showPanel('categorias')" href="#"><span class="nav-item-icon">🏢</span> Categorías</a>
            <a class="nav-item" onclick="showPanel('publicaciones')" href="#"><span class="nav-item-icon">💼</span> Publicaciones</a>
            <a class="nav-item" onclick="showPanel('postulantes')" href="#"><span class="nav-item-icon">🧰</span> Postulantes</a>
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
            <a class="nav-item" onclick="showPanel('postulaciones-trans')" href="#"><span class="nav-item-icon">📝</span> Postulaciones</a>
            <a class="nav-item" onclick="showPanel('contrataciones-trans')" href="#"><span class="nav-item-icon">🤝</span> Contrataciones</a>
            <a class="nav-item" onclick="showPanel('calificaciones-trans')" href="#"><span class="nav-item-icon">⭐</span> Calificaciones</a>
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
        </div>

        <!-- PANEL: USUARIOS -->
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
                                    <button class="icon-btn" title="Ver" onclick="verUsuario({{ $u->cedula }})">👁️️</button>
                                    <button class="icon-btn" title="Editar" onclick="editarUsuario({{ $u->cedula }}, {{ \Illuminate\Support\Js::from($u->nombre) }}, {{ \Illuminate\Support\Js::from($u->email) }}, {{ \Illuminate\Support\Js::from($u->ciudad) }}, {{ \Illuminate\Support\Js::from($u->estado) }}, {{ \Illuminate\Support\Js::from($u->roles->first()?->name ?? '') }})">✏</button>
                                    <button class="icon-btn del" title="Eliminar" onclick="confirmarEliminar({{ $u->cedula }}, {{ \Illuminate\Support\Js::from($u->nombre) }}, 'usuario')">🗑️</button>
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
                <button class="btn btn-accent" onclick="abrirModalRol()">+ Nuevo Rol</button>
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
                                    <button class="icon-btn" title="Ver usuarios" onclick="verUsuariosRol({{ \Illuminate\Support\Js::from($r->name) }})">👥</button>
                                    <button class="icon-btn" title="Editar" onclick="editarRol({{ $r->id }}, {{ \Illuminate\Support\Js::from($r->name) }})">✏️</button>
                                    <button class="icon-btn del" title="Eliminar" onclick="confirmarEliminarRol({{ $r->id }}, {{ \Illuminate\Support\Js::from($r->name) }})">🗑️</button>
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
                    <div class="page-breadcrumb">Admin / <span>Acceso y Seguridad</span> / Asignar Rol</div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div class="table-wrap" style="padding:1.5rem;">
                    <form onsubmit="asignarRolFetch(event)">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">Seleccionar Usuario</label>
                            <select class="form-select">
                                <option value="">— Seleccione usuario —</option>
                                @foreach(\App\Models\User::orderBy('nombre')->get() as $u)
                                <option value="{{ $u->cedula }}">{{ $u->nombre }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Seleccionar Rol</label>
                            <select class="form-select"></select>
                        </div>
                        <button type="submit" class="btn btn-accent" style="width:100%;">✅ Asignar Rol</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- PANEL: CATEGORÍAS -->
        <div class="panel" id="panel-categorias">
            <div class="page-header">
                <div><div class="page-title">🏢 Categorías</div></div>
                <button class="btn btn-accent" onclick="openModal('modal-categoria')">+ Nueva Categoría</button>
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
                <button class="btn btn-accent" onclick="openModal('modal-publicacion')">+ Nueva Publicación</button>
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
                <button class="btn btn-accent" onclick="abrirModalPostulante()">+ Nuevo Postulante</button>
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

        <!-- PANEL TRANSACCIONAL: CALIFICACIONES -->
        <div class="panel" id="panel-calificaciones-trans">
            <div class="page-header">
                <div>
                    <div class="page-title">⭐ Monitoreo de Calificaciones</div>
                    <div class="page-breadcrumb">Admin / <span>Transaccional</span> / Calificaciones</div>
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

    </div><!-- /content -->
</div><!-- /main -->

<!-- ══ MODALES DE FORMULARIOS ══ -->
<div class="modal-overlay" id="modal-usuario">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modal-usuario-title">Nuevo Usuario</div>
            <span class="modal-close" onclick="closeModal('modal-usuario')">✕</span>
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
            <span class="modal-close" onclick="closeModal('modal-categoria')">✕</span>
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
            <span class="modal-close" onclick="closeModal('modal-publicacion')">✕</span>
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
            <span class="modal-close" onclick="closeModal('modal-postulante')">✕</span>
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

<div class="modal-overlay" id="modal-eliminar">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#ff7070;">⚠️ Confirmar</div>
            <span class="modal-close" onclick="closeModal('modal-eliminar')">✕</span>
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
            <span class="modal-close" onclick="closeModal('modal-eliminar-item')">✕</span>
        </div>
        <p style="color:var(--text-soft);font-size:.88rem;margin-bottom:1rem;">¿Eliminar <strong id="eliminar-item-nombre" style="color:white;"></strong>?</p>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modal-eliminar-item')">Cancelar</button>
            <button class="btn btn-red" onclick="ejecutarEliminarItem()">🗑️ Eliminar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-ver-usuario">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">👤 Detalle Usuario</div>
            <span class="modal-close" onclick="closeModal('modal-ver-usuario')">✕</span>
        </div>
        <div id="detalle-usuario-content" style="color:var(--text-soft);font-size:.88rem;">Cargando...</div>
        <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('modal-ver-usuario')">Cerrar</button></div>
    </div>
</div>

<div class="modal-overlay" id="modal-rol">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <div class="modal-title" id="modal-rol-title">Nuevo Rol</div>
            <span class="modal-close" onclick="closeModal('modal-rol')">✕</span>
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
            <span class="modal-close" onclick="closeModal('modal-eliminar-rol')">✕</span>
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