<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RED-WORK — Panel Contratador</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    @php
        $user = auth()->user();
        $token = $user?->createToken('dashboard-contratador')->plainTextToken;
        $misPublicaciones = \App\Models\Publicacion::where('cedula', $user->cedula ?? null)
            ->where('estado', 1)
            ->with('categoria')
            ->get();
    @endphp
    <meta name="api-token" content="{{ $token }}">
    <meta name="user-cedula" content="{{ $user->cedula }}">
    @vite(['resources/css/contratador-dashboard.css', 'resources/js/contratador-dashboard.js'])
</head>
<body>

<!-- TOPBAR -->
<nav class="topbar">
    <div class="topbar-left">
        <div class="menu-toggle" onclick="toggleSidebar()">
            <span></span><span></span><span></span>
        </div>
        <div class="logo">
            <img src="{{ asset('images/redwork-logo.png') }}" alt="RedWork Logo" class="logo-img">
        </div>
        <span class="contratador-badge">💼 Contratador</span>
    </div>
    <div class="topbar-right">
        <div class="avatar-btn" onclick="showPanel('perfil')" style="cursor:pointer;">{{ strtoupper(substr($user->nombre ?? 'C', 0, 2)) }}</div>
    </div>
</nav>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <a class="nav-item active" data-panel="home" onclick="showPanel('home')">🏠 Inicio</a>
    <a class="nav-item" data-panel="mis-publicaciones" onclick="showPanel('mis-publicaciones')">📋 Mis Publicaciones</a>
    <a class="nav-item" data-panel="postulaciones" onclick="showPanel('postulaciones')">📩 Postulaciones Recibidas</a>
    <a class="nav-item" data-panel="contrataciones" onclick="showPanel('contrataciones')">🤝 Contrataciones</a>
    <a class="nav-item" data-panel="perfil" onclick="showPanel('perfil')">👤 Mi Perfil</a>
    <div style="margin-top:auto;">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;">🚪 Cerrar Sesión</button>
        </form>
    </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main" id="main">
    <div class="content">

        <!-- PANEL 1: INICIO -->
        <div class="panel active" id="panel-home">
            <div class="welcome-card">
                <h1 style="font-family:'Bebas Neue',cursive;font-size:2.2rem;color:white;">BIENVENIDO, {{ strtoupper($user->nombre) }}</h1>
                <p style="color:var(--text-soft);margin-top:.3rem;">Gestiona tus publicaciones, contrata personal calificado y califica el trabajo finalizado.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Publicaciones Activas</div>
                    <div class="stat-value">{{ $misPublicaciones->count() }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Contrataciones Activas</div>
                    <div class="stat-value" style="color:var(--accent);" id="stat-contrataciones">–</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Postulaciones Pendientes</div>
                    <div class="stat-value" style="color:var(--gold);" id="stat-pendientes">–</div>
                </div>
            </div>
        </div>

        <!-- PANEL 2: MIS PUBLICACIONES -->
        <div class="panel" id="panel-mis-publicaciones">
            <div class="page-header">
                <div class="page-title">📋 Mis Publicaciones de Empleo</div>
                <button class="btn btn-accent" onclick="openModal('modal-nueva-publicacion')">+ Crear Publicación</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th># ID</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Pago (Bs)</th>
                            <th>Lugar</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($misPublicaciones as $pub)
                        <tr>
                            <td>{{ $pub->id_labor }}</td>
                            <td class="name">{{ $pub->titulo }}</td>
                            <td><span class="chip chip-blue">{{ ucfirst($pub->tipo) }}</span></td>
                            <td style="color:var(--accent);font-weight:bold;">Bs {{ $pub->pago }}</td>
                            <td>{{ $pub->lugar }}</td>
                            <td>{{ $pub->categoria?->nombre_categoria ?? '—' }}</td>
                            <td>
                                @if($pub->estado)
                                    <span class="chip chip-green">Activa</span>
                                @else
                                    <span class="chip chip-red">Inactiva</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap: .4rem;">
                                    <button class="btn btn-gold" onclick="editarPublicacion({{ json_encode($pub) }})">✏️ Editar</button>
                                    <button class="btn btn-danger" onclick="eliminarPublicacion({{ $pub->id_labor }})">🗑️ Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--text-muted);">No has creado ninguna publicación todavía.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL 3: POSTULACIONES RECIBIDAS -->
        <div class="panel" id="panel-postulaciones">
            <div class="page-header">
                <div class="page-title">📩 Postulantes Interesados</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Trabajador</th>
                            <th>Labor / Oferta</th>
                            <th>Mensaje</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-postulaciones-recibidas">
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Cargando postulaciones...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL: CONTRATACIONES -->
        <div class="panel" id="panel-contrataciones">
            <div class="page-header">
                <div class="page-title">🤝 Mis Contrataciones</div>
            </div>
            <div class="table-wrap" style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Trabajador</th>
                            <th>Labor</th>
                            <th>Monto</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-contrataciones">
                        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Cargando contrataciones...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL 4: MI PERFIL -->
        <div class="panel" id="panel-perfil">
            <div class="page-header">
                <div class="page-title">👤 Mi Perfil de Contratador</div>
                <button class="btn btn-accent" onclick="openModal('modal-editar-perfil')">✏️ Editar Perfil</button>
            </div>

            <div class="table-wrap" style="padding: 1.8rem; background: var(--bg-card);">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                    <div>
                        <span class="stat-label">Cédula de Identidad</span>
                        <p style="font-size: 1.1rem; font-weight: 600; color: white; margin-top: 0.3rem;">{{ $user->cedula }}</p>
                    </div>
                    <div>
                        <span class="stat-label">Nombre Completo</span>
                        <p style="font-size: 1.1rem; font-weight: 600; color: white; margin-top: 0.3rem;">{{ $user->nombre }}</p>
                    </div>
                    <div>
                        <span class="stat-label">Correo Electrónico</span>
                        <p style="font-size: 1.1rem; font-weight: 600; color: white; margin-top: 0.3rem;">{{ $user->email }}</p>
                    </div>
                    <div>
                        <span class="stat-label">Teléfono</span>
                        <p style="font-size: 1.1rem; font-weight: 600; color: white; margin-top: 0.3rem;">{{ $user->telefono ?? 'Sin registrar' }}</p>
                    </div>
                    <div>
                        <span class="stat-label">Dirección</span>
                        <p style="font-size: 1.1rem; font-weight: 600; color: white; margin-top: 0.3rem;">{{ $user->direccion ?? 'Sin registrar' }}</p>
                    </div>
                    <div>
                        <span class="stat-label">Ciudad</span>
                        <p style="font-size: 1.1rem; font-weight: 600; color: white; margin-top: 0.3rem;">{{ $user->ciudad ?? 'Sin registrar' }}</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL NUEVA PUBLICACIÓN -->
<div class="modal-overlay" id="modal-nueva-publicacion">
    <div class="modal">
        <h3 style="font-family:'Bebas Neue',cursive;font-size:1.6rem;color:white;margin-bottom:1.2rem;">Nueva Oferta de Empleo</h3>
        <div class="form-group"><label class="form-label">Título</label><input class="form-input" type="text" id="pub-titulo" placeholder="Ej: Pintor de casa"></div>
        <div class="form-group">
            <label class="form-label">Tipo de Labor</label>
            <select class="form-select" id="pub-tipo">
                <option value="temporal">Temporal</option>
                <option value="recurrente">Recurrente</option>
            </select>
        </div>
        <div class="form-group"><label class="form-label">Descripción</label><textarea class="form-input" id="pub-descripcion" rows="3" placeholder="Detalles de la labor..."></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
            <div class="form-group"><label class="form-label">Pago (Bs)</label><input class="form-input" type="number" id="pub-pago" placeholder="250"></div>
            <div class="form-group"><label class="form-label">Lugar</label><input class="form-input" type="text" id="pub-lugar" placeholder="Ciudad / Zona"></div>
        </div>
        <div class="form-group"><label class="form-label">Categoría</label><select class="form-select" id="pub-categoria"></select></div>
        <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1.5rem;">
            <button class="btn btn-danger" onclick="closeModal('modal-nueva-publicacion')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarNuevaPublicacion()">Publicar Oferta</button>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PUBLICACIÓN -->
<div class="modal-overlay" id="modal-editar-publicacion">
    <div class="modal">
        <h3 style="font-family:'Bebas Neue',cursive;font-size:1.6rem;color:white;margin-bottom:1.2rem;">Editar Oferta de Empleo</h3>
        <input type="hidden" id="edit-pub-id">
        <div class="form-group"><label class="form-label">Título</label><input class="form-input" type="text" id="edit-pub-titulo"></div>
        <div class="form-group">
            <label class="form-label">Tipo de Labor</label>
            <select class="form-select" id="edit-pub-tipo">
                <option value="temporal">Temporal</option>
                <option value="recurrente">Recurrente</option>
            </select>
        </div>
        <div class="form-group"><label class="form-label">Descripción</label><textarea class="form-input" id="edit-pub-descripcion" rows="3"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
            <div class="form-group"><label class="form-label">Pago (Bs)</label><input class="form-input" type="number" id="edit-pub-pago"></div>
            <div class="form-group"><label class="form-label">Lugar</label><input class="form-input" type="text" id="edit-pub-lugar"></div>
        </div>
        <div class="form-group"><label class="form-label">Categoría</label><select class="form-select" id="edit-pub-categoria"></select></div>
        <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1.5rem;">
            <button class="btn btn-danger" onclick="closeModal('modal-editar-publicacion')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarEdicionPublicacion()">Guardar Cambios</button>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PERFIL -->
<div class="modal-overlay" id="modal-editar-perfil">
    <div class="modal">
        <h3 style="font-family:'Bebas Neue',cursive;font-size:1.6rem;color:white;margin-bottom:1.2rem;">Editar Información Personal</h3>
        <div class="form-group">
            <label class="form-label">Nombre Completo</label>
            <input class="form-input" type="text" id="edit-perfil-nombre" value="{{ $user->nombre }}">
        </div>
        <div class="form-group">
            <label class="form-label">Correo Electrónico</label>
            <input class="form-input" type="email" id="edit-perfil-email" value="{{ $user->email }}">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
            <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input class="form-input" type="text" id="edit-perfil-telefono" value="{{ $user->telefono }}">
            </div>
            <div class="form-group">
                <label class="form-label">Ciudad</label>
                <input class="form-input" type="text" id="edit-perfil-ciudad" value="{{ $user->ciudad }}">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Dirección</label>
            <input class="form-input" type="text" id="edit-perfil-direccion" value="{{ $user->direccion }}">
        </div>
        <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1.5rem;">
            <button class="btn btn-danger" onclick="closeModal('modal-editar-perfil')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarPerfil()">Guardar Cambios</button>
        </div>
    </div>
</div>

<!-- MODAL CALIFICAR -->
<div class="modal-overlay" id="modal-calificar">
    <div class="modal">
        <h3 style="font-family:'Bebas Neue',cursive;font-size:1.5rem;color:white;margin-bottom:1rem;">⭐ Calificar a <span id="calif-nombre-trabajador"></span></h3>
        <input type="hidden" id="calif-contratacion">
        
        <div class="form-group">
            <label class="form-label">Puntuación</label>
            <select class="form-select" id="calif-puntuacion">
                <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                <option value="4">⭐⭐⭐⭐ (4/5)</option>
                <option value="3">⭐⭐⭐ (3/5)</option>
                <option value="2">⭐⭐ (2/5)</option>
                <option value="1">⭐ (1/5)</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Comentario</label>
            <textarea class="form-input" id="calif-comentario" rows="3" placeholder="Escribe tu opinión sobre el trabajo realizado..."></textarea>
        </div>

        <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1.5rem;">
            <button class="btn btn-danger" onclick="closeModal('modal-calificar')">Cancelar</button>
            <button class="btn btn-accent" onclick="guardarCalificacion()">Guardar Calificación</button>
        </div>
    </div>
</div>

</body>
</html>