/* Dashboard del trabajador (resources/views/dashboard.blade.php). Trasladado sin cambios desde el <script> incrustado.
 * Los datos del usuario llegan en <script type="application/json" id="trabajador-me"> (antes se incrustaban con Blade dentro del script). */
const TOKEN = document.querySelector('meta[name="api-token"]').content;
const ME = JSON.parse(document.getElementById('trabajador-me')?.textContent || '{}');
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

/* ── Mis pagos (solo lectura; registros SIMULADOS calculados por la API) ── */
const METODOS_PAGO = { efectivo: 'Efectivo', transferencia: 'Transferencia', qr: 'QR', deposito: 'Depósito', otro: 'Otro' };
const chipPago = e => e === 'confirmado' ? 'chip-green' : e === 'anulado' ? 'chip-red' : 'chip-yellow';
async function cargarPagos() {
    try {
        const r = await api('/api/trabajador/pagos');
        const pagos = r.data || [], resumen = r.resumen || [];
        if (r.aviso) $('pagos-aviso').textContent = 'SIMULADO — ' + r.aviso;
        $('tbody-pagos-resumen').innerHTML = resumen.length ? resumen.map(x => `<tr>
                <td class="name">#${esc(x.id_contratacion)}</td>
                <td><span class="chip ${chipContr(x.estado_contratacion)}">${esc(x.estado_contratacion).toUpperCase()}</span></td>
                <td>${esc(bs(x.monto_acordado))}</td>
                <td>${esc(bs(x.pagado_confirmado))}</td>
                <td>${esc(bs(x.pagado_pendiente))}</td>
                <td>${esc(bs(x.saldo_disponible))}</td>
            </tr>`).join('')
            : '<tr><td colspan="6" class="muted" style="text-align:center">Aún no tienes contrataciones con pagos.</td></tr>';
        $('tbody-pagos').innerHTML = pagos.length ? pagos.map(p => `<tr>
                <td class="name">${esc((p.publicacion && p.publicacion.titulo) || '—')}</td>
                <td>${esc((p.contratador && p.contratador.nombre) || '—')}</td>
                <td>${esc(fecha(p.fecha_pago))}</td>
                <td>${esc(METODOS_PAGO[p.metodo] || p.metodo)}</td>
                <td>${esc(bs(p.monto))}</td>
                <td><span class="chip ${chipPago(p.estado)}">${esc(p.estado).toUpperCase()}</span> <span class="chip chip-yellow">SIM</span></td>
            </tr>`).join('')
            : '<tr><td colspan="6" class="muted" style="text-align:center">Aún no tienes pagos registrados.</td></tr>';
    } catch (e) {
        const msg = `<tr><td colspan="6" style="text-align:center;color:var(--primary)">${esc(e.message)}</td></tr>`;
        $('tbody-pagos-resumen').innerHTML = msg; $('tbody-pagos').innerHTML = msg;
    }
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
cargarPagos();
