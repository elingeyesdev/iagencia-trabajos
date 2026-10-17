const TOKEN = document.querySelector('meta[name="api-token"]').content;

// ── Sidebar & UI ──
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
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
function verUsuario(id) {
    const rows = document.querySelectorAll('#tabla-usuarios tbody tr');
    let found = null;
    rows.forEach(row => { if (row.cells[0].textContent == id) found = row; });
    if (found) {
        document.getElementById('detalle-usuario-content').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:.8rem;">
                <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">ID</div><div style="color:white;font-weight:600;">${found.cells[0].textContent}</div></div>
                <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Nombre</div><div style="color:white;font-weight:600;">${found.cells[1].textContent}</div></div>
                <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Email</div><div style="color:white;">${found.cells[2].textContent}</div></div>
                <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Cédula</div><div style="color:white;">${found.cells[3].textContent}</div></div>
                <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Ciudad</div><div style="color:white;">${found.cells[4].textContent}</div></div>
                <div><div style="color:var(--text-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Rol</div><div>${found.cells[5].innerHTML}</div></div>
            </div>`;
    }
    openModal('modal-ver-usuario');
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

async function asignarRolFetch(e) {
    e.preventDefault();
    const selects = e.target.querySelectorAll('select');
    const res = await fetch('/api/admin/asignar-rol', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ usuario_id: selects[0].value, rol: selects[1].value })
    });
    const data = await res.json();
    if (res.ok) { toast(data.message); setTimeout(() => location.reload(), 1500); }
    else { toast(data.message || 'Error.', 'error'); }
}

function editarRolUsuario(id, nombre) { showPanel('asignar-rol'); }
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
                        <button class="icon-btn" onclick="editarCategoria(${c.id_categoria},'${c.nombre_categoria?.replace(/'/g,"\\'")}','${c.descripcion?.replace(/'/g,"\\'") ?? ''}',${c.estado})">✏️</button>
                        <button class="icon-btn del" onclick="confirmarEliminarItem(${c.id_categoria},'${c.nombre_categoria}','categoria')">🗑️</button>
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
                        <button class="icon-btn" onclick="editarPostulante(${JSON.stringify(p).replace(/"/g,'&quot;')})">✏️</button>
                        <button class="icon-btn del" onclick="confirmarEliminarItem(${p.id_postulante},'${p.usuario?.nombre ?? 'postulante'}','postulante')">🗑️</button>
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
                        <button class="icon-btn del" title="Eliminar" onclick="confirmarEliminarItem(${p.id_postulacion || p.id},'Postulación de ${p.trabajador?.nombre ?? 'usuario'}','postulacion')">🗑️</button>
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
                        <button class="icon-btn del" title="Eliminar" onclick="confirmarEliminarItem(${c.id_contratacion || c.id},'Contratación #${c.id_contratacion || c.id}','contratacion')">🗑️</button>
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
                        <button class="icon-btn del" title="Eliminar" onclick="confirmarEliminarItem(${cal.id_calificacion || cal.id},'Calificación #${cal.id_calificacion || cal.id}','calificacion')">🗑️</button>
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

        const selects = document.querySelectorAll('#panel-asignar-rol select');
        if (selects[1]) {
            selects[1].innerHTML = '<option value="">— Seleccione rol —</option>';
            roles.forEach(r => {
                selects[1].innerHTML += `<option value="${r.name}">${r.name.charAt(0).toUpperCase() + r.name.slice(1)}</option>`;
            });
        }
    } catch(e) { console.log('Error cargando roles:', e); }
}

// ── Inicialización Asíncrona ──
Promise.all([
    cargarTodosRoles(),
    cargarCategorias(),
    cargarPublicaciones(),
    cargarPostulantes(),
    cargarPostulacionesTrans(),
    cargarContratacionesTrans(),
    cargarCalificacionesTrans()
]);
// Exponer funciones al ámbito global (window) para los atributos onclick de Blade
window.toggleSidebar = toggleSidebar;
window.toggleGroup = toggleGroup;
window.showPanel = showPanel;
window.openModal = openModal;
window.closeModal = closeModal;
window.filterTable = filterTable;
window.filterByRol = filterByRol;
window.verUsuario = verUsuario;
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
window.verUsuariosRol = verUsuariosRol;
window.editarCategoria = editarCategoria;
window.guardarCategoria = guardarCategoria;
window.editarPublicacion = editarPublicacion;
window.guardarPublicacion = guardarPublicacion;
window.abrirModalPostulante = abrirModalPostulante;
window.editarPostulante = editarPostulante;
window.guardarPostulante = guardarPostulante;
window.confirmarEliminarItem = confirmarEliminarItem;
window.ejecutarEliminarItem = ejecutarEliminarItem;