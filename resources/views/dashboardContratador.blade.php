<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <script>/* Tema antes de pintar (sin parpadeo). Ver resources/js/themes.js */try{document.documentElement.setAttribute('data-theme',localStorage.getItem('redwork-theme')==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}</script>
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
    @vite(['resources/css/contratador-dashboard.css', 'resources/js/contratador-dashboard.js', 'resources/css/kpi-dashboard.css', 'resources/js/kpi-dashboard.js', 'resources/css/themes.css', 'resources/js/themes.js'])
</head>
<body class="rw-contratador">

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
        <button type="button" class="theme-switch" data-theme-toggle role="switch" aria-checked="false" aria-label="Tema oscuro activado. Cambiar a tema claro" title="Cambiar a tema claro"><span class="theme-switch-track" aria-hidden="true"><span class="theme-switch-thumb">🌙</span></span><span class="theme-switch-label">Tema oscuro</span></button>
        <div class="avatar-btn" onclick="showPanel('perfil')" style="cursor:pointer;">{{ strtoupper(substr($user->nombre ?? 'C', 0, 2)) }}</div>
    </div>
</nav>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <a class="nav-item active" data-panel="home" onclick="showPanel('home')">🏠 Inicio</a>
    <a class="nav-item" data-panel="mis-publicaciones" onclick="showPanel('mis-publicaciones')">📋 Mis Publicaciones</a>
    <a class="nav-item" data-panel="postulaciones" onclick="showPanel('postulaciones')">📩 Postulaciones Recibidas</a>
    <a class="nav-item" data-panel="contrataciones" onclick="showPanel('contrataciones')" title="Contrataciones, pagos simulados y calificaciones">🤝 Mis Contrataciones</a>
    <a class="nav-item" data-panel="calificaciones" onclick="showPanel('calificaciones')">⭐ Calificaciones</a>
    <a class="nav-item" data-panel="indicadores" onclick="showPanel('indicadores')">📊 Mis Indicadores</a>
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
                                    <button class="btn btn-gold" title="Editar" onclick="editarPublicacion({{ json_encode($pub) }})">✏️ Editar</button>
                                    {{-- La API no borra: desactiva (estado = 0). Solo tiene efecto sobre publicaciones activas. --}}
                                    @if($pub->estado)
                                    <button class="btn btn-danger" title="Desactivar" onclick="eliminarPublicacion({{ $pub->id_labor }})">🚫 Desactivar</button>
                                    @endif
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
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-postulaciones-recibidas">
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Cargando postulaciones...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL: CONTRATACIONES -->
        <div class="panel" id="panel-contrataciones">
            <div class="page-header">
                <div class="page-title">🤝 Mis Contrataciones</div>
            </div>
            <p style="color:var(--text-soft);font-size:.85rem;margin:-.6rem 0 1rem;">Desde cada contratación: 💳 <strong>Pagos</strong> (registro y consulta de pagos simulados; la confirmación corresponde al administrador) y ⭐ <strong>Calificación</strong> al finalizar.</p>
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

        <!-- PANEL: CALIFICACIONES (mismos datos de GET /api/contratador/contrataciones; califica con el modal existente) -->
        <div class="panel" id="panel-calificaciones">
            <div class="page-header">
                <div class="page-title">⭐ Calificaciones</div>
            </div>
            <p style="color:var(--text-soft);font-size:.85rem;margin:-.6rem 0 1rem;">Solo se califica una contratación finalizada, una única vez. Aquí ves las calificaciones que realizaste y las que recibiste.</p>
            <div class="stats-grid" id="calif-resumen-contratador"></div>
            <div class="table-wrap" style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Trabajador</th>
                            <th>Labor</th>
                            <th>Finalizada</th>
                            <th>Mi calificación</th>
                            <th>Calificación recibida</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-calificaciones">
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Cargando calificaciones...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANEL: MIS INDICADORES (GET /api/contratador/kpis — resources/js/kpi-dashboard.js) -->
        <div class="panel" id="panel-indicadores">
            <section class="kpi-bi" id="kpi-contratador" data-kpi-dashboard data-alcance="contratador"
                     data-endpoint="/api/contratador/kpis"></section>
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

<!-- MODAL PAGOS DE LA CONTRATACIÓN (registros simulados) -->
<div class="modal-overlay" id="modal-pagos">
    <div class="modal" style="max-width:860px;width:95%;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;">
            <h3 style="font-family:'Bebas Neue',cursive;font-size:1.5rem;color:white;">💳 Pagos de la contratación <span id="pagos-titulo"></span></h3>
            <span class="chip chip-yellow">SIMULADO</span>
        </div>
        <div style="padding:.7rem 1rem;border:1px solid rgba(245,158,11,.3);border-radius:10px;color:var(--gold);font-size:.82rem;margin-bottom:1rem;" id="pagos-aviso">SIMULADO — no representa dinero real.</div>

        <div class="stats-grid" id="pagos-resumen"></div>

        <div class="table-wrap" style="overflow-x:auto;margin:1rem 0;">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Método</th><th>Monto</th><th>Comisión</th><th>Neto</th><th>Estado</th><th>Referencia</th></tr>
                </thead>
                <tbody id="tbody-pagos-contratacion">
                    <tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Cargando pagos...</td></tr>
                </tbody>
            </table>
        </div>

        <form id="form-pago" onsubmit="registrarPago(event)">
            <input type="hidden" id="pago-contratacion">
            <div style="font-family:'Bebas Neue',cursive;font-size:1.2rem;color:white;margin-bottom:.6rem;">Registrar pago (simulado)</div>
            <div id="pago-bloqueo" style="display:none;color:var(--text-muted);font-size:.82rem;margin-bottom:.6rem;"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.8rem;">
                <div class="form-group">
                    <label class="form-label">Monto (Bs)</label>
                    <input class="form-input" type="number" step="0.01" min="0.01" id="pago-monto">
                    <div class="pago-error" data-campo="monto" style="color:#ff7070;font-size:.75rem;margin-top:.25rem;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha de pago</label>
                    <input class="form-input" type="date" id="pago-fecha">
                    <div class="pago-error" data-campo="fecha_pago" style="color:#ff7070;font-size:.75rem;margin-top:.25rem;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Método</label>
                    <select class="form-select" id="pago-metodo">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="qr">QR</option>
                        <option value="deposito">Depósito</option>
                        <option value="otro">Otro</option>
                    </select>
                    <div class="pago-error" data-campo="metodo" style="color:#ff7070;font-size:.75rem;margin-top:.25rem;"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Observación (opcional)</label>
                <textarea class="form-input" id="pago-observacion" rows="2"></textarea>
                <div class="pago-error" data-campo="observacion" style="color:#ff7070;font-size:.75rem;margin-top:.25rem;"></div>
            </div>
            <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn btn-danger" onclick="closeModal('modal-pagos')">Cerrar</button>
                <button type="submit" class="btn btn-accent" id="pago-guardar">Registrar pago simulado</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>