const TOKEN = document.querySelector('meta[name="api-token"]')?.content;
const ME = String(document.querySelector('meta[name="user-cedula"]')?.content ?? '');

let contrataciones = [];

/* ── Utilidades ── */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const bs = n => 'Bs ' + Number(n || 0).toLocaleString('es-BO', { maximumFractionDigits: 2 });
const fecha = s => {
    if (!s) return '—';
    const [y, m, d] = String(s).slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-BO');
};

async function api(url, method = 'GET', body = null) {
    const res = await fetch(url, {
        method,
        headers: Object.assign({ 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }, body ? { 'Content-Type': 'application/json' } : {}),
        body: body ? JSON.stringify(body) : null
    });
    let data = null; try { data = await res.json(); } catch (e) {}
    if (!res.ok) throw new Error((data && data.errors) ? Object.values(data.errors)[0][0] : (data && data.message) || 'Error de conexión.');
    return data;
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main').classList.toggle('expanded');
}

function showPanel(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.toggle('active', n.dataset.panel === name));
    document.getElementById('panel-' + name)?.classList.add('active');
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function toast(msg, tipo = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:2rem;right:2rem;z-index:9999;padding:.9rem 1.4rem;border-radius:12px;font-size:.88rem;font-weight:600;backdrop-filter:blur(12px);${tipo === 'success' ? 'background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.3);color:#4ADE80;' : 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#ff7070;'}`;
    t.textContent = (tipo === 'success' ? '✅ ' : '❌ ') + msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ── 1. Categorías para el selector de publicación ──
async function cargarCategoriasSelect() {
    try {
        const data = await api('/api/categorias');
        const sel = document.getElementById('pub-categoria');
        if (sel && Array.isArray(data)) {
            sel.innerHTML = '<option value="">— Seleccione Categoría —</option>';
            data.forEach(c => sel.innerHTML += `<option value="${c.id_categoria}">${esc(c.nombre_categoria)}</option>`);
        }
    } catch (e) { console.error('Error al cargar categorías:', e); }
}

// ── 2. Nueva publicación ──
async function guardarNuevaPublicacion() {
    const body = {
        titulo:       document.getElementById('pub-titulo').value,
        tipo:         document.getElementById('pub-tipo').value,
        descripcion:  document.getElementById('pub-descripcion').value,
        pago:         document.getElementById('pub-pago').value,
        lugar:        document.getElementById('pub-lugar').value,
        id_categoria: document.getElementById('pub-categoria').value
    };
    try {
        await api('/api/contratador/publicaciones', 'POST', body);
        toast('Publicación creada correctamente.');
        closeModal('modal-nueva-publicacion');
        setTimeout(() => location.reload(), 1200);
    } catch (e) { toast(e.message, 'error'); }
}

// ── 3. Postulaciones recibidas ──
async function cargarPostulacionesRecibidas() {
    const tbody = document.getElementById('tbody-postulaciones-recibidas');
    if (!tbody) return;
    try {
        const data = await api('/api/contratador/postulaciones');
        const pend = (data || []).filter(p => p.estado === 'pendiente').length;
        const stat = document.getElementById('stat-pendientes');
        if (stat) stat.textContent = pend;

        if (!data || !data.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No has recibido postulaciones aún.</td></tr>';
            return;
        }

        tbody.innerHTML = data.map(p => {
            const estado = p.estado ?? 'pendiente';
            const chip = estado === 'aceptada' ? 'chip-green' : estado === 'rechazada' ? 'chip-red' : 'chip-yellow';
            const t = p.trabajador || {};
            const contacto = [t.telefono, t.email].filter(Boolean).map(esc).join(' · ');
            const botones = estado === 'pendiente'
                ? `<button class="btn btn-success" onclick="cambiarEstadoPostulacion(${p.id_postulacion}, 'aceptada')">Aceptar</button>
                   <button class="btn btn-danger" onclick="cambiarEstadoPostulacion(${p.id_postulacion}, 'rechazada')">Rechazar</button>`
                : '—';
            return `<tr>
                <td class="name">${esc(t.nombre ?? 'Trabajador')}<div style="font-weight:400;font-size:.72rem;color:var(--text-muted);">${contacto}</div></td>
                <td>${esc(p.publicacion?.titulo ?? 'Oferta Laboral')}<div style="font-size:.72rem;color:var(--text-muted);">${fecha(p.fecha_postulacion)}</div></td>
                <td style="max-width:240px;">${esc(p.mensaje || '—')}</td>
                <td><span class="chip ${chip}">${esc(estado).toUpperCase()}</span></td>
                <td>${botones}</td>
            </tr>`;
        }).join('');
    } catch (e) { console.error('Error postulaciones:', e); }
}

// ── 4. Aceptar / rechazar (al aceptar, el servidor crea la contratación) ──
async function cambiarEstadoPostulacion(idPostulacion, estado) {
    try {
        await api(`/api/contratador/postulaciones/${idPostulacion}/estado`, 'PUT', { estado });
        toast(estado === 'aceptada' ? 'Postulación aceptada. Contratación creada.' : 'Postulación rechazada.');
        cargarPostulacionesRecibidas();
        cargarContrataciones();
    } catch (e) { toast(e.message, 'error'); }
}

// ── 5. Contrataciones ──
async function cargarContrataciones() {
    const tbody = document.getElementById('tbody-contrataciones');
    if (!tbody) return;
    try {
        contrataciones = await api('/api/contratador/contrataciones');
        const activas = contrataciones.filter(c => c.estado === 'activa').length;
        const stat = document.getElementById('stat-contrataciones');
        if (stat) stat.textContent = activas;

        if (!contrataciones.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">Aún no tienes contrataciones. Se crean al aceptar una postulación.</td></tr>';
            return;
        }

        tbody.innerHTML = contrataciones.map(c => {
            const t = c.trabajador || {};
            const chip = c.estado === 'finalizada' ? 'chip-blue' : c.estado === 'cancelada' ? 'chip-red' : 'chip-green';
            const contacto = [t.telefono, t.email].filter(Boolean).map(esc).join(' · ');
            const mia = (c.calificaciones || []).find(x => String(x.cedula_calificador) === ME);

            let acciones = '—';
            if (c.estado === 'activa') {
                acciones = `<div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                    <button class="btn btn-success" onclick="cambiarEstadoContratacion(${c.id_contratacion}, 'finalizada')">✔ Finalizar</button>
                    <button class="btn btn-danger" onclick="cambiarEstadoContratacion(${c.id_contratacion}, 'cancelada')">✖ Cancelar</button>
                </div>`;
            } else if (c.estado === 'finalizada') {
                acciones = mia
                    ? `<span style="color:var(--gold);font-weight:600;">⭐ ${mia.puntuacion}/5 calificado</span>`
                    : `<button class="btn btn-gold" onclick="abrirModalCalificar(${c.id_contratacion})">⭐ Calificar</button>`;
            }

            return `<tr>
                <td class="name">${esc(t.nombre ?? '—')}<div style="font-weight:400;font-size:.72rem;color:var(--text-muted);">${contacto}</div></td>
                <td>${esc(c.publicacion?.titulo ?? '—')}</td>
                <td style="color:var(--accent);font-weight:600;">${bs(c.monto_acordado)}</td>
                <td>${fecha(c.fecha_inicio)}</td>
                <td>${fecha(c.fecha_fin)}</td>
                <td><span class="chip ${chip}">${esc(c.estado).toUpperCase()}</span></td>
                <td>${acciones}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        console.error('Error contrataciones:', e);
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#ff7070;">${esc(e.message)}</td></tr>`;
    }
}

async function cambiarEstadoContratacion(id, estado) {
    const txt = estado === 'finalizada' ? 'finalizar' : 'cancelar';
    if (!confirm(`¿Seguro que deseas ${txt} esta contratación?`)) return;
    try {
        await api(`/api/contratador/contrataciones/${id}/estado`, 'PUT', { estado });
        toast(estado === 'finalizada' ? 'Contratación finalizada. Ya puedes calificar.' : 'Contratación cancelada.');
        cargarContrataciones();
    } catch (e) { toast(e.message, 'error'); }
}

// ── 6. Calificación ──
function abrirModalCalificar(idContratacion) {
    const c = contrataciones.find(x => x.id_contratacion == idContratacion);
    document.getElementById('calif-contratacion').value = idContratacion;
    document.getElementById('calif-nombre-trabajador').textContent = c?.trabajador?.nombre ?? 'el trabajador';
    document.getElementById('calif-puntuacion').value = '5';
    document.getElementById('calif-comentario').value = '';
    openModal('modal-calificar');
}

async function guardarCalificacion() {
    const body = {
        id_contratacion: Number(document.getElementById('calif-contratacion').value),
        puntuacion:      Number(document.getElementById('calif-puntuacion').value),
        comentario:      document.getElementById('calif-comentario').value.trim() || null
    };
    try {
        await api('/api/contratador/calificaciones', 'POST', body);
        toast('Calificación registrada.');
        closeModal('modal-calificar');
        cargarContrataciones();
    } catch (e) { toast(e.message, 'error'); }
}

// ── 7. Editar / eliminar publicación ──
function editarPublicacion(pub) {
    document.getElementById('edit-pub-id').value = pub.id_labor;
    document.getElementById('edit-pub-titulo').value = pub.titulo;
    document.getElementById('edit-pub-tipo').value = pub.tipo;
    document.getElementById('edit-pub-descripcion').value = pub.descripcion;
    document.getElementById('edit-pub-pago').value = pub.pago;
    document.getElementById('edit-pub-lugar').value = pub.lugar;

    const selOrigen = document.getElementById('pub-categoria');
    const selDestino = document.getElementById('edit-pub-categoria');
    if (selOrigen && selDestino) {
        selDestino.innerHTML = selOrigen.innerHTML;
        selDestino.value = pub.id_categoria;
    }
    openModal('modal-editar-publicacion');
}

async function guardarEdicionPublicacion() {
    const id = document.getElementById('edit-pub-id').value;
    const pagoRaw = document.getElementById('edit-pub-pago').value;
    const body = {
        titulo:       document.getElementById('edit-pub-titulo').value,
        tipo:         document.getElementById('edit-pub-tipo').value,
        descripcion:  document.getElementById('edit-pub-descripcion').value,
        pago:         pagoRaw ? pagoRaw.toString().replace(',', '.') : 0,
        lugar:        document.getElementById('edit-pub-lugar').value,
        id_categoria: document.getElementById('edit-pub-categoria').value
    };
    try {
        await api(`/api/contratador/publicaciones/${id}`, 'PUT', body);
        toast('Publicación actualizada correctamente.');
        closeModal('modal-editar-publicacion');
        setTimeout(() => location.reload(), 1000);
    } catch (e) { toast(e.message, 'error'); }
}

async function eliminarPublicacion(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar esta oferta laboral?')) return;
    try {
        await api(`/api/contratador/publicaciones/${id}`, 'DELETE');
        toast('Publicación eliminada con éxito.');
        setTimeout(() => location.reload(), 1000);
    } catch (e) { toast('No se pudo eliminar la publicación.', 'error'); }
}

// ── 8. Perfil (el modal ya llamaba a guardarPerfil, que no existía) ──
async function guardarPerfil() {
    const v = id => document.getElementById(id).value.trim();
    try {
        await api('/api/perfil', 'PUT', {
            nombre:    v('edit-perfil-nombre'),
            email:     v('edit-perfil-email'),
            telefono:  v('edit-perfil-telefono') || null,
            ciudad:    v('edit-perfil-ciudad') || null,
            direccion: v('edit-perfil-direccion') || null
        });
        toast('Perfil actualizado.');
        closeModal('modal-editar-perfil');
        setTimeout(() => location.reload(), 900);
    } catch (e) { toast(e.message, 'error'); }
}

// Funciones globales para los onclick (Vite las encapsula en un módulo)
Object.assign(window, {
    toggleSidebar, showPanel, openModal, closeModal,
    guardarNuevaPublicacion, cambiarEstadoPostulacion,
    cambiarEstadoContratacion, abrirModalCalificar, guardarCalificacion,
    editarPublicacion, guardarEdicionPublicacion, eliminarPublicacion, guardarPerfil
});

// Cargas iniciales
cargarCategoriasSelect();
cargarPostulacionesRecibidas();
cargarContrataciones();