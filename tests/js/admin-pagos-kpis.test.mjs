/**
 * Bloque 6 — Ejecuta la lógica REAL de resources/js/admin-dashboard.js para KPIs y pagos simulados:
 * cargarKpis (tarjetas y '—' en error), cargarPagosAdmin (URL con filtros, paginación, acciones),
 * confirmarPago/ejecutarConfirmarPago (PUT .../confirmar) y abrirAnularPago/ejecutarAnularPago
 * (motivo y errores 422). Ejecución: node --test "tests/js/*.test.mjs"
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { leer, neutralizarModulo, crearEntorno, idsDeVista, FakeElement } from './helpers/entorno-vm.mjs';

const SOURCE = neutralizarModulo(leer('resources/js/admin-dashboard.js'));
const VIEW = leer('resources/views/admin/dashboard.blade.php');
const IDS_VISTA = idsDeVista(VIEW);

const KPIS_OK = { publicaciones_activas: 1234, contrataciones_activas: 7, pagos_confirmados_bs: '40.00', simulado: true, generado_en: '2026-11-07T10:00:00-04:00' };
const fmtEntero = n => Number(n).toLocaleString('es-BO');
const bs = n => 'Bs ' + Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const PAGOS_OK = {
    modo_pagos: 'simulado', simulado: true, aviso: 'Operaciones simuladas: no representan dinero real.',
    data: [
        { id_pago: 11, id_contratacion: 3, estado: 'pendiente', monto: '40.00', metodo: 'qr', fecha_pago: '2026-11-01', contratacion: { id_contratacion: 3 }, publicacion: { titulo: 'Pintar' }, contratador: { nombre: 'Carlos' }, trabajador: { nombre: 'Tania' } },
        { id_pago: 12, id_contratacion: 3, estado: 'confirmado', monto: '10.00', metodo: 'efectivo', fecha_pago: '2026-11-02' },
        { id_pago: 13, id_contratacion: 3, estado: 'anulado', monto: '5.00', metodo: 'otro', fecha_pago: '2026-11-03' },
    ],
    meta: { current_page: 1, last_page: 3, per_page: 15, total: 33, from: 1, to: 15 },
    totales: { pendiente: { cantidad: 1, monto: '40.00' }, confirmado: { cantidad: 1, monto: '10.00' }, anulado: { cantidad: 1, monto: '5.00' } },
};

function entorno({ kpis = { status: 200, data: KPIS_OK }, pagos = { status: 200, data: PAGOS_OK }, accion = { status: 200, data: { message: 'OK servidor.' } } } = {}) {
    const elementos = new Map();
    for (const id of ['pagos-filtro-estado', 'pagos-filtro-desde', 'pagos-filtro-hasta', 'pago-anular-motivo']) {
        elementos.set(id, new FakeElement(id, { tag: id.includes('estado') ? 'select' : 'input' }));
    }
    return crearEntorno({
        source: SOURCE,
        filename: 'admin-dashboard.js',
        metas: { 'api-token': 'TOKEN-PRUEBA' },
        elementos,
        rutas: c => {
            if (c.url === '/api/admin/kpis') return kpis;
            if (c.method === 'GET' && c.url.startsWith('/api/admin/pagos?')) return pagos;
            if (c.method === 'PUT') return accion;
            return undefined;
        },
    });
}

const urlsPagos = env => env.fetchCalls.filter(c => c.method === 'GET' && c.url.startsWith('/api/admin/pagos?')).map(c => c.url);
const getsKpi = env => env.fetchCalls.filter(c => c.url === '/api/admin/kpis').length;

test('carga: se ejecuta sin errores y expone en window las funciones de pagos', async () => {
    const env = entorno();
    await env.asentar();
    for (const fn of ['filtrarPagos', 'paginaPagos', 'verPago', 'confirmarPago', 'ejecutarConfirmarPago', 'abrirAnularPago', 'ejecutarAnularPago']) {
        assert.equal(typeof env.win[fn], 'function', `window.${fn} no está expuesta`);
    }
    assert.deepEqual(env.logs, []);
    assert.deepEqual(env.escrituras(), []);
});

test('cargarKpis: rellena las 3 tarjetas con los datos de /api/admin/kpis (Bearer)', async () => {
    const env = entorno();
    await env.asentar();
    const call = env.fetchCalls.find(c => c.url === '/api/admin/kpis');
    assert.ok(call, 'debe consultar /api/admin/kpis');
    assert.equal(call.method, 'GET');
    assert.equal(call.headers.Authorization, 'Bearer TOKEN-PRUEBA');
    assert.equal(env.el('kpi-publicaciones-activas').textContent, fmtEntero(1234));
    assert.equal(env.el('kpi-contrataciones-activas').textContent, fmtEntero(7));
    assert.equal(env.el('kpi-pagos-confirmados').textContent, bs('40.00'));
    assert.match(env.el('kpi-generado').textContent, /Simulado/);
});

for (const [nombre, kpis] of [['HTTP 500', { status: 500, data: { message: 'Error' } }], ['HTTP 403', { status: 403, data: { message: 'No' } }], ['fallo de red', { lanzar: new Error('red caída') }]]) {
    test(`cargarKpis: con ${nombre} muestra '—' en las 3 tarjetas`, async () => {
        const env = entorno({ kpis });
        await env.asentar();
        for (const id of ['kpi-publicaciones-activas', 'kpi-contrataciones-activas', 'kpi-pagos-confirmados']) {
            assert.equal(env.el(id).textContent, '—', id);
        }
        assert.equal(env.el('kpi-generado').textContent, 'No se pudieron cargar los indicadores.');
    });
}

test('cargarPagosAdmin: carga inicial sin filtros y luego URL con estado/desde/hasta codificados', async () => {
    const env = entorno();
    await env.asentar();
    assert.deepEqual(urlsPagos(env), ['/api/admin/pagos?page=1&per_page=15']);

    env.el('pagos-filtro-estado').value = 'confirmado';
    env.el('pagos-filtro-desde').value = '2026-01-01';
    env.el('pagos-filtro-hasta').value = '2026-01-31';
    env.win.filtrarPagos();
    await env.asentar();
    assert.equal(urlsPagos(env).at(-1), '/api/admin/pagos?page=1&per_page=15&estado=confirmado&desde=2026-01-01&hasta=2026-01-31');

    // Filtro vacío: el parámetro no se envía.
    env.el('pagos-filtro-estado').value = '';
    env.win.filtrarPagos();
    await env.asentar();
    assert.equal(urlsPagos(env).at(-1), '/api/admin/pagos?page=1&per_page=15&desde=2026-01-01&hasta=2026-01-31');
});

test('cargarPagosAdmin: paginación respeta last_page y no pide páginas fuera de rango', async () => {
    const env = entorno();
    await env.asentar();
    const antes = urlsPagos(env).length;
    env.win.paginaPagos(-1); // página 0: no hace nada
    await env.asentar();
    assert.equal(urlsPagos(env).length, antes);
    env.win.paginaPagos(1);
    await env.asentar();
    assert.equal(urlsPagos(env).at(-1), '/api/admin/pagos?page=2&per_page=15');
});

test('cargarPagosAdmin: totales por estado y acciones según el estado del pago', async () => {
    const env = entorno();
    await env.asentar();
    assert.equal(env.el('pagos-tot-pendiente-monto').textContent, bs('40.00'));
    assert.equal(env.el('pagos-tot-confirmado-cant').textContent, '1 pago(s)');
    const html = env.el('tbody-pagos').innerHTML;
    const filas = html.split('<tr>').slice(1);
    assert.equal(filas.length, 3);
    assert.match(filas[0], /confirmarPago\(11\)/);
    assert.match(filas[0], /abrirAnularPago\(11\)/);
    assert.doesNotMatch(filas[1], /confirmarPago\(/, 'un confirmado no se vuelve a confirmar');
    assert.match(filas[1], /abrirAnularPago\(12\)/);
    assert.doesNotMatch(filas[2], /confirmarPago\(|abrirAnularPago\(/, 'un anulado no tiene acciones de escritura');
    assert.match(html, /SIM/);
});

test('confirmarPago + ejecutarConfirmarPago: PUT a /api/admin/pagos/{id}/confirmar, cierra el modal y recarga pagos y KPIs', async () => {
    const env = entorno();
    await env.asentar();
    env.win.confirmarPago(11);
    assert.ok(env.el('modal-pago-confirmar').classList.contains('open'));
    assert.equal(env.el('pago-confirmar-nombre').textContent, '#11');
    const kpisAntes = getsKpi(env), pagosAntes = urlsPagos(env).length;

    await env.win.ejecutarConfirmarPago();

    const w = env.escrituras();
    assert.equal(w.length, 1);
    assert.equal(w[0].method, 'PUT');
    assert.equal(w[0].url, '/api/admin/pagos/11/confirmar');
    assert.equal(w[0].headers.Authorization, 'Bearer TOKEN-PRUEBA');
    assert.equal(env.el('modal-pago-confirmar').classList.contains('open'), false);
    assert.equal(getsKpi(env), kpisAntes + 1, 'debe recargar KPIs');
    assert.equal(urlsPagos(env).length, pagosAntes + 1, 'debe recargar el listado');
    assert.equal(env.el('pago-confirmar-btn').disabled, false);
    assert.match(env.ultimoToast(), /OK servidor\./);
});

test('ejecutarConfirmarPago con 422: muestra el error, mantiene el modal y reactiva el botón', async () => {
    const env = entorno({ accion: { status: 422, data: { message: 'No se puede confirmar: la contratación está cancelada.' } } });
    await env.asentar();
    env.win.confirmarPago(11);
    await env.win.ejecutarConfirmarPago();
    assert.ok(env.el('modal-pago-confirmar').classList.contains('open'));
    assert.equal(env.el('pago-confirmar-btn').disabled, false);
    assert.match(env.ultimoToast(), /contratación está cancelada/);
});

test('abrirAnularPago + ejecutarAnularPago: limpia el formulario y envía el motivo (recortado) por PUT', async () => {
    const env = entorno();
    await env.asentar();
    env.el('pago-anular-motivo').value = 'texto de una anulación previa';
    env.win.document.getElementById('pago-anular-error').textContent = 'error previo';
    env.win.abrirAnularPago(12);
    assert.equal(env.el('pago-anular-motivo').value, '');
    assert.equal(env.el('pago-anular-error').textContent, '');
    assert.equal(env.el('pago-anular-nombre').textContent, '#12');
    assert.ok(env.el('modal-pago-anular').classList.contains('open'));

    env.el('pago-anular-motivo').value = '  Registro duplicado  ';
    await env.win.ejecutarAnularPago();
    const w = env.escrituras();
    assert.equal(w.length, 1);
    assert.equal(w[0].method, 'PUT');
    assert.equal(w[0].url, '/api/admin/pagos/12/anular');
    assert.deepEqual(w[0].body, { motivo_anulacion: 'Registro duplicado' });
    assert.equal(w[0].headers['Content-Type'], 'application/json');
    assert.equal(env.el('modal-pago-anular').classList.contains('open'), false);
});

test('ejecutarAnularPago con 422: muestra el error del campo motivo, no cierra ni recarga', async () => {
    const msg = 'El motivo de anulación debe tener al menos 5 caracteres.';
    const env = entorno({ accion: { status: 422, data: { message: msg, errors: { motivo_anulacion: [msg] } } } });
    await env.asentar();
    env.win.abrirAnularPago(12);
    env.el('pago-anular-motivo').value = 'ab';
    const pagosAntes = urlsPagos(env).length, kpisAntes = getsKpi(env);
    await env.win.ejecutarAnularPago();
    assert.equal(env.el('pago-anular-error').textContent, msg);
    assert.ok(env.el('modal-pago-anular').classList.contains('open'));
    assert.equal(env.el('pago-anular-btn').disabled, false);
    assert.equal(urlsPagos(env).length, pagosAntes);
    assert.equal(getsKpi(env), kpisAntes);
    assert.equal(env.el('pago-anular-motivo').value, 'ab', 'el motivo se conserva para corregir');
});

test('todos los ids que usan KPIs y pagos existen en la vista Blade del admin', async () => {
    const env = entorno();
    await env.asentar();
    env.win.filtrarPagos();
    env.win.confirmarPago(11);
    await env.win.ejecutarConfirmarPago();
    env.win.abrirAnularPago(12);
    env.el('pago-anular-motivo').value = 'Motivo válido';
    await env.win.ejecutarAnularPago();
    await env.win.verPago(11);
    await env.asentar();
    const faltantes = [...new Set(env.idsConsultados)].filter(id => !IDS_VISTA.has(id));
    assert.deepEqual(faltantes, [], `ids usados por el JS que no existen en la vista: ${faltantes}`);
});
