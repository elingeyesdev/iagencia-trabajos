const TOKEN = document.querySelector('meta[name="api-token"]').content;

// ── Sidebar & UI ──
// En pantallas ≤ 900px el CSS oculta el menú y lo muestra con .mobile-open; en escritorio se usa .collapsed.
const esMovil = () => !!(window.matchMedia && window.matchMedia('(max-width: 900px)').matches);

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (esMovil()) {
        sidebar.classList.toggle('mobile-open');
        return;
    }
    const main = document.getElementById('main');
    sidebar.classList.toggle('collapsed');
    main.classList.toggle('expanded');
}

function toggleGroup(id) {
    const group = document.getElementById('group-' + id);
    group.classList.toggle('open');
}

function showPanel(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('panel-' + name)?.classList.add('active');
    document.getElementById('sidebar')?.classList.remove('mobile-open'); // en móvil, cerrar el menú al elegir un módulo
    if (window.event?.currentTarget) {
        window.event.currentTarget.classList.add('active');
    }
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
    document.querySelectorAll('.dropdown.open').forEach(d => {
        if (!d.contains(e.target)) d.classList.remove('open');
    });
});

function filterTable(tableId, query) {
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    const q = query.toLowerCase();
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function filterByRol(rol) {
    const rows = document.querySelectorAll('#tabla-usuarios tbody tr');
    rows.forEach(row => {
        row.style.display = (!rol || row.dataset.rol === rol) ? '' : 'none';
    });
}

function toast(msg, tipo = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:2rem;right:2rem;z-index:9999;padding:.9rem 1.4rem;border-radius:12px;font-size:.88rem;font-weight:600;backdrop-filter:blur(12px);${tipo==='success'?'background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.3);color:#4ADE80;':'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#ff7070;'}`;
    t.textContent = (tipo==='success'?'✅ ':'❌ ') + msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ── USUARIOS ──
// Reinicia el modal al estado de creación (evita arrastrar id/datos de una edición previa).
function abrirModalUsuario() {
    document.getElementById('modal-usuario-title').textContent = 'Nuevo Usuario';
    document.getElementById('usuario-id').value = '';
    document.getElementById('f-nombre').value = '';
    document.getElementById('f-email').value = '';
    document.getElementById('f-cedula').value = '';
    document.getElementById('f-ciudad').value = '';
    document.getElementById('f-estado').value = '1';
    document.getElementById('f-rol').value = '';
    document.getElementById('f-password').value = '';
    document.getElementById('f-fechanac').value = '';
    document.getElementById('grupo-password').style.display = '';
    document.getElementById('grupo-fechanac').style.display = '';
    openModal('modal-usuario');
}

function editarUsuario(id, nombre, email, ciudad, estado, rol) {
    document.getElementById('modal-usuario-title').textContent = 'Editar Usuario';
    document.getElementById('usuario-id').value = id;
    document.getElementById('f-nombre').value = nombre;
    document.getElementById('f-email').value = email;
    document.getElementById('f-cedula').value = id;
    document.getElementById('f-ciudad').value = ciudad === '—' ? '' : ciudad;
    document.getElementById('f-estado').value = estado;
    document.getElementById('f-rol').value = rol || '';
    document.getElementById('grupo-password').style.display = 'none';
    document.getElementById('f-fechanac').value = '';
    document.getElementById('grupo-fechanac').style.display = 'none';
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
  
    if (!id) body.fechanac = document.getElementById('f-fechanac').value;
    const url = id ? `/api/admin/usuarios/${id}` : '/api/admin/usuarios';
    const method = id ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
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

let eliminarId = null, eliminarTipo = null;
function confirmarEliminar(id, nombre, tipo) {
    eliminarId = id; eliminarTipo = tipo;
    document.getElementById('eliminar-nombre').textContent = nombre;
    openModal('modal-eliminar');
}

async function ejecutarEliminar() {
    const res = await fetch('/api/admin/usuarios/' + eliminarId, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
    });
    if (res.ok) { toast('Usuario eliminado.'); closeModal('modal-eliminar'); setTimeout(() => location.reload(), 1500); }
    else { toast('Error al eliminar.', 'error'); }
}

// ── ROLES ──
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
    const id = document.getElementById('rol-id').value;
    const nombre = document.getElementById('rol-nombre').value.trim();
    if (!nombre) { toast('Ingresa un nombre para el rol.', 'error'); return; }

    const url = id ? `/api/admin/roles/${id}` : '/api/admin/roles';
    const method = id ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: nombre })
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Guardado.');
        closeModal('modal-rol');
        setTimeout(() => location.reload(), 1500);
    } else {
        toast(data.message || 'Error.', 'error');
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
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Rol eliminado.');
        closeModal('modal-eliminar-rol');
        setTimeout(() => location.reload(), 1500);
    } else { toast(data.message || 'Error al eliminar.', 'error'); }
}

// asignarRolFetch y editarRolUsuario: ver la sección «ASIGNAR ROL» más abajo.
function verUsuariosRol(rolName) {
    showPanel('usuarios');
    const sel = document.querySelector('select[onchange="filterByRol(this.value)"]');
    if (sel) sel.value = rolName;
    filterByRol(rolName);
}

// ── CATEGORÍAS ──
async function cargarCategorias() {
    try {
        const res = await fetch('/api/admin/categorias', { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
        const data = await res.json();
        const tbody = document.getElementById('tbody-categorias');
        if (!tbody) return;
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Sin categorías.</td></tr>'; return; }
        tbody.innerHTML = data.map(c => `
            <tr>
                <td>${c.id_categoria}</td>
                <td class="name">${c.nombre_categoria}</td>
                <td style="color:var(--text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${c.descripcion ?? '—'}</td>
                <td>${c.estado ? '<span class="chip chip-green">Activo</span>' : '<span class="chip chip-red">Inactivo</span>'}</td>
                <td>
                    <div class="action-btns">
                        <button class="icon-btn" title="Editar" aria-label="Editar categoría" onclick="editarCategoria(${c.id_categoria},'${c.nombre_categoria?.replace(/'/g,"\\'")}','${c.descripcion?.replace(/'/g,"\\'") ?? ''}',${c.estado})">✏️</button>
                        <button class="icon-btn del" title="Eliminar" aria-label="Eliminar categoría" onclick="confirmarEliminarItem(${c.id_categoria},'${c.nombre_categoria}','categoria')">🗑️</button>
                    </div>
                </td>
            </tr>`).join('');
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
    const url = id ? `/api/admin/categorias/${id}` : '/api/admin/categorias';
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
        cargarCategorias();
    } else { toast(data.message || 'Error.', 'error'); }
}

// ── PUBLICACIONES ──
async function cargarPublicaciones() {
    try {
        const res = await fetch('/api/admin/publicaciones', { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
        const data = await res.json();
        const tbody = document.getElementById('tbody-publicaciones');
        if (!tbody) return;
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Sin publicaciones.</td></tr>'; return; }
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
                        <button class="icon-btn" title="Editar" aria-label="Editar publicación" onclick="editarPublicacion(${JSON.stringify(p).replace(/"/g,'&quot;')})">✏️</button>
                        <button class="icon-btn del" title="Eliminar" aria-label="Eliminar publicación" onclick="confirmarEliminarItem(${p.id_labor},'${p.titulo?.replace(/'/g,"\\'")}','publicacion')">🗑️</button>
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
    const url = id ? `/api/admin/publicaciones/${id}` : '/api/admin/publicaciones';
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
    } else { toast(data.message || 'Error.', 'error'); }
}

// ── POSTULANTES ──
async function cargarPostulantes() {
    try {
        const res = await fetch('/api/admin/postulantes', { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
        const data = await res.json();
        const tbody = document.getElementById('tbody-postulantes');
        if (!tbody) return;
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Sin postulantes.</td></tr>'; return; }
        tbody.innerHTML = data.map(p => `
            <tr>
                <td>${p.id_postulante}</td>
                <td class="name">${p.usuario?.nombre ?? '—'}</td>
                <td style="color:var(--text-muted);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${p.habilidades}</td>
                <td>${p.disponibilidad ?? '—'}</td>
                <td style="color:var(--accent);font-family:'Bebas Neue',cursive;font-size:1.1rem;">${p.tarifa ? 'Bs ' + p.tarifa : '—'}</td>
                <td>${p.estado ? '<span class="chip chip-green">Activo</span>' : '<span class="chip chip-red">Inactivo</span>'}</td>
                <td>
                    <div class="action-btns">
                        <button class="icon-btn" title="Editar" aria-label="Editar postulante" onclick="editarPostulante(${JSON.stringify(p).replace(/"/g,'&quot;')})">✏️</button>
                        <button class="icon-btn del" title="Eliminar" aria-label="Eliminar postulante" onclick="confirmarEliminarItem(${p.id_postulante},'${p.usuario?.nombre ?? 'postulante'}','postulante')">🗑️</button>
                    </div>
                </td>
            </tr>`).join('');
    } catch(e) { console.log('Error postulantes:', e); }
}

async function cargarTrabajadoresParaPostulante() {
    const sel = document.getElementById('post-cedula');
    if (!sel) return;
    try {
        const res = await fetch('/api/admin/usuarios', { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
        const data = await res.json();
        const trabajadores = data.filter(u => u.roles?.some(r => r.name === 'trabajador'));
        sel.innerHTML = trabajadores.length ? '<option value="">— Seleccione —</option>' : '<option value="">Sin trabajadores disponibles</option>';
        trabajadores.forEach(u => sel.innerHTML += `<option value="${u.cedula}">${u.nombre}</option>`);
    } catch(e) { console.log('Error trabajadores:', e); }
}

function abrirModalPostulante() {
    document.getElementById('modal-postulante-title').textContent = 'Nuevo Postulante';
    document.getElementById('post-id').value = '';
    document.getElementById('post-habilidades').value = '';
    document.getElementById('post-experiencia').value = '';
    document.getElementById('post-disponibilidad').value = 'Tiempo completo';
    document.getElementById('post-tarifa').value = '';
    document.getElementById('post-estado').value = '1';
    const sel = document.getElementById('post-cedula');
    sel.disabled = false;
    cargarTrabajadoresParaPostulante();
    openModal('modal-postulante');
}

function editarPostulante(p) {
    if (typeof p === 'string') p = JSON.parse(p.replace(/&quot;/g,'"'));
    document.getElementById('modal-postulante-title').textContent = 'Editar Postulante';
    document.getElementById('post-id').value = p.id_postulante;
    document.getElementById('post-habilidades').value = p.habilidades;
    document.getElementById('post-experiencia').value = p.experiencia ?? '';
    document.getElementById('post-disponibilidad').value = p.disponibilidad ?? 'Tiempo completo';
    document.getElementById('post-tarifa').value = p.tarifa ?? '';
    document.getElementById('post-estado').value = p.estado;
    const sel = document.getElementById('post-cedula');
    sel.innerHTML = `<option value="${p.cedula}">${p.usuario?.nombre ?? p.cedula}</option>`;
    sel.disabled = true;
    openModal('modal-postulante');
}

async function guardarPostulante() {
    const id = document.getElementById('post-id').value;
    const body = {
        cedula:         document.getElementById('post-cedula').value,
        habilidades:    document.getElementById('post-habilidades').value,
        experiencia:    document.getElementById('post-experiencia').value,
        disponibilidad: document.getElementById('post-disponibilidad').value,
        tarifa:         document.getElementById('post-tarifa').value,
        estado:         document.getElementById('post-estado').value,
    };
    const url = id ? `/api/admin/postulantes/${id}` : '/api/admin/postulantes';
    const method = id ? 'PUT' : 'POST';
    const res = await fetch(url, {
        method,
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Guardado.');
        closeModal('modal-postulante');
        document.getElementById('post-id').value = '';
        cargarPostulantes();
    } else { toast(data.message || 'Error.', 'error'); }
}

let eliminarItemId = null, eliminarItemTipo = null;
function confirmarEliminarItem(id, nombre, tipo) {
    eliminarItemId = id; eliminarItemTipo = tipo;
    document.getElementById('eliminar-item-nombre').textContent = nombre;
    openModal('modal-eliminar-item');
}

async function ejecutarEliminarItem() {
    const url = eliminarItemTipo === 'categoria' ? `/api/admin/categorias/${eliminarItemId}`
        : eliminarItemTipo === 'publicacion' ? `/api/admin/publicaciones/${eliminarItemId}`
        : eliminarItemTipo === 'postulante' ? `/api/admin/postulantes/${eliminarItemId}`
        : eliminarItemTipo === 'postulacion' ? `/api/admin/postulaciones/${eliminarItemId}`
        : eliminarItemTipo === 'contratacion' ? `/api/admin/contrataciones/${eliminarItemId}`
        : `/api/admin/calificaciones/${eliminarItemId}`;

    const res = await fetch(url, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }
    });
    const data = await res.json();
    if (res.ok) {
        toast(data.message || 'Eliminado correctamente.');
        closeModal('modal-eliminar-item');
        if (eliminarItemTipo === 'categoria') cargarCategorias();
        else if (eliminarItemTipo === 'publicacion') cargarPublicaciones();
        else if (eliminarItemTipo === 'postulante') cargarPostulantes();
        else if (eliminarItemTipo === 'postulacion') cargarPostulacionesTrans();
        else if (eliminarItemTipo === 'contratacion') cargarContratacionesTrans();
        else if (eliminarItemTipo === 'calificacion') cargarCalificacionesTrans();
    } else { toast(data.message || 'Error al eliminar.', 'error'); }
}

// ════════════════════════════════════════════════════════════════════
// ── CONEXIONES TRANSACCIONALES (POSTULACIONES, CONTRATACIONES, CALIFICACIONES)
// ════════════════════════════════════════════════════════════════════

async function cargarPostulacionesTrans() {
    try {
        const res = await fetch('/api/admin/postulaciones', { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
        const data = await res.json();
        const tbody = document.getElementById('tbody-postulaciones');
        if (!tbody) return;
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Sin postulaciones registradas.</td></tr>'; return; }
        tbody.innerHTML = data.map(p => `
            <tr>
                <td>${p.id_postulacion || p.id}</td>
                <td class="name">${p.trabajador?.nombre ?? p.cedula ?? '—'}</td>
                <td>${p.publicacion?.titulo ?? '—'}</td>
                <td>
                    <span class="chip ${p.estado === 'aceptada' ? 'chip-green' : p.estado === 'rechazada' ? 'chip-red' : 'chip-yellow'}">
                        ${p.estado?.toUpperCase() ?? 'PENDIENTE'}
                    </span>
                </td>
                <td>${p.created_at ? new Date(p.created_at).toLocaleDateString('es-ES') : '—'}</td>
                <td>
                    <div class="action-btns">
                        <button class="icon-btn del" title="Eliminar" aria-label="Eliminar postulación" onclick="confirmarEliminarItem(${p.id_postulacion || p.id},'Postulación de ${p.trabajador?.nombre ?? 'usuario'}','postulacion')">🗑️</button>
                    </div>
                </td>
            </tr>`).join('');
    } catch(e) { console.log('Error postulaciones:', e); }
}

async function cargarContratacionesTrans() {
    try {
        const res = await fetch('/api/admin/contrataciones', { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
        const data = await res.json();
        const tbody = document.getElementById('tbody-contrataciones');
        if (!tbody) return;
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Sin contrataciones registradas.</td></tr>'; return; }
        tbody.innerHTML = data.map(c => `
            <tr>
                <td>${c.id_contratacion || c.id}</td>
                <td class="name">${c.contratador?.nombre ?? '—'}</td>
                <td>${c.trabajador?.nombre ?? '—'}</td>
                <td>${c.publicacion?.titulo ?? '—'}</td>
                <td>
                    <span class="chip ${c.estado === 'finalizado' ? 'chip-blue' : c.estado === 'activo' ? 'chip-green' : 'chip-yellow'}">
                        ${c.estado?.toUpperCase() ?? 'ACTIVO'}
                    </span>
                </td>
                <td>
                    <div class="action-btns">
                        <button class="icon-btn del" title="Eliminar" aria-label="Eliminar contratación" onclick="confirmarEliminarItem(${c.id_contratacion || c.id},'Contratación #${c.id_contratacion || c.id}','contratacion')">🗑️</button>
                    </div>
                </td>
            </tr>`).join('');
    } catch(e) { console.log('Error contrataciones:', e); }
}

async function cargarCalificacionesTrans() {
    try {
        const res = await fetch('/api/admin/calificaciones', { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
        const data = await res.json();
        const tbody = document.getElementById('tbody-calificaciones');
        if (!tbody) return;
        if (!data.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Sin calificaciones registradas.</td></tr>'; return; }
        tbody.innerHTML = data.map(cal => `
            <tr>
                <td>${cal.id_calificacion || cal.id}</td>
                <td class="name">${cal.emisor?.nombre ?? '—'}</td>
                <td>${cal.receptor?.nombre ?? '—'}</td>
                <td style="color:var(--gold);font-weight:bold;">⭐ ${cal.puntuacion} / 5</td>
                <td style="color:var(--text-muted);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${cal.comentario ?? 'Sin comentario'}</td>
                <td>
                    <div class="action-btns">
                        <button class="icon-btn del" title="Eliminar" aria-label="Eliminar calificación" onclick="confirmarEliminarItem(${cal.id_calificacion || cal.id},'Calificación #${cal.id_calificacion || cal.id}','calificacion')">🗑️</button>
                    </div>
                </td>
            </tr>`).join('');
    } catch(e) { console.log('Error calificaciones:', e); }
}

async function cargarTodosRoles() {
    try {
        const res = await fetch('/api/admin/roles', { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
        const roles = await res.json();

        const fRol = document.getElementById('f-rol');
        if (fRol) {
            fRol.innerHTML = '<option value="">Sin rol</option>';
            roles.forEach(r => {
                const icon = r.name === 'administrador' ? '⚙️' : r.name === 'contratador' ? '💼' : '🔧';
                fRol.innerHTML += `<option value="${r.name}">${icon} ${r.name.charAt(0).toUpperCase() + r.name.slice(1)}</option>`;
            });
        }

        const filtroRol = document.querySelector('select[onchange="filterByRol(this.value)"]');
        if (filtroRol) {
            filtroRol.innerHTML = '<option value="">Todos los roles</option>';
            roles.forEach(r => {
                filtroRol.innerHTML += `<option value="${r.name}">${r.name.charAt(0).toUpperCase() + r.name.slice(1)}</option>`;
            });
        }

        // Asignar Rol: selector del formulario y filtro de la tabla (por id, conservando la selección actual)
        arEstado.roles = Array.isArray(roles) ? roles : [];
        const opciones = arEstado.roles.map(r => `<option value="${esc(r.name)}">${esc(iconoRol(r.name))} ${esc(capitalizar(r.name))}</option>`).join('');
        const arRol = document.getElementById('ar-rol-select');
        if (arRol) {
            const previo = arRol.value;
            arRol.innerHTML = '<option value="">— Seleccione rol —</option>' + opciones;
            arRol.value = previo;
        }
        const arFiltro = document.getElementById('ar-filtro-rol');
        if (arFiltro) {
            const previo = arFiltro.value;
            arFiltro.innerHTML = '<option value="">Todos los roles</option>' + opciones;
            arFiltro.value = previo;
        }
    } catch(e) { console.log('Error cargando roles:', e); }
}

// ── Utilidades para el código NUEVO (pagos y KPIs) ──
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const bs = n => 'Bs ' + Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fechaCorta = s => {
    if (!s) return '—';
    const [y, m, d] = String(s).slice(0, 10).split('-').map(Number);
    return (y && m && d) ? new Date(y, m - 1, d).toLocaleDateString('es-BO') : String(s);
};
const fechaHora = s => s ? String(s).replace('T', ' ').slice(0, 19) : '—';
const METODOS_PAGO = { efectivo: 'Efectivo', transferencia: 'Transferencia', qr: 'QR', deposito: 'Depósito', otro: 'Otro' };
const ESTADO_PAGO = { sin_pagos: 'Sin pagos', parcial: 'Parcial', completo: 'Completo' };
const chipPago = e => e === 'confirmado' ? 'chip-green' : e === 'anulado' ? 'chip-red' : 'chip-yellow';

// Petición JSON con Bearer; lanza Error con .status, .errors y el mensaje del servidor.
async function apiJson(url, method = 'GET', body = null) {
    const res = await fetch(url, {
        method,
        headers: Object.assign({ 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }, body ? { 'Content-Type': 'application/json' } : {}),
        body: body ? JSON.stringify(body) : null
    });
    let data = null; try { data = await res.json(); } catch (e) {}
    if (!res.ok) {
        const err = new Error((data && data.errors) ? Object.values(data.errors)[0][0] : (data && data.message) || 'Error de conexión.');
        err.status = res.status;
        err.errors = (data && data.errors) || null;
        throw err;
    }
    return data;
}

// ── KPIs (Incremento 5) ──
async function cargarKpis() {
    const ids = ['kpi-publicaciones-activas', 'kpi-contrataciones-activas', 'kpi-pagos-confirmados'];
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    ids.forEach(id => set(id, '…'));
    try {
        const k = await apiJson('/api/admin/kpis');
        set('kpi-publicaciones-activas', Number(k.publicaciones_activas ?? 0).toLocaleString('es-BO'));
        set('kpi-contrataciones-activas', Number(k.contrataciones_activas ?? 0).toLocaleString('es-BO'));
        set('kpi-pagos-confirmados', bs(k.pagos_confirmados_bs));
        if (k.generado_en) set('kpi-generado', 'Simulado · generado ' + fechaHora(k.generado_en));
    } catch (e) {
        ids.forEach(id => set(id, '—'));
        set('kpi-generado', 'No se pudieron cargar los indicadores.');
        console.log('Error KPIs:', e);
    }
}

// ── PAGOS (registros SIMULADOS) ──
const pagosEstado = { page: 1, lastPage: 1, accionId: null };

function filtrarPagos() {
    pagosEstado.page = 1;
    cargarPagosAdmin();
}

function paginaPagos(delta) {
    const nueva = pagosEstado.page + delta;
    if (nueva < 1 || nueva > pagosEstado.lastPage) return;
    pagosEstado.page = nueva;
    cargarPagosAdmin();
}

async function cargarPagosAdmin() {
    const tbody = document.getElementById('tbody-pagos');
    if (!tbody) return;
    const filtros = {
        page: pagosEstado.page,
        per_page: 15,
        estado: document.getElementById('pagos-filtro-estado')?.value,
        desde: document.getElementById('pagos-filtro-desde')?.value,
        hasta: document.getElementById('pagos-filtro-hasta')?.value,
    };
    const query = Object.entries(filtros)
        .filter(([, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v))
        .join('&');

    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);">Cargando pagos...</td></tr>';
    try {
        const r = await apiJson('/api/admin/pagos?' + query);
        const pagos = r.data || [];
        const meta = r.meta || {};
        const tot = r.totales || {};
        if (r.aviso) document.getElementById('pagos-aviso').textContent = 'OPERACIONES SIMULADAS — ' + r.aviso;

        ['pendiente', 'confirmado', 'anulado'].forEach(k => {
            const t = tot[k] || {};
            document.getElementById(`pagos-tot-${k}-monto`).textContent = bs(t.monto);
            document.getElementById(`pagos-tot-${k}-cant`).textContent = Number(t.cantidad ?? 0) + ' pago(s)';
        });

        pagosEstado.lastPage = Number(meta.last_page) || 1;
        pagosEstado.page = Number(meta.current_page) || pagosEstado.page;
        document.getElementById('pagos-paginacion-info').textContent = meta.total
            ? `Mostrando ${meta.from ?? 0}–${meta.to ?? 0} de ${meta.total} · Página ${pagosEstado.page} de ${pagosEstado.lastPage}`
            : '';
        document.getElementById('pagos-prev').disabled = pagosEstado.page <= 1;
        document.getElementById('pagos-next').disabled = pagosEstado.page >= pagosEstado.lastPage;

        if (!pagos.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);">Sin pagos registrados para los filtros seleccionados.</td></tr>';
            return;
        }
        tbody.innerHTML = pagos.map(p => {
            const id = Number(p.id_pago);
            const ctr = p.contratacion || {};
            const acciones = [`<button class="icon-btn" title="Ver detalles" aria-label="Ver detalles del pago" onclick="verPago(${id})">👁️</button>`];
            if (p.estado === 'pendiente') acciones.push(`<button class="icon-btn" title="Confirmar pago" aria-label="Confirmar pago" onclick="confirmarPago(${id})">✅</button>`);
            if (p.estado === 'pendiente' || p.estado === 'confirmado') acciones.push(`<button class="icon-btn del" title="Anular pago" aria-label="Anular pago" onclick="abrirAnularPago(${id})">⛔</button>`);
            return `<tr>
                <td>${id}</td>
                <td>${esc(fechaCorta(p.fecha_pago))}</td>
                <td class="name">#${esc(ctr.id_contratacion ?? p.id_contratacion)} · ${esc(p.publicacion?.titulo ?? '—')}</td>
                <td>${esc(p.contratador?.nombre ?? '—')}</td>
                <td>${esc(p.trabajador?.nombre ?? '—')}</td>
                <td>${esc(METODOS_PAGO[p.metodo] ?? p.metodo)}</td>
                <td>${esc(bs(p.monto))}</td>
                <td><span class="chip ${chipPago(p.estado)}">${esc(p.estado).toUpperCase()}</span> <span class="chip chip-yellow">SIM</span></td>
                <td><div class="action-btns">${acciones.join('')}</div></td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;color:#ff7070;">${esc(e.message)}</td></tr>`;
        console.log('Error pagos:', e);
    }
}

async function verPago(id) {
    const body = document.getElementById('pago-detalle-body');
    document.getElementById('pago-detalle-id').textContent = '#' + id;
    body.textContent = 'Cargando...';
    openModal('modal-pago-detalle');
    try {
        const r = await apiJson('/api/admin/pagos/' + encodeURIComponent(id));
        const p = r.data || {};
        const a = p.auditoria || {};
        const res = p.resumen || {};
        const fila = (k, v) => `<div style="display:flex;justify-content:space-between;gap:1rem;padding:.35rem 0;border-bottom:1px solid var(--border);"><span style="color:var(--text-muted);">${esc(k)}</span><span style="color:white;text-align:right;">${esc(v)}</span></div>`;
        const titulo = t => `<div class="form-label" style="margin-top:1rem;">${esc(t)}</div>`;
        body.innerHTML = `<div style="padding:.6rem .9rem;border:1px solid rgba(245,158,11,.3);border-radius:10px;color:var(--gold);font-size:.8rem;">${esc(r.aviso || 'Registro simulado: no representa dinero real.')}</div>`
            + titulo('Datos del pago')
            + fila('Estado', String(p.estado ?? '').toUpperCase())
            + fila('Monto', bs(p.monto))
            + fila('Comisión', `${bs(p.monto_comision)} (${p.porcentaje_comision ?? 0}%)`)
            + fila('Neto', bs(p.monto_neto))
            + fila('Método', METODOS_PAGO[p.metodo] ?? p.metodo ?? '—')
            + fila('Fecha de pago', fechaCorta(p.fecha_pago))
            + fila('Observación', p.observacion || '—')
            + fila('Proveedor / referencia', `${p.proveedor ?? '—'} · ${p.referencia_externa ?? '—'}`)
            + fila('Contratación', `#${p.contratacion?.id_contratacion ?? p.id_contratacion ?? '—'} (${p.contratacion?.estado ?? '—'})`)
            + fila('Labor', p.publicacion?.titulo ?? '—')
            + fila('Contratador', p.contratador?.nombre ?? '—')
            + fila('Trabajador', p.trabajador?.nombre ?? '—')
            + titulo('Auditoría')
            + fila('Registrado por', `${a.registrado_por?.nombre ?? '—'} · ${fechaHora(a.fecha_registro ?? p.fecha_registro)}`)
            + fila('Confirmado por', a.confirmado_por ? `${a.confirmado_por.nombre ?? '—'} · ${fechaHora(a.fecha_confirmacion)}` : '—')
            + fila('Anulado por', a.anulado_por ? `${a.anulado_por.nombre ?? '—'} · ${fechaHora(a.fecha_anulacion)}` : '—')
            + fila('Motivo de anulación', a.motivo_anulacion || p.motivo_anulacion || '—')
            + titulo('Resumen de la contratación')
            + fila('Monto acordado', bs(res.monto_acordado))
            + fila('Pagado (no anulado)', bs(res.pagado_no_anulado))
            + fila('Confirmado', bs(res.pagado_confirmado))
            + fila('Pendiente de confirmar', bs(res.pagado_pendiente))
            + fila('Saldo disponible', bs(res.saldo_disponible))
            + fila('Estado de pago', ESTADO_PAGO[res.estado_pago] ?? res.estado_pago ?? '—');
    } catch (e) {
        body.innerHTML = `<span style="color:#ff7070;">${esc(e.message)}</span>`;
    }
}

function confirmarPago(id) {
    pagosEstado.accionId = id;
    document.getElementById('pago-confirmar-nombre').textContent = '#' + id;
    openModal('modal-pago-confirmar');
}

async function ejecutarConfirmarPago() {
    const btn = document.getElementById('pago-confirmar-btn');
    btn.disabled = true;
    try {
        const r = await apiJson('/api/admin/pagos/' + encodeURIComponent(pagosEstado.accionId) + '/confirmar', 'PUT');
        toast((r && r.message) || 'Pago confirmado.');
        closeModal('modal-pago-confirmar');
        await Promise.all([cargarPagosAdmin(), cargarKpis()]);
    } catch (e) {
        toast(e.message, 'error');
    } finally { btn.disabled = false; }
}

function abrirAnularPago(id) {
    pagosEstado.accionId = id;
    document.getElementById('pago-anular-nombre').textContent = '#' + id;
    document.getElementById('pago-anular-motivo').value = '';
    document.getElementById('pago-anular-error').textContent = '';
    openModal('modal-pago-anular');
}

async function ejecutarAnularPago() {
    const btn = document.getElementById('pago-anular-btn');
    const errEl = document.getElementById('pago-anular-error');
    errEl.textContent = '';
    btn.disabled = true;
    try {
        const r = await apiJson('/api/admin/pagos/' + encodeURIComponent(pagosEstado.accionId) + '/anular', 'PUT', {
            motivo_anulacion: document.getElementById('pago-anular-motivo').value.trim()
        });
        toast((r && r.message) || 'Pago anulado.');
        closeModal('modal-pago-anular');
        await Promise.all([cargarPagosAdmin(), cargarKpis()]);
    } catch (e) {
        if (e.errors && e.errors.motivo_anulacion) errEl.textContent = e.errors.motivo_anulacion[0];
        else errEl.textContent = e.message;
        toast(e.message, 'error');
    } finally { btn.disabled = false; }
}

// ── ASIGNAR ROL ──
// Búsqueda de usuarios en servidor (GET /api/admin/asignaciones), resumen, capacidades del rol derivadas de las
// rutas reales (GET /api/admin/roles/{id}/capacidades) y asignar/quitar sin recargar la página.
const AR_DEBOUNCE_MS = 300;
const arEstado = {
    roles: [],            // GET /api/admin/roles (id, name, users_count)
    usuario: null,        // usuario seleccionado en el formulario
    resultados: [],       // resultados del buscador del formulario
    activo: -1,           // opción resaltada con el teclado
    secBuscador: 0,       // secuencia del debounce del buscador del formulario
    secTabla: 0,          // secuencia del debounce del buscador de la tabla
    secCapacidades: 0,    // descarta respuestas de capacidades obsoletas
    filas: [],            // filas visibles de la tabla
    page: 1,
    lastPage: 1,
    quitar: null,         // fila pendiente de confirmación para quitar rol
};
const AR_SIN_FECHA = 'La base de datos no guarda la fecha de asignación de roles (model_has_roles no tiene fechas).';

function capitalizar(s) { s = String(s ?? ''); return s.charAt(0).toUpperCase() + s.slice(1); }
function iconoRol(n) { return n === 'administrador' ? '⚙️' : n === 'contratador' ? '💼' : n === 'trabajador' ? '🔧' : '🏷️'; }
function chipRolClase(n) { return n === 'administrador' ? 'chip-yellow' : n === 'contratador' ? 'chip-blue' : n === 'trabajador' ? 'chip-green' : 'chip-accent'; }
function chipsRoles(roles) {
    return (roles && roles.length)
        ? roles.map(r => `<span class="chip ${chipRolClase(r)}">${esc(iconoRol(r))} ${esc(capitalizar(r))}</span>`).join(' ')
        : '<span class="chip chip-gray">Sin rol asignado</span>';
}
function chipEstado(e) { return Number(e) === 1 ? '<span class="chip chip-green">Activo</span>' : '<span class="chip chip-red">Inactivo</span>'; }
function arQuery(obj) {
    return Object.entries(obj)
        .filter(([, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v)).join('&');
}
function arScroll(id) { document.getElementById(id)?.scrollIntoView?.({ behavior: 'smooth', block: 'start' }); }

async function cargarResumenAsignaciones() {
    const ids = { total_usuarios: 'ar-resumen-total', con_rol: 'ar-resumen-con', roles_disponibles: 'ar-resumen-roles', sin_rol: 'ar-resumen-sin' };
    try {
        const r = await apiJson('/api/admin/asignaciones/resumen');
        Object.entries(ids).forEach(([k, id]) => { const el = document.getElementById(id); if (el) el.textContent = Number(r[k] ?? 0).toLocaleString('es-BO'); });
    } catch (e) {
        Object.values(ids).forEach(id => { const el = document.getElementById(id); if (el) el.textContent = '—'; });
        console.log('Error resumen de asignaciones:', e);
    }
}

// ── Buscador de usuario del formulario (debounce + teclado) ──
function buscarUsuarioAsignar(texto) {
    const sec = ++arEstado.secBuscador;
    const q = String(texto ?? '').trim();
    if (!q) { arEstado.resultados = []; pintarListaUsuarios(); return; }
    setTimeout(() => { if (sec === arEstado.secBuscador) ejecutarBusquedaUsuario(q, sec); }, AR_DEBOUNCE_MS);
}

async function ejecutarBusquedaUsuario(q, sec) {
    try {
        const r = await apiJson('/api/admin/asignaciones?' + arQuery({ buscar: q, per_page: 8 }));
        if (sec !== arEstado.secBuscador) return; // respuesta obsoleta
        arEstado.resultados = r.data || [];
        arEstado.activo = arEstado.resultados.length ? 0 : -1;
        pintarListaUsuarios(true);
    } catch (e) {
        toast(e.message, 'error');
    }
}

function pintarListaUsuarios(buscado = false) {
    const lista = document.getElementById('ar-usuario-lista');
    const input = document.getElementById('ar-usuario-buscar');
    if (!lista) return;
    const abierta = buscado || arEstado.resultados.length > 0;
    lista.hidden = !abierta;
    input?.setAttribute?.('aria-expanded', abierta ? 'true' : 'false');
    if (!arEstado.resultados.length) {
        lista.innerHTML = buscado ? '<li class="ar-lista-vacia" role="option" aria-disabled="true">Sin usuarios que coincidan.</li>' : '';
        input?.removeAttribute?.('aria-activedescendant');
        return;
    }
    lista.innerHTML = arEstado.resultados.map((u, i) => `
        <li role="option" id="ar-opt-${i}" class="ar-opcion${i === arEstado.activo ? ' activa' : ''}" aria-selected="${i === arEstado.activo}" onclick="seleccionarUsuarioAsignar(${i})">
            <span class="ar-opcion-nombre">${esc(u.nombre)}</span>
            <span class="ar-opcion-meta">${esc(u.email)} · ${esc((u.roles || []).map(capitalizar).join(', ') || 'Sin rol')}</span>
        </li>`).join('');
    if (arEstado.activo >= 0) input?.setAttribute?.('aria-activedescendant', 'ar-opt-' + arEstado.activo);
}

function teclaUsuarioAsignar(e) {
    const n = arEstado.resultados.length;
    if (e.key === 'ArrowDown' && n) { e.preventDefault(); arEstado.activo = (arEstado.activo + 1) % n; pintarListaUsuarios(true); }
    else if (e.key === 'ArrowUp' && n) { e.preventDefault(); arEstado.activo = (arEstado.activo - 1 + n) % n; pintarListaUsuarios(true); }
    else if (e.key === 'Enter') { e.preventDefault(); if (arEstado.activo >= 0 && n) seleccionarUsuarioAsignar(arEstado.activo); }
    else if (e.key === 'Escape') { arEstado.resultados = []; arEstado.activo = -1; pintarListaUsuarios(); }
}

function seleccionarUsuarioAsignar(i) {
    const u = arEstado.resultados[i];
    if (!u) return;
    fijarUsuarioAsignar(u);
    arEstado.resultados = []; arEstado.activo = -1;
    pintarListaUsuarios();
}

function fijarUsuarioAsignar(u) {
    arEstado.usuario = u;
    const hidden = document.getElementById('ar-usuario-id');
    if (hidden) hidden.value = u ? u.cedula : '';
    const input = document.getElementById('ar-usuario-buscar');
    if (input && u) input.value = u.nombre;
    pintarUsuarioInfo();
    actualizarEstadoGuardar();
}

function pintarUsuarioInfo() {
    const box = document.getElementById('ar-usuario-info');
    if (!box) return;
    const u = arEstado.usuario;
    if (!u) { box.innerHTML = '<div class="ar-vacio">Busca y selecciona un usuario para ver su información.</div>'; return; }
    const fila = (k, v) => `<div class="ar-dato"><span>${esc(k)}</span><span>${v}</span></div>`;
    box.innerHTML = `<div class="ar-usuario-nombre">👤 ${esc(u.nombre)}</div>`
        + fila('Correo', esc(u.email ?? '—'))
        + fila('Cédula', esc(u.cedula))
        + fila('Estado', u.estado === undefined ? '—' : chipEstado(u.estado))
        + fila('Rol actual', u.roles ? chipsRoles(u.roles) : '—');
}

function actualizarEstadoGuardar() {
    const btn = document.getElementById('ar-guardar');
    const aviso = document.getElementById('ar-aviso');
    const rol = document.getElementById('ar-rol-select')?.value || '';
    const u = arEstado.usuario;
    const mismo = !!(u && rol && Array.isArray(u.roles) && u.roles.length === 1 && u.roles[0] === rol);
    if (aviso) {
        aviso.hidden = !mismo;
        aviso.textContent = mismo ? `${u.nombre} ya tiene el rol ${capitalizar(rol)}. Elige otro rol para cambiarlo.` : '';
    }
    if (btn) btn.disabled = !(u && rol) || mismo;
}

// ── Información y capacidades del rol (columna derecha) ──
function cambiarRolAsignar(nombre) {
    actualizarEstadoGuardar();
    return mostrarRolAsignacion(nombre);
}

async function mostrarRolAsignacion(nombre) {
    const info = document.getElementById('ar-rol-info');
    const permisos = document.getElementById('ar-rol-permisos');
    const rol = arEstado.roles.find(r => r.name === nombre);
    const sec = ++arEstado.secCapacidades;
    if (!rol) {
        if (info) info.innerHTML = '<div class="ar-vacio">Selecciona un rol para ver su información.</div>';
        if (permisos) permisos.innerHTML = '<div class="ar-vacio">Selecciona un rol para ver a qué módulos da acceso.</div>';
        return;
    }
    const pintarInfo = (r, descripcion) => {
        if (!info) return;
        info.innerHTML = `<div class="ar-usuario-nombre">${esc(iconoRol(r.name))} ${esc(capitalizar(r.name))}</div>`
            + `<div class="ar-dato"><span>Descripción</span><span>${descripcion ? esc(descripcion) : '<em class="ar-tenue">Sin descripción registrada</em>'}</span></div>`
            + `<div class="ar-dato"><span>Usuarios con este rol</span><span>${esc(Number(r.users_count ?? 0))}</span></div>`;
    };
    pintarInfo(rol, null);
    if (permisos) permisos.innerHTML = '<div class="ar-vacio">Cargando permisos...</div>';
    try {
        const r = await apiJson('/api/admin/roles/' + encodeURIComponent(rol.id) + '/capacidades');
        if (sec !== arEstado.secCapacidades) return;
        pintarInfo(r.rol || rol, r.descripcion);
        const caps = r.capacidades || [];
        const spatie = r.permisos_spatie || [];
        let html = caps.length
            ? '<ul class="ar-capacidades">' + caps.map(c => `<li><span class="ar-cap-icono" aria-hidden="true">${esc(c.icono)}</span><span class="ar-cap-texto"><strong>${esc(c.etiqueta)}</strong><small>${esc((c.metodos || []).join(' · '))} · ${esc(c.rutas)} ruta(s)</small></span></li>`).join('') + '</ul>'
            : '<div class="ar-vacio">Este rol no tiene rutas de la API asociadas: no da acceso a ningún módulo.</div>';
        if (spatie.length) html += `<div class="ar-dato"><span>Permisos Spatie</span><span>${spatie.map(esc).join(', ')}</span></div>`;
        if (r.nota_limitacion) html += `<p class="ar-nota">ℹ️ ${esc(r.nota_limitacion)}</p>`;
        if (permisos) permisos.innerHTML = html;
    } catch (e) {
        if (sec !== arEstado.secCapacidades) return;
        if (permisos) permisos.innerHTML = `<div class="ar-error">${esc(e.message)}</div>`;
        toast(e.message, 'error');
    }
}

// ── Asignar (submit del formulario «Guardar +») ──
async function asignarRolFetch(e) {
    e?.preventDefault?.();
    const usuarioId = document.getElementById('ar-usuario-id')?.value || '';
    const rol = document.getElementById('ar-rol-select')?.value || '';
    if (!usuarioId || !rol) { toast('Selecciona un usuario y un rol.', 'error'); return; }
    const btn = document.getElementById('ar-guardar');
    if (btn) btn.disabled = true;
    try {
        const r = await apiJson('/api/admin/asignar-rol', 'POST', { usuario_id: usuarioId, rol });
        toast((r && r.message) || 'Rol asignado.');
        if (arEstado.usuario && String(arEstado.usuario.cedula) === String(usuarioId)) {
            arEstado.usuario = Object.assign({}, arEstado.usuario, { roles: [rol] });
            pintarUsuarioInfo();
        }
        await refrescarAsignaciones();
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        actualizarEstadoGuardar();
    }
}

async function refrescarAsignaciones() {
    await Promise.all([cargarResumenAsignaciones(), cargarAsignaciones(), cargarTodosRoles(), cargarHistorialAsignaciones()]);
    const rol = document.getElementById('ar-rol-select')?.value;
    if (rol) await mostrarRolAsignacion(rol);
}

// Precarga un usuario en el formulario (desde la tabla o desde otros paneles).
async function editarRolUsuario(id, nombre) {
    showPanel('asignar-rol');
    const local = arEstado.filas.find(f => String(f.cedula) === String(id));
    fijarUsuarioAsignar(local || { cedula: id, nombre: nombre ?? String(id) });
    arScroll('ar-col-form');
    if (local) return;
    try {
        const r = await apiJson('/api/admin/asignaciones?' + arQuery({ buscar: nombre ?? '', per_page: 50 }));
        const u = (r.data || []).find(f => String(f.cedula) === String(id));
        if (u && arEstado.usuario && String(arEstado.usuario.cedula) === String(id)) fijarUsuarioAsignar(u);
    } catch (e) { console.log('Error precargando usuario:', e); }
}

// ── Tabla «Usuarios y roles asignados» ──
function buscarTablaAsignaciones(texto) {
    const sec = ++arEstado.secTabla;
    const limpiar = document.getElementById('ar-tabla-limpiar');
    if (limpiar) limpiar.hidden = !String(texto ?? '').length;
    setTimeout(() => { if (sec === arEstado.secTabla) { arEstado.page = 1; cargarAsignaciones(); } }, AR_DEBOUNCE_MS);
}

function limpiarBusquedaAsignaciones() {
    const input = document.getElementById('ar-tabla-buscar');
    if (input) input.value = '';
    const limpiar = document.getElementById('ar-tabla-limpiar');
    if (limpiar) limpiar.hidden = true;
    arEstado.secTabla++;
    arEstado.page = 1;
    return cargarAsignaciones();
}

function filtrarAsignaciones() {
    arEstado.page = 1;
    return cargarAsignaciones();
}

function paginaAsignaciones(delta) {
    const nueva = arEstado.page + delta;
    if (nueva < 1 || nueva > arEstado.lastPage) return;
    arEstado.page = nueva;
    return cargarAsignaciones();
}

async function cargarAsignaciones() {
    const tbody = document.getElementById('tbody-asignaciones');
    if (!tbody) return;
    const filtros = {
        buscar: (document.getElementById('ar-tabla-buscar')?.value || '').trim(),
        rol: document.getElementById('ar-filtro-rol')?.value,
        estado: document.getElementById('ar-filtro-estado')?.value,
        con_rol: document.getElementById('ar-filtro-conrol')?.value,
        page: arEstado.page,
        per_page: 10,
    };
    tbody.innerHTML = '<tr><td colspan="6" class="ar-celda-info">Cargando usuarios...</td></tr>';
    try {
        const r = await apiJson('/api/admin/asignaciones?' + arQuery(filtros));
        const meta = r.meta || {};
        arEstado.filas = r.data || [];
        arEstado.lastPage = Number(meta.last_page) || 1;
        arEstado.page = Number(meta.current_page) || arEstado.page;
        const info = document.getElementById('ar-paginacion-info');
        if (info) info.textContent = meta.total
            ? `Mostrando ${meta.from ?? 0}–${meta.to ?? 0} de ${meta.total} · Página ${arEstado.page} de ${arEstado.lastPage}`
            : '';
        const prev = document.getElementById('ar-prev'); if (prev) prev.disabled = arEstado.page <= 1;
        const next = document.getElementById('ar-next'); if (next) next.disabled = arEstado.page >= arEstado.lastPage;

        if (!arEstado.filas.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="ar-celda-info">No se encontraron usuarios con los filtros seleccionados.</td></tr>';
            return;
        }
        tbody.innerHTML = arEstado.filas.map((u, i) => {
            const tieneRol = (u.roles || []).length > 0;
            const acciones = [
                tieneRol ? `<button class="icon-btn" title="Ver rol" aria-label="Ver información y permisos del rol" onclick="verRolFila(${i})">👁️</button>` : '',
                `<button class="icon-btn" title="Cambiar rol" aria-label="Cambiar el rol de este usuario" onclick="cambiarRolFila(${i})">✏️</button>`,
                tieneRol ? `<button class="icon-btn del" title="Quitar rol" aria-label="Quitar el rol de este usuario" onclick="confirmarQuitarRol(${i})">🗑️</button>` : '',
            ].join('');
            return `<tr>
                <td class="name" data-label="Nombre">${esc(u.nombre)}</td>
                <td data-label="Correo">${esc(u.email)}</td>
                <td data-label="Rol actual">${chipsRoles(u.roles)}</td>
                <td data-label="Estado">${chipEstado(u.estado)}</td>
                <td data-label="Fecha de asignación"><span class="ar-tenue" title="${esc(AR_SIN_FECHA)}">No disponible</span></td>
                <td data-label="Acciones"><div class="action-btns">${acciones}</div></td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" class="ar-celda-info ar-error">${esc(e.message)}</td></tr>`;
        toast(e.message, 'error');
    }
}

function verRolFila(i) {
    const u = arEstado.filas[i];
    if (!u || !(u.roles || []).length) return;
    mostrarRolAsignacion(u.roles[0]);
    arScroll('ar-col-rol');
}

function cambiarRolFila(i) {
    const u = arEstado.filas[i];
    if (u) editarRolUsuario(u.cedula, u.nombre);
}

function confirmarQuitarRol(i) {
    const u = arEstado.filas[i];
    if (!u) return;
    arEstado.quitar = u;
    document.getElementById('quitar-rol-nombre').textContent = u.nombre;
    document.getElementById('quitar-rol-rol').textContent = (u.roles || []).map(capitalizar).join(', ') || '—';
    document.getElementById('quitar-rol-error').textContent = '';
    openModal('modal-quitar-rol');
}

async function ejecutarQuitarRol() {
    const u = arEstado.quitar;
    if (!u) return;
    const btn = document.getElementById('quitar-rol-btn');
    const errEl = document.getElementById('quitar-rol-error');
    errEl.textContent = '';
    btn.disabled = true;
    try {
        const r = await apiJson('/api/admin/quitar-rol', 'POST', { usuario_id: u.cedula });
        toast((r && r.message) || 'Rol removido.');
        closeModal('modal-quitar-rol');
        arEstado.quitar = null;
        if (arEstado.usuario && String(arEstado.usuario.cedula) === String(u.cedula)) {
            arEstado.usuario = Object.assign({}, arEstado.usuario, { roles: [] });
            pintarUsuarioInfo();
            actualizarEstadoGuardar();
        }
        await refrescarAsignaciones();
    } catch (e) {
        errEl.textContent = e.message;
        toast(e.message, 'error');
    } finally { btn.disabled = false; }
}

// ── Historial de asignaciones (GET /api/admin/asignaciones/historial, solo lectura) ──
const AR_ACCIONES = {
    asignacion: { icono: '➕', etiqueta: 'Asignación', chip: 'chip-green' },
    cambio:     { icono: '🔄', etiqueta: 'Cambio',     chip: 'chip-blue' },
    retirada:   { icono: '➖', etiqueta: 'Retirada',   chip: 'chip-red' },
};
const arHistorial = { page: 1, lastPage: 1, filas: [], sec: 0, secBuscar: 0, origenFoco: null };

function chipAccionHistorial(h) {
    const a = AR_ACCIONES[h.accion] || { icono: '🔹', etiqueta: h.accion_etiqueta || h.accion, chip: 'chip-gray' };
    return `<span class="chip ${a.chip} ar-chip-accion"><span aria-hidden="true">${esc(a.icono)}</span> ${esc(h.accion_etiqueta || a.etiqueta)}</span>`;
}
function rolHistorial(r) { return r ? `${esc(iconoRol(r))} ${esc(capitalizar(r))}` : '<span class="ar-tenue">Sin rol</span>'; }
function adminHistorial(a) { return a ? esc(a.nombre || ('Cédula ' + (a.cedula ?? '—'))) : '<span class="ar-tenue">No registrado</span>'; }

function buscarHistorialAsignaciones(texto) {
    const sec = ++arHistorial.secBuscar;
    const limpiar = document.getElementById('arh-buscar-limpiar');
    if (limpiar) limpiar.hidden = !String(texto ?? '').length;
    setTimeout(() => { if (sec === arHistorial.secBuscar) { arHistorial.page = 1; cargarHistorialAsignaciones(); } }, AR_DEBOUNCE_MS);
}

function limpiarBusquedaHistorial() {
    const input = document.getElementById('arh-buscar');
    if (input) input.value = '';
    const limpiar = document.getElementById('arh-buscar-limpiar');
    if (limpiar) limpiar.hidden = true;
    arHistorial.secBuscar++;
    arHistorial.page = 1;
    return cargarHistorialAsignaciones();
}

function filtrarHistorial() {
    arHistorial.page = 1;
    return cargarHistorialAsignaciones();
}

function limpiarFiltrosHistorial() {
    for (const id of ['arh-buscar', 'arh-accion', 'arh-desde', 'arh-hasta']) { const el = document.getElementById(id); if (el) el.value = ''; }
    const limpiar = document.getElementById('arh-buscar-limpiar');
    if (limpiar) limpiar.hidden = true;
    arHistorial.secBuscar++;
    arHistorial.page = 1;
    return cargarHistorialAsignaciones();
}

function paginaHistorial(delta) {
    const nueva = arHistorial.page + delta;
    if (nueva < 1 || nueva > arHistorial.lastPage) return;
    arHistorial.page = nueva;
    return cargarHistorialAsignaciones();
}

async function cargarHistorialAsignaciones() {
    const tbody = document.getElementById('tbody-historial');
    if (!tbody) return;
    const sec = ++arHistorial.sec;
    const errEl = document.getElementById('arh-error');
    const wrap = document.getElementById('arh-tabla-wrap');
    const filtros = {
        buscar: (document.getElementById('arh-buscar')?.value || '').trim(),
        accion: document.getElementById('arh-accion')?.value,
        desde: document.getElementById('arh-desde')?.value,
        hasta: document.getElementById('arh-hasta')?.value,
        page: arHistorial.page,
        per_page: 10,
    };
    if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
    wrap?.setAttribute?.('aria-busy', 'true');
    tbody.innerHTML = '<tr><td colspan="7" class="ar-celda-info">⏳ Cargando historial...</td></tr>';
    try {
        const r = await apiJson('/api/admin/asignaciones/historial?' + arQuery(filtros));
        if (sec !== arHistorial.sec) return; // respuesta obsoleta
        const meta = (r && r.meta) || {};
        arHistorial.filas = (r && r.data) || [];
        arHistorial.lastPage = Number(meta.last_page) || 1;
        arHistorial.page = Number(meta.current_page) || arHistorial.page;
        const info = document.getElementById('arh-paginacion-info');
        if (info) info.textContent = meta.total
            ? `Mostrando ${meta.from ?? 0}–${meta.to ?? 0} de ${meta.total} · Página ${arHistorial.page} de ${arHistorial.lastPage}`
            : '';
        const prev = document.getElementById('arh-prev'); if (prev) prev.disabled = arHistorial.page <= 1;
        const next = document.getElementById('arh-next'); if (next) next.disabled = arHistorial.page >= arHistorial.lastPage;

        if (!arHistorial.filas.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="ar-celda-info">No hay registros de cambios de rol con los filtros seleccionados.</td></tr>';
            return;
        }
        tbody.innerHTML = arHistorial.filas.map((h, i) => `<tr>
                <td class="name" data-label="Usuario">${esc(h.usuario?.nombre)}<small class="ar-celda-sub">CI ${esc(h.usuario?.cedula)}</small></td>
                <td data-label="Rol anterior">${rolHistorial(h.rol_anterior)}</td>
                <td data-label="Nuevo rol">${rolHistorial(h.rol_nuevo)}</td>
                <td data-label="Acción">${chipAccionHistorial(h)}</td>
                <td data-label="Administrador responsable">${adminHistorial(h.admin)}</td>
                <td data-label="Fecha y hora"><time datetime="${esc(h.fecha)}">${esc(h.fecha_texto || fechaHora(h.fecha))}</time></td>
                <td data-label="Opciones"><button type="button" class="btn btn-ghost ar-ver-detalle" aria-label="Ver detalle del cambio de rol de ${esc(h.usuario?.nombre)}" onclick="verDetalleHistorial(${i})"><span aria-hidden="true">👁️</span> Ver detalle</button></td>
            </tr>`).join('');
    } catch (e) {
        if (sec !== arHistorial.sec) return;
        arHistorial.filas = [];
        tbody.innerHTML = '<tr><td colspan="7" class="ar-celda-info">No se pudo cargar el historial.</td></tr>';
        if (errEl) { errEl.hidden = false; errEl.textContent = e.message; }
        toast(e.message, 'error');
    } finally {
        if (sec === arHistorial.sec) wrap?.setAttribute?.('aria-busy', 'false');
    }
}

function verDetalleHistorial(i) {
    const h = arHistorial.filas[i];
    if (!h) return;
    const body = document.getElementById('arh-detalle-body');
    const fila = (k, v) => `<div class="ar-dato"><span>${esc(k)}</span><span>${v}</span></div>`;
    if (body) body.innerHTML = fila('Usuario', esc(h.usuario?.nombre))
        + fila('Cédula del usuario', esc(h.usuario?.cedula))
        + fila('Acción', chipAccionHistorial(h))
        + fila('Rol anterior', rolHistorial(h.rol_anterior))
        + fila('Nuevo rol', rolHistorial(h.rol_nuevo))
        + fila('Administrador responsable', adminHistorial(h.admin))
        + (h.admin && h.admin.cedula !== null && h.admin.cedula !== undefined ? fila('Cédula del administrador', esc(h.admin.cedula)) : '')
        + fila('Fecha y hora', esc(h.fecha_texto || fechaHora(h.fecha)))
        + (h.ip ? fila('Dirección IP', esc(h.ip)) : '')
        + fila('N.º de registro', esc(h.id));
    arHistorial.origenFoco = document.activeElement || null;
    openModal('modal-historial-detalle');
    document.getElementById('arh-detalle-cerrar')?.focus?.();
}

function cerrarDetalleHistorial() {
    const modal = document.getElementById('modal-historial-detalle');
    const estabaAbierto = !!modal?.classList.contains('open');
    closeModal('modal-historial-detalle');
    const origen = arHistorial.origenFoco;
    arHistorial.origenFoco = null;
    if (estabaAbierto || origen) origen?.focus?.();
}

function teclaDetalleHistorial(e) {
    if (e && e.key === 'Escape' && document.getElementById('modal-historial-detalle')?.classList.contains('open')) {
        e.preventDefault?.();
        cerrarDetalleHistorial();
    }
}
document.addEventListener('keydown', teclaDetalleHistorial);
// Clic fuera del modal: el manejador global ya quita .open; aquí se devuelve el foco.
document.addEventListener('click', e => { if (e.target && e.target.id === 'modal-historial-detalle') cerrarDetalleHistorial(); });

// ── Inicialización Asíncrona ──
Promise.all([
    cargarKpis(),
    cargarPagosAdmin(),
    cargarTodosRoles(),
    cargarResumenAsignaciones(),
    cargarAsignaciones(),
    cargarHistorialAsignaciones(),
    cargarCategorias(),
    cargarPublicaciones(),
    cargarPostulantes(),
    cargarPostulacionesTrans(),
    cargarContratacionesTrans(),
    cargarCalificacionesTrans()
]);
// Navegación desde páginas independientes del administrador (p. ej. Dashboard de KPIs):
// /dashboard#usuarios abre ese panel y marca su opción del menú.
(function abrirPanelDesdeHash() {
    const nombre = decodeURIComponent((location.hash || '').slice(1));
    if (!/^[a-z0-9-]+$/.test(nombre) || !document.getElementById('panel-' + nombre)) return;
    showPanel(nombre);
    const enlace = document.querySelector(`.nav-item[onclick="showPanel('${nombre}')"]`);
    if (enlace) {
        enlace.classList.add('active');
        enlace.closest('.nav-group')?.classList.add('open');
    }
})();

// Exponer funciones al ámbito global (window) para los atributos onclick de Blade
window.toggleSidebar = toggleSidebar;
window.toggleGroup = toggleGroup;
window.showPanel = showPanel;
window.openModal = openModal;
window.closeModal = closeModal;
window.filterTable = filterTable;
window.filterByRol = filterByRol;
window.abrirModalUsuario = abrirModalUsuario;
window.editarUsuario = editarUsuario;
window.guardarUsuario = guardarUsuario;
window.confirmarEliminar = confirmarEliminar;
window.ejecutarEliminar = ejecutarEliminar;
window.abrirModalRol = abrirModalRol;
window.editarRol = editarRol;
window.guardarRol = guardarRol;
window.confirmarEliminarRol = confirmarEliminarRol;
window.ejecutarEliminarRol = ejecutarEliminarRol;
window.asignarRolFetch = asignarRolFetch;
window.editarRolUsuario = editarRolUsuario;
window.buscarUsuarioAsignar = buscarUsuarioAsignar;
window.teclaUsuarioAsignar = teclaUsuarioAsignar;
window.seleccionarUsuarioAsignar = seleccionarUsuarioAsignar;
window.cambiarRolAsignar = cambiarRolAsignar;
window.buscarTablaAsignaciones = buscarTablaAsignaciones;
window.limpiarBusquedaAsignaciones = limpiarBusquedaAsignaciones;
window.filtrarAsignaciones = filtrarAsignaciones;
window.paginaAsignaciones = paginaAsignaciones;
window.verRolFila = verRolFila;
window.cambiarRolFila = cambiarRolFila;
window.confirmarQuitarRol = confirmarQuitarRol;
window.ejecutarQuitarRol = ejecutarQuitarRol;
window.verUsuariosRol = verUsuariosRol;
window.buscarHistorialAsignaciones = buscarHistorialAsignaciones;
window.limpiarBusquedaHistorial = limpiarBusquedaHistorial;
window.filtrarHistorial = filtrarHistorial;
window.limpiarFiltrosHistorial = limpiarFiltrosHistorial;
window.paginaHistorial = paginaHistorial;
window.verDetalleHistorial = verDetalleHistorial;
window.cerrarDetalleHistorial = cerrarDetalleHistorial;
window.teclaDetalleHistorial = teclaDetalleHistorial;
window.editarCategoria = editarCategoria;
window.guardarCategoria = guardarCategoria;
window.editarPublicacion = editarPublicacion;
window.guardarPublicacion = guardarPublicacion;
window.abrirModalPostulante = abrirModalPostulante;
window.editarPostulante = editarPostulante;
window.guardarPostulante = guardarPostulante;
window.confirmarEliminarItem = confirmarEliminarItem;
window.ejecutarEliminarItem = ejecutarEliminarItem;window.filtrarPagos = filtrarPagos;
window.paginaPagos = paginaPagos;
window.verPago = verPago;
window.confirmarPago = confirmarPago;
window.ejecutarConfirmarPago = ejecutarConfirmarPago;
window.abrirAnularPago = abrirAnularPago;
window.ejecutarAnularPago = ejecutarAnularPago;
