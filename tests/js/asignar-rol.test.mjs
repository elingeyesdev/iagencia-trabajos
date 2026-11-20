/**
 * Módulo «Asignar Rol» — ejecuta la lógica REAL de resources/js/admin-dashboard.js en node:vm:
 * botón «Guardar +», debounce del buscador de usuario, escapado, tarjeta del usuario seleccionado,
 * capacidades del rol, confirmación antes de quitar, toasts de error y paginación de la tabla.
 * Ejecución: node --test "tests/js/*.test.mjs"
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { leer, neutralizarModulo, crearEntorno, idsDeVista, FakeElement } from './helpers/entorno-vm.mjs';

const SOURCE = neutralizarModulo(leer('resources/js/admin-dashboard.js'));
const VIEW = leer('resources/views/admin/dashboard.blade.php');
const PANEL = VIEW.slice(VIEW.indexOf('<!-- PANEL: ASIGNAR ROL -->'), VIEW.indexOf('<!-- PANEL: CATEGORÍAS -->'));

const ROLES = [
    { id: 1, name: 'trabajador', users_count: 4 },
    { id: 2, name: 'contratador', users_count: 3 },
    { id: 3, name: 'administrador', users_count: 2 },
];
const FILAS = [
    { cedula: 101, nombre: 'Ana Pérez', email: 'ana@redwork.test', estado: 1, roles: ['trabajador'], fecha_asignacion: null },
    { cedula: 102, nombre: 'Beto Admin', email: 'beto@redwork.test', estado: 1, roles: ['administrador'], fecha_asignacion: null },
];
const LISTADO = { data: FILAS, meta: { current_page: 1, last_page: 3, per_page: 10, total: 25, from: 1, to: 10 }, fecha_asignacion_disponible: false };
const CAPACIDADES = {
    rol: { id: 3, name: 'administrador', users_count: 2 }, descripcion: null, permisos_spatie: [],
    capacidades: [{ modulo: 'admin/pagos', etiqueta: 'Pagos simulados', icono: '💳', rutas: 5, metodos: ['GET', 'PUT'] }],
    nota_limitacion: 'RedWork autoriza por rol, no por permisos individuales.',
};

function entorno({ listado = { status: 200, data: LISTADO }, capacidades = { status: 200, data: CAPACIDADES }, escritura = { status: 200, data: { message: 'OK servidor.' } }, historial = undefined } = {}) {
    const elementos = new Map();
    for (const id of ['ar-rol-select', 'ar-filtro-rol', 'ar-filtro-estado', 'ar-filtro-conrol', 'arh-accion']) elementos.set(id, new FakeElement(id, { tag: 'select' }));
    for (const id of ['ar-usuario-buscar', 'ar-tabla-buscar', 'ar-usuario-id', 'arh-buscar', 'arh-desde', 'arh-hasta']) elementos.set(id, new FakeElement(id, { tag: 'input' }));
    const focos = [];
    for (const id of ['arh-detalle-cerrar']) {
        const el = new FakeElement(id, { tag: 'button' });
        el.focus = () => focos.push(id);
        elementos.set(id, el);
    }
    const env = crearEntorno({
        source: SOURCE,
        filename: 'admin-dashboard.js',
        metas: { 'api-token': 'TOKEN-PRUEBA' },
        elementos,
        rutas: c => {
            if (c.url === '/api/admin/roles') return { status: 200, data: ROLES };
            if (c.url === '/api/admin/asignaciones/resumen') return { status: 200, data: { total_usuarios: 30, con_rol: 25, sin_rol: 5, roles_disponibles: 3 } };
            if (c.method === 'GET' && c.url.startsWith('/api/admin/asignaciones/historial?')) return typeof historial === 'function' ? historial(c) : historial;
            if (c.method === 'GET' && c.url.startsWith('/api/admin/asignaciones?')) return listado;
            if (c.method === 'GET' && /^\/api\/admin\/roles\/\d+\/capacidades$/.test(c.url)) return capacidades;
            if (c.method === 'POST') return escritura;
            return undefined;
        },
    });
    env.focos = focos;
    return env;
}

const getsListado = env => env.fetchCalls.filter(c => c.method === 'GET' && c.url.startsWith('/api/admin/asignaciones?')).map(c => c.url);
const correrDebounce = env => env.timers.filter(t => t.ms === 300).splice(0).forEach(t => t.fn());

test('vista: botón submit con texto exacto «Guardar +», sin @foreach de usuarios y con las secciones nuevas', () => {
    assert.match(PANEL, /<form id="ar-form" onsubmit="asignarRolFetch\(event\)"/);
    assert.match(PANEL, /<button type="submit" class="btn btn-accent ar-guardar" id="ar-guardar" disabled>Guardar \+<\/button>/);
    assert.doesNotMatch(PANEL, /@foreach/, 'no se deben cargar todos los usuarios en el Blade');
    assert.match(PANEL, /Gestiona los roles y permisos de los usuarios de RedWork\./);
    for (const t of ['Información del rol', 'Permisos del rol', 'Usuarios y roles asignados', 'Historial de Asignaciones', 'Fecha de asignación']) {
        assert.ok(PANEL.includes(t), `falta «${t}»`);
    }
    // El estado vacío provisional («aún no registra el historial») se sustituye por el historial real.
    assert.match(PANEL, /Consulta los cambios de roles realizados por los administradores de RedWork\./);
    assert.doesNotMatch(PANEL, /Requiere autorización para crear la tabla de historial/);
    const ids = idsDeVista(VIEW);
    for (const id of ['panel-asignar-rol', 'ar-usuario-buscar', 'ar-usuario-lista', 'ar-usuario-info', 'ar-rol-select', 'ar-rol-info', 'ar-rol-permisos',
        'tbody-asignaciones', 'ar-prev', 'ar-next', 'modal-quitar-rol', 'quitar-rol-error', 'ar-resumen-total', 'ar-resumen-sin']) {
        assert.ok(ids.has(id), `falta #${id}`);
    }
});

test('carga: resumen, roles por id (formulario y filtro) y tabla, sin escrituras ni errores', async () => {
    const env = entorno();
    await env.asentar();
    assert.deepEqual(env.logs, []);
    assert.deepEqual(env.escrituras(), []);
    assert.equal(env.el('ar-resumen-total').textContent, (30).toLocaleString('es-BO'));
    assert.equal(env.el('ar-resumen-sin').textContent, '5');
    assert.match(env.el('ar-rol-select').innerHTML, /— Seleccione rol —.*value="administrador"/s);
    assert.match(env.el('ar-filtro-rol').innerHTML, /Todos los roles.*value="contratador"/s);
    const tabla = env.el('tbody-asignaciones').innerHTML;
    assert.match(tabla, /Ana Pérez/);
    assert.match(tabla, /No disponible/);
    assert.match(tabla, /title="Ver rol"/);
    assert.match(tabla, /title="Cambiar rol"/);
    assert.match(tabla, /title="Quitar rol"/);
});

test('buscador: debounce de 300 ms — teclear rápido produce UNA sola petición con el último texto', async () => {
    const env = entorno();
    await env.asentar();
    const antes = getsListado(env).length;
    env.win.buscarUsuarioAsignar('a');
    env.win.buscarUsuarioAsignar('an');
    env.win.buscarUsuarioAsignar('ana');
    assert.equal(getsListado(env).length, antes, 'no debe consultar antes de que venza el debounce');
    correrDebounce(env);
    await env.asentar();
    const nuevas = getsListado(env).slice(antes);
    assert.deepEqual(nuevas, ['/api/admin/asignaciones?buscar=ana&per_page=8']);
    assert.match(env.el('ar-usuario-lista').innerHTML, /role="option"/);
});

test('escapado: nombres con <script> se muestran como texto en la lista, la tarjeta y la tabla', async () => {
    const malo = { cedula: 7, nombre: '<script>alert(1)</script>', email: '"x"@<b>.test', estado: 1, roles: ['<img src=x>'] };
    const env = entorno({ listado: { status: 200, data: { data: [malo], meta: { current_page: 1, last_page: 1, total: 1, from: 1, to: 1 } } } });
    await env.asentar();
    env.win.buscarUsuarioAsignar('x');
    correrDebounce(env);
    await env.asentar();
    env.win.seleccionarUsuarioAsignar(0);
    for (const id of ['tbody-asignaciones', 'ar-usuario-info']) {
        const html = env.el(id).innerHTML;
        assert.doesNotMatch(html, /<script>|<img src=x>|<b>/, `${id} sin escapar`);
        assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    }
});

test('selección: rellena la tarjeta, habilita «Guardar +» solo con usuario y rol y avisa si el rol ya es el actual', async () => {
    const env = entorno();
    await env.asentar();
    env.win.buscarUsuarioAsignar('ana');
    correrDebounce(env);
    await env.asentar();
    assert.notEqual(env.el('ar-guardar')?.disabled, false, 'deshabilitado sin usuario (atributo disabled del Blade)');
    env.win.seleccionarUsuarioAsignar(0);
    const info = env.el('ar-usuario-info').innerHTML;
    for (const t of ['Ana Pérez', 'ana@redwork.test', '101', 'Activo', 'Trabajador']) assert.ok(info.includes(t), `la tarjeta no muestra ${t}`);
    assert.equal(env.el('ar-usuario-id').value, '101');
    assert.equal(env.el('ar-usuario-lista').hidden, true, 'la lista se cierra al elegir');
    assert.equal(env.el('ar-guardar').disabled, true, 'deshabilitado sin rol');

    env.el('ar-rol-select').value = 'trabajador';
    env.win.cambiarRolAsignar('trabajador');
    assert.equal(env.el('ar-aviso').hidden, false);
    assert.match(env.el('ar-aviso').textContent, /ya tiene el rol Trabajador/);
    assert.equal(env.el('ar-guardar').disabled, true);

    env.el('ar-rol-select').value = 'contratador';
    env.win.cambiarRolAsignar('contratador');
    assert.equal(env.el('ar-aviso').hidden, true);
    assert.equal(env.el('ar-guardar').disabled, false);
});

test('selección sin rol: la tarjeta muestra «Sin rol asignado»', async () => {
    const env = entorno({ listado: { status: 200, data: { data: [{ cedula: 9, nombre: 'Nadie', email: 'n@r.test', estado: 0, roles: [] }], meta: {} } } });
    await env.asentar();
    env.win.buscarUsuarioAsignar('nad');
    correrDebounce(env);
    await env.asentar();
    env.win.seleccionarUsuarioAsignar(0);
    assert.match(env.el('ar-usuario-info').innerHTML, /Sin rol asignado/);
    assert.match(env.el('ar-usuario-info').innerHTML, /Inactivo/);
});

test('teclado: flechas mueven la opción activa y Enter selecciona', async () => {
    const env = entorno();
    await env.asentar();
    env.win.buscarUsuarioAsignar('a');
    correrDebounce(env);
    await env.asentar();
    const ev = key => ({ key, preventDefault() {} });
    env.win.teclaUsuarioAsignar(ev('ArrowDown'));
    assert.match(env.el('ar-usuario-lista').innerHTML, /id="ar-opt-1" class="ar-opcion activa"/);
    env.win.teclaUsuarioAsignar(ev('Enter'));
    assert.equal(env.el('ar-usuario-id').value, '102');
});

test('cambio de rol: carga info y capacidades del endpoint del rol (por id)', async () => {
    const env = entorno();
    await env.asentar();
    await env.win.cambiarRolAsignar('administrador');
    assert.ok(env.fetchCalls.some(c => c.method === 'GET' && c.url === '/api/admin/roles/3/capacidades'));
    const info = env.el('ar-rol-info').innerHTML;
    assert.match(info, /Administrador/);
    assert.match(info, /Sin descripción registrada/);
    const permisos = env.el('ar-rol-permisos').innerHTML;
    assert.match(permisos, /Pagos simulados/);
    assert.match(permisos, /💳/);
    assert.match(permisos, /autoriza por rol/);
});

test('cambio de rol: sin capacidades muestra un estado vacío claro', async () => {
    const env = entorno({ capacidades: { status: 200, data: { ...CAPACIDADES, capacidades: [] } } });
    await env.asentar();
    await env.win.cambiarRolAsignar('trabajador');
    assert.match(env.el('ar-rol-permisos').innerHTML, /no tiene rutas de la API asociadas/);
});

test('asignar: POST con usuario y rol, toast de éxito y refresco sin recargar la página', async () => {
    const env = entorno({ escritura: { status: 200, data: { message: "Rol 'contratador' asignado a Ana Pérez." } } });
    await env.asentar();
    env.win.buscarUsuarioAsignar('ana');
    correrDebounce(env);
    await env.asentar();
    env.win.seleccionarUsuarioAsignar(0);
    env.el('ar-rol-select').value = 'contratador';
    const listadosAntes = getsListado(env).length;
    let prevenido = false;
    await env.win.asignarRolFetch({ preventDefault() { prevenido = true; } });
    await env.asentar();
    assert.ok(prevenido);
    const post = env.escrituras();
    assert.equal(post.length, 1);
    assert.equal(post[0].url, '/api/admin/asignar-rol');
    assert.deepEqual(post[0].body, { usuario_id: '101', rol: 'contratador' });
    assert.match(env.ultimoToast(), /asignado a Ana Pérez/);
    assert.deepEqual(env.reloads, [], 'no debe recargar la página');
    assert.ok(getsListado(env).length > listadosAntes, 'refresca la tabla');
    assert.match(env.el('ar-usuario-info').innerHTML, /Contratador/, 'la tarjeta refleja el rol nuevo');
});

test('asignar: un 422 del backend se muestra en un toast de error', async () => {
    const msg = 'No se puede quitar el rol administrador a Beto Admin: es el último administrador activo de RedWork.';
    const env = entorno({ escritura: { status: 422, data: { message: msg, errors: { usuario_id: [msg] } } } });
    await env.asentar();
    await env.win.editarRolUsuario(102, 'Beto Admin');
    env.el('ar-rol-select').value = 'trabajador';
    await env.win.asignarRolFetch({ preventDefault() {} });
    assert.match(env.ultimoToast(), /❌ .*último administrador activo/);
    assert.deepEqual(env.reloads, []);
});

test('editarRolUsuario: precarga el usuario en el formulario', async () => {
    const env = entorno();
    await env.asentar();
    await env.win.editarRolUsuario(102, 'Beto Admin');
    assert.equal(env.el('ar-usuario-id').value, '102');
    assert.equal(env.el('ar-usuario-buscar').value, 'Beto Admin');
    assert.match(env.el('ar-usuario-info').innerHTML, /beto@redwork\.test/);
});

test('quitar rol: pide confirmación antes del POST y muestra el error del backend en el modal', async () => {
    const msg = 'No se puede quitar el rol administrador a Beto Admin: es el último administrador activo de RedWork.';
    const env = entorno({ escritura: { status: 422, data: { message: msg, errors: { usuario_id: [msg] } } } });
    await env.asentar();
    env.win.confirmarQuitarRol(1);
    assert.deepEqual(env.escrituras(), [], 'abrir la confirmación no escribe');
    assert.ok(env.el('modal-quitar-rol').classList.contains('open'));
    assert.equal(env.el('quitar-rol-nombre').textContent, 'Beto Admin');
    await env.win.ejecutarQuitarRol();
    const post = env.escrituras();
    assert.equal(post.length, 1);
    assert.equal(post[0].url, '/api/admin/quitar-rol');
    assert.deepEqual(post[0].body, { usuario_id: 102 });
    assert.equal(env.el('quitar-rol-error').textContent, msg);
    assert.ok(env.el('modal-quitar-rol').classList.contains('open'), 'el modal sigue abierto ante el error');
    assert.match(env.ultimoToast(), /❌/);
});

test('quitar rol: éxito cierra el modal, avisa y refresca', async () => {
    const env = entorno({ escritura: { status: 200, data: { message: 'Rol removido de Ana Pérez.' } } });
    await env.asentar();
    env.win.confirmarQuitarRol(0);
    await env.win.ejecutarQuitarRol();
    await env.asentar();
    assert.equal(env.el('modal-quitar-rol').classList.contains('open'), false);
    assert.match(env.ultimoToast(), /✅ Rol removido de Ana Pérez\./);
    assert.deepEqual(env.reloads, []);
});

test('paginación y filtros: la URL conserva los filtros, respeta límites y filtrar vuelve a la página 1', async () => {
    const env = entorno();
    await env.asentar();
    assert.equal(env.el('ar-prev').disabled, true);
    assert.equal(env.el('ar-next').disabled, false);
    assert.match(env.el('ar-paginacion-info').textContent, /Mostrando 1–10 de 25 · Página 1 de 3/);

    env.el('ar-filtro-rol').value = 'trabajador';
    env.el('ar-filtro-estado').value = '1';
    env.el('ar-filtro-conrol').value = 'si';
    const n = getsListado(env).length;
    await env.win.paginaAsignaciones(-1);
    assert.equal(getsListado(env).length, n, 'no retrocede antes de la página 1');
    await env.win.paginaAsignaciones(1);
    assert.equal(getsListado(env).at(-1), '/api/admin/asignaciones?rol=trabajador&estado=1&con_rol=si&page=2&per_page=10');
    await env.win.filtrarAsignaciones();
    assert.match(getsListado(env).at(-1), /&page=1&/);
});

test('tabla: buscador con debounce, botón limpiar y mensaje sin resultados', async () => {
    const env = entorno({ listado: { status: 200, data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } } });
    await env.asentar();
    assert.match(env.el('tbody-asignaciones').innerHTML, /No se encontraron usuarios/);
    env.el('ar-tabla-buscar').value = 'zz';
    env.win.buscarTablaAsignaciones('z');
    env.win.buscarTablaAsignaciones('zz');
    assert.equal(env.el('ar-tabla-limpiar').hidden, false);
    const n = getsListado(env).length;
    correrDebounce(env);
    await env.asentar();
    assert.deepEqual(getsListado(env).slice(n), ['/api/admin/asignaciones?buscar=zz&page=1&per_page=10']);
    await env.win.limpiarBusquedaAsignaciones();
    assert.equal(env.el('ar-tabla-buscar').value, '');
    assert.equal(env.el('ar-tabla-limpiar').hidden, true);
});

// ── Historial de Asignaciones (GET /api/admin/asignaciones/historial, solo lectura) ──

const HIST_FILAS = [
    { id: 12, usuario: { cedula: 101, nombre: 'Ana Pérez' }, rol_anterior: 'trabajador', rol_nuevo: 'contratador', accion: 'cambio', accion_etiqueta: 'Cambio',
        admin: { cedula: 900, nombre: 'Beto Admin' }, ip: '10.1.2.3', fecha: '2026-11-19T14:30:05-04:00', fecha_texto: '19/11/2026 14:30:05' },
    { id: 11, usuario: { cedula: 102, nombre: 'Carla Ruiz' }, rol_anterior: null, rol_nuevo: 'trabajador', accion: 'asignacion', accion_etiqueta: 'Asignación',
        admin: null, ip: null, fecha: '2026-11-18T09:00:00-04:00', fecha_texto: '18/11/2026 09:00:00' },
    { id: 10, usuario: { cedula: 103, nombre: 'Dani Soto' }, rol_anterior: 'contratador', rol_nuevo: null, accion: 'retirada', accion_etiqueta: 'Retirada',
        admin: { cedula: 900, nombre: 'Beto Admin' }, ip: '10.1.2.3', fecha: '2026-11-17T08:00:00-04:00', fecha_texto: '17/11/2026 08:00:00' },
];
const HISTORIAL = { status: 200, data: { data: HIST_FILAS, meta: { current_page: 1, last_page: 2, per_page: 10, total: 13, from: 1, to: 10 } } };
const getsHistorial = env => env.fetchCalls.filter(c => c.method === 'GET' && c.url.startsWith('/api/admin/asignaciones/historial?')).map(c => c.url);

test('vista historial: título, subtítulo, filtros, columnas y modal de detalle de solo lectura', () => {
    assert.match(PANEL, /Historial de Asignaciones/);
    assert.match(PANEL, /Consulta los cambios de roles realizados por los administradores de RedWork\./);
    for (const col of ['Usuario', 'Rol anterior', 'Nuevo rol', 'Acción', 'Administrador responsable', 'Fecha y hora', 'Opciones']) {
        assert.match(PANEL, new RegExp(`<th>${col}</th>`), `falta la columna ${col}`);
    }
    assert.match(PANEL, /<input type="date"[^>]*id="arh-desde"/);
    assert.match(PANEL, /<input type="date"[^>]*id="arh-hasta"/);
    assert.match(PANEL, /<option value="asignacion">➕ Asignación<\/option>/);
    assert.match(PANEL, /<option value="cambio">🔄 Cambio<\/option>/);
    assert.match(PANEL, /<option value="retirada">➖ Retirada<\/option>/);
    assert.match(PANEL, />Limpiar filtros</);
    assert.match(VIEW, /<div class="modal-overlay" id="modal-historial-detalle">/);
    const modal = VIEW.slice(VIEW.indexOf('id="modal-historial-detalle"'), VIEW.indexOf('<!-- MODALES DE PAGOS'));
    assert.match(modal, /role="dialog" aria-modal="true"/);
    assert.match(modal, /onclick="cerrarDetalleHistorial\(\)">✕</);
    assert.match(modal, /id="arh-detalle-cerrar" onclick="cerrarDetalleHistorial\(\)">Cerrar</);
    assert.doesNotMatch(modal, /<input|<select|<textarea|type="submit"/, 'el detalle es de solo lectura');
    const ids = idsDeVista(VIEW);
    for (const id of ['tbody-historial', 'arh-buscar', 'arh-buscar-limpiar', 'arh-accion', 'arh-desde', 'arh-hasta', 'arh-limpiar-filtros', 'arh-error',
        'arh-prev', 'arh-next', 'arh-paginacion-info', 'modal-historial-detalle', 'arh-detalle-body', 'arh-detalle-cerrar']) {
        assert.ok(ids.has(id), `falta #${id}`);
    }
    assert.match(SOURCE, /document\.addEventListener\('keydown', teclaDetalleHistorial\)/, 'Escape cierra el modal');
});

test('historial: carga inicial y render con etiquetas de acción (icono + texto), roles y administrador', async () => {
    const env = entorno({ historial: HISTORIAL });
    await env.asentar();
    assert.deepEqual(env.logs, []);
    assert.deepEqual(getsHistorial(env), ['/api/admin/asignaciones/historial?page=1&per_page=10']);
    const html = env.el('tbody-historial').innerHTML;
    assert.match(html, /🔄<\/span> Cambio/);
    assert.match(html, /➕<\/span> Asignación/);
    assert.match(html, /➖<\/span> Retirada/);
    assert.match(html, /Ana Pérez/);
    assert.match(html, /Beto Admin/);
    assert.match(html, /No registrado/, 'admin nulo');
    assert.match(html, /Sin rol/, 'rol anterior/nuevo nulo');
    assert.match(html, /19\/11\/2026 14:30:05/);
    assert.match(html, /Ver detalle/);
    assert.match(html, /data-label="Administrador responsable"/, 'tarjetas en móvil');
    assert.match(env.el('arh-paginacion-info').textContent, /Mostrando 1–10 de 13 · Página 1 de 2/);
    assert.equal(env.el('arh-prev').disabled, true);
    assert.equal(env.el('arh-next').disabled, false);
});

test('historial: los datos de la API se escapan en la tabla y en el detalle', async () => {
    const malo = { ...HIST_FILAS[0], usuario: { cedula: '<b>1</b>', nombre: '<script>alert(1)</script>' }, admin: { cedula: 1, nombre: '<img src=x onerror=1>' },
        rol_anterior: '<i>r</i>', ip: '"><svg>', fecha_texto: '<u>f</u>', accion_etiqueta: '<em>Cambio</em>' };
    const env = entorno({ historial: { status: 200, data: { data: [malo], meta: { current_page: 1, last_page: 1, total: 1, from: 1, to: 1 } } } });
    await env.asentar();
    env.win.verDetalleHistorial(0);
    for (const id of ['tbody-historial', 'arh-detalle-body']) {
        const html = env.el(id).innerHTML;
        assert.doesNotMatch(html, /<script>|<img src=x|<b>1|<i>r|<svg>|<u>f|<em>Cambio/, `${id} sin escapar`);
        assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    }
});

test('historial: buscador con debounce de 300 ms, botón limpiar y vuelta a la página 1', async () => {
    const env = entorno({ historial: HISTORIAL });
    await env.asentar();
    await env.win.paginaHistorial(1);
    const n = getsHistorial(env).length;
    env.el('arh-buscar').value = 'ana';
    env.win.buscarHistorialAsignaciones('a');
    env.win.buscarHistorialAsignaciones('an');
    env.win.buscarHistorialAsignaciones('ana');
    assert.equal(env.el('arh-buscar-limpiar').hidden, false);
    assert.equal(getsHistorial(env).length, n, 'no consulta antes del debounce');
    correrDebounce(env);
    await env.asentar();
    assert.deepEqual(getsHistorial(env).slice(n), ['/api/admin/asignaciones/historial?buscar=ana&page=1&per_page=10']);
    await env.win.limpiarBusquedaHistorial();
    assert.equal(env.el('arh-buscar').value, '');
    assert.equal(env.el('arh-buscar-limpiar').hidden, true);
    assert.equal(getsHistorial(env).at(-1), '/api/admin/asignaciones/historial?page=1&per_page=10');
});

test('historial: filtros de acción y fechas en la URL, «Limpiar filtros» los vacía y la paginación respeta límites', async () => {
    const env = entorno({ historial: HISTORIAL });
    await env.asentar();
    env.el('arh-accion').value = 'retirada';
    env.el('arh-desde').value = '2026-11-01';
    env.el('arh-hasta').value = '2026-11-19';
    await env.win.filtrarHistorial();
    assert.equal(getsHistorial(env).at(-1), '/api/admin/asignaciones/historial?accion=retirada&desde=2026-11-01&hasta=2026-11-19&page=1&per_page=10');
    const n = getsHistorial(env).length;
    await env.win.paginaHistorial(-1);
    assert.equal(getsHistorial(env).length, n, 'no retrocede antes de la página 1');
    await env.win.paginaHistorial(1);
    assert.match(getsHistorial(env).at(-1), /accion=retirada&desde=2026-11-01&hasta=2026-11-19&page=2&per_page=10$/);
    await env.win.limpiarFiltrosHistorial();
    for (const id of ['arh-buscar', 'arh-accion', 'arh-desde', 'arh-hasta']) assert.equal(env.el(id).value, '', `${id} sin limpiar`);
    assert.equal(getsHistorial(env).at(-1), '/api/admin/asignaciones/historial?page=1&per_page=10');
});

test('historial: mensaje sin registros', async () => {
    const env = entorno({ historial: { status: 200, data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } } });
    await env.asentar();
    assert.match(env.el('tbody-historial').innerHTML, /No hay registros de cambios de rol con los filtros seleccionados\./);
    assert.equal(env.el('arh-paginacion-info').textContent, '');
});

test('historial: un 422 de filtros se muestra visible (alerta + toast) y se oculta al corregir', async () => {
    const msg = 'La fecha final no puede ser anterior a la fecha inicial.';
    const env = entorno({ historial: c => c.url.includes('hasta=') ? { status: 422, data: { message: msg, errors: { hasta: [msg] } } } : HISTORIAL });
    await env.asentar();
    assert.equal(env.el('arh-error').hidden, true);
    env.el('arh-desde').value = '2026-11-10';
    env.el('arh-hasta').value = '2026-11-01';
    await env.win.filtrarHistorial();
    assert.equal(env.el('arh-error').hidden, false);
    assert.equal(env.el('arh-error').textContent, msg);
    assert.match(env.el('tbody-historial').innerHTML, /No se pudo cargar el historial/);
    assert.match(env.ultimoToast(), /❌ .*fecha final/);
    env.el('arh-hasta').value = '';
    await env.win.filtrarHistorial();
    assert.equal(env.el('arh-error').hidden, true, 'el error se oculta al corregir');
});

test('historial: el modal abre con foco en «Cerrar», muestra IP y se cierra con Escape devolviendo el foco', async () => {
    const env = entorno({ historial: HISTORIAL });
    await env.asentar();
    const origen = new FakeElement('boton-origen', { tag: 'button' });
    const focos = [];
    origen.focus = () => focos.push('origen');
    env.win.document.activeElement = origen;

    env.win.verDetalleHistorial(0);
    assert.ok(env.el('modal-historial-detalle').classList.contains('open'));
    assert.deepEqual(env.focos, ['arh-detalle-cerrar'], 'el foco va al botón Cerrar al abrir');
    const det = env.el('arh-detalle-body').innerHTML;
    for (const t of ['Ana Pérez', '101', 'Trabajador', 'Contratador', 'Beto Admin', '900', '19/11/2026 14:30:05', '10.1.2.3', 'Dirección IP', '🔄']) {
        assert.ok(det.includes(t), `el detalle no muestra ${t}`);
    }
    assert.deepEqual(env.escrituras(), [], 'ver el detalle no escribe');

    env.win.teclaDetalleHistorial({ key: 'Enter', preventDefault() {} });
    assert.ok(env.el('modal-historial-detalle').classList.contains('open'), 'otras teclas no cierran');
    let prevenido = false;
    env.win.teclaDetalleHistorial({ key: 'Escape', preventDefault() { prevenido = true; } });
    assert.equal(env.el('modal-historial-detalle').classList.contains('open'), false);
    assert.ok(prevenido);
    assert.deepEqual(focos, ['origen'], 'devuelve el foco al elemento que abrió el modal');
});

test('historial: sin IP el detalle omite la fila de IP; el botón Cerrar también cierra', async () => {
    const env = entorno({ historial: HISTORIAL });
    await env.asentar();
    env.win.verDetalleHistorial(1);
    assert.doesNotMatch(env.el('arh-detalle-body').innerHTML, /Dirección IP/);
    assert.match(env.el('arh-detalle-body').innerHTML, /No registrado/);
    env.win.cerrarDetalleHistorial();
    assert.equal(env.el('modal-historial-detalle').classList.contains('open'), false);
});

test('historial: se refresca sin recargar tras asignar y tras quitar un rol', async () => {
    const env = entorno({ historial: HISTORIAL, escritura: { status: 200, data: { message: 'Rol removido de Ana Pérez.' } } });
    await env.asentar();
    await env.win.editarRolUsuario(101, 'Ana Pérez');
    env.el('ar-rol-select').value = 'contratador';
    let n = getsHistorial(env).length;
    await env.win.asignarRolFetch({ preventDefault() {} });
    await env.asentar();
    assert.ok(getsHistorial(env).length > n, 'refresca el historial tras asignar');

    n = getsHistorial(env).length;
    env.win.confirmarQuitarRol(0);
    await env.win.ejecutarQuitarRol();
    await env.asentar();
    assert.ok(getsHistorial(env).length > n, 'refresca el historial tras quitar');
    assert.deepEqual(env.reloads, []);
});

test('historial: un error del POST no refresca el historial', async () => {
    const msg = 'El usuario ya tiene el rol trabajador.';
    const env = entorno({ historial: HISTORIAL, escritura: { status: 422, data: { message: msg, errors: { rol: [msg] } } } });
    await env.asentar();
    await env.win.editarRolUsuario(101, 'Ana Pérez');
    env.el('ar-rol-select').value = 'contratador';
    const n = getsHistorial(env).length;
    await env.win.asignarRolFetch({ preventDefault() {} });
    await env.asentar();
    assert.equal(getsHistorial(env).length, n);
});
