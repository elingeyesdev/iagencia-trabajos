/**
 * Incremento 4 — Ejecuta la lógica REAL de resources/js/reportes.js (vista imprimible que consume
 * GET /api/admin/reportes/{tipo}) en node:vm con fetch simulado: URL con filtros de la query string y
 * Bearer, relleno de tablas/totales, escapado de <script>, «Sin registros», errores 422 y de red/HTTP.
 * Botón «Descargar PDF»: fetch a /api/admin/reportes/{tipo}/pdf (Bearer, filtros), blob → URL del objeto
 * → <a download> → revocación; estados de carga/errores; sin window.print().
 * Ejecución: node --test "tests/js/*.test.mjs"
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { leer, crearEntorno, idsDeVista, FakeElement } from './helpers/entorno-vm.mjs';

const SOURCE = leer('resources/js/reportes.js');
const XSS = '<script>alert("xss")</script>';
const XSS_ESC = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';

const BASE = { generado_en: '2026-11-13T10:00:00-04:00', generado_en_texto: '13/11/2026 10:00', generado_por: { cedula: 1, nombre: 'Ana Admin' }, limite_filas: 1000 };

const PAGOS = {
    ...BASE, reporte: 'pagos', filtros: { estado: 'confirmado' }, simulado: true, aviso: 'Operaciones simuladas: no representan dinero real.',
    total: 1, mostrados: 1,
    por_estado: [{ estado: 'confirmado', cantidad: 1, monto: '40.00', comision: '0.00', neto: '40.00' }],
    confirmados: { cantidad: 1, monto: '40.00', comision: '0.00', neto: '40.00' },
    filas: [{
        id_pago: 7, fecha_pago: '2026-11-01', id_contratacion: 3, titulo: XSS,
        contratador: { cedula: 10, nombre: XSS }, trabajador: { cedula: 11, nombre: null },
        metodo: 'qr', estado: 'confirmado', monto: '40.00', porcentaje_comision: '0.00', monto_comision: '0.00', monto_neto: '40.00', es_simulado: true,
    }],
    estados: ['pendiente', 'confirmado', 'anulado'],
};

const USUARIOS = {
    ...BASE, reporte: 'usuarios', filtros: { rol: 'trabajador' }, total: 1, mostrados: 1, sin_rol: 0,
    por_estado: { activos: 1, inactivos: 0 }, por_rol: [{ rol: 'trabajador', total: 1 }],
    filas: [{ cedula: 5, nombre: XSS, email: 'x@y.z', ciudad: null, roles: ['trabajador'], estado: 1, fecha_registro: '2026-01-05 10:00:00' }],
    opciones: { roles: ['administrador', 'trabajador'] },
};

const vacia = l => ({ total: 0, por_estado: [], recientes: [] });
const ACTIVIDAD_VACIA = {
    ...BASE, reporte: 'actividad', filtros: { desde: '2030-01-01' },
    usuarios: vacia(), publicaciones: vacia(), postulaciones: vacia(), contrataciones: vacia(), pagos: { ...vacia(), simulado: true },
};

async function entorno(tipo, { search = '', respuesta = { status: 200, data: {} } } = {}) {
    const env = crearEntorno({
        source: SOURCE,
        filename: 'reportes.js',
        modulo: false,
        metas: { 'api-token': 'TOKEN-REP', 'reporte-tipo': tipo },
        rutas: c => (c.url.startsWith('/api/admin/reportes/') ? respuesta : undefined),
        globales: { location: { search, reload() {}, hash: '' }, URLSearchParams },
    });
    await env.win.__reporteListo;
    await env.asentar();
    return env;
}

test('las vistas de reportes contienen los ids que usa reportes.js', () => {
    const esperados = {
        usuarios: ['f-rol', 'f-estado', 'rep-filtros', 't-total', 't-activos', 't-inactivos', 'tb-roles', 'tb-filas', 'tf-total', 'rep-limite'],
        publicaciones: ['f-estado', 'f-categoria', 'f-desde', 'f-hasta', 'rep-filtros', 't-total', 't-suma-pago', 'tb-filas', 'tf-etiqueta', 'tf-suma-pago', 'rep-limite'],
        categorias: ['t-total', 't-activos', 't-inactivos', 'tb-filas', 'tf-publicaciones'],
        actividad: ['f-desde', 'f-hasta', 'rep-filtros', 'tb-resumen', 'tb-usuarios', 'tb-publicaciones', 'tb-postulaciones', 'tb-contrataciones', 'tb-pagos'],
        pagos: ['f-estado', 'f-desde', 'f-hasta', 'rep-filtros', 't-total', 't-confirmado-monto', 't-confirmado-comision', 't-confirmado-neto', 'tb-estados', 'tb-filas', 'tf-confirmados', 'rep-limite'],
    };
    const layout = idsDeVista(leer('resources/views/reportes/layout.blade.php'));
    for (const id of ['reporte-meta', 'reporte-errores', 'reporte-estado']) assert.ok(layout.has(id), `layout sin #${id}`);
    for (const [tipo, ids] of Object.entries(esperados)) {
        const vista = idsDeVista(leer(`resources/views/reportes/${tipo}.blade.php`));
        for (const id of ids) assert.ok(vista.has(id), `${tipo}.blade.php sin #${id}`);
    }
});

test('pagos: llama a la API con los filtros de la query string, Bearer y Accept JSON', async () => {
    const env = await entorno('pagos', { search: '?estado=confirmado&desde=&otro=x', respuesta: { status: 200, data: PAGOS } });
    assert.equal(env.fetchCalls.length, 1);
    const c = env.fetchCalls[0];
    assert.equal(c.url, '/api/admin/reportes/pagos?estado=confirmado');
    assert.equal(c.method, 'GET');
    assert.equal(c.headers.Authorization, 'Bearer TOKEN-REP');
    assert.equal(c.headers.Accept, 'application/json');
    assert.equal(env.el('f-estado').value, 'confirmado');
});

test('pagos: rellena tarjetas, totales por estado, listado y pie; escapa <script>', async () => {
    const env = await entorno('pagos', { search: '?estado=confirmado', respuesta: { status: 200, data: PAGOS } });
    assert.equal(env.el('t-total').textContent, '1');
    assert.equal(env.el('t-confirmado-monto').textContent, 'Bs 40.00');
    assert.equal(env.el('t-confirmado-comision').textContent, 'Bs 0.00');
    assert.equal(env.el('t-confirmado-neto').textContent, 'Bs 40.00');
    assert.equal(env.el('tb-estados').innerHTML,
        '<tr data-estado="confirmado"><td>confirmado</td><td class="num">1</td><td class="num">40.00</td><td class="num">0.00</td><td class="num">40.00</td></tr>');
    const filas = env.el('tb-filas').innerHTML;
    assert.ok(!filas.includes('<script>'), 'el listado no debe contener <script> sin escapar');
    assert.ok(filas.includes('<td>#3 — ' + XSS_ESC + '</td>'));
    assert.ok(filas.includes('<td>' + XSS_ESC + '</td>'));
    assert.ok(filas.includes('<td>(usuario 11)</td>'), 'trabajador sin nombre → (usuario cédula)');
    assert.ok(filas.includes('<td>Sí</td>'));
    assert.match(env.el('tf-confirmados').innerHTML, /Confirmados \(1\)/);
    assert.equal(env.el('rep-filtros').textContent, 'Filtros: estado = confirmado · fecha de pago: inicio a hoy');
    assert.equal(env.el('reporte-meta').textContent, 'RedWork · Generado el 13/11/2026 10:00 por Ana Admin');
    assert.equal(env.el('reporte-estado').style.display, 'none');
    assert.equal(env.el('reporte-errores').style.display, 'none');
    assert.equal(env.el('rep-limite').style.display, 'none');
});

test('usuarios: opciones de rol escapadas, tabla de roles y listado escapado', async () => {
    const env = await entorno('usuarios', { search: '?rol=trabajador', respuesta: { status: 200, data: USUARIOS } });
    assert.match(env.el('f-rol').innerHTML, /^<option value="">Todos<\/option><option value="administrador">administrador<\/option>/);
    assert.equal(env.el('f-rol').value, 'trabajador');
    assert.equal(env.el('t-total').textContent, '1');
    assert.equal(env.el('tb-roles').innerHTML, '<tr><td>trabajador</td><td class="num">1</td></tr>');
    const filas = env.el('tb-filas').innerHTML;
    assert.ok(!filas.includes('<script>'));
    assert.ok(filas.includes('<td>' + XSS_ESC + '</td>'));
    assert.ok(filas.includes('<td>—</td>'), 'ciudad nula → —');
    assert.ok(filas.includes('<td>Activo</td>'));
    assert.equal(env.el('rep-filtros').textContent, 'Filtros: rol = trabajador · estado = todos');
});

test('actividad sin datos: muestra «Sin registros» en resumen y tablas', async () => {
    const env = await entorno('actividad', { search: '?desde=2030-01-01', respuesta: { status: 200, data: ACTIVIDAD_VACIA } });
    assert.equal(env.fetchCalls[0].url, '/api/admin/reportes/actividad?desde=2030-01-01');
    assert.match(env.el('tb-resumen').innerHTML, /data-total="pagos">0<\/td><td>Sin registros<\/td>/);
    for (const id of ['tb-usuarios', 'tb-publicaciones', 'tb-postulaciones', 'tb-contrataciones', 'tb-pagos']) {
        assert.match(env.el(id).innerHTML, /class="vacio">Sin registros</, id);
    }
    assert.equal(env.el('rep-filtros').textContent, 'Periodo: 2030-01-01 a hoy');
});

test('422: muestra los errores de filtros (escapados) y no rellena tablas', async () => {
    const env = await entorno('usuarios', {
        search: '?rol=%3Cb%3E',
        respuesta: { status: 422, data: { message: 'El rol indicado no existe.', errors: { rol: ['El rol indicado no existe.'], estado: ['<b>malo</b>'] } } },
    });
    const err = env.el('reporte-errores');
    assert.equal(err.style.display, '');
    assert.equal(err.innerHTML, '<div>El rol indicado no existe.</div><div>&lt;b&gt;malo&lt;/b&gt;</div>');
    assert.match(env.el('reporte-estado').textContent, /Corrija los filtros/);
    assert.equal(env.el('tb-filas')?.innerHTML ?? '', '', 'no debe rellenar el listado');
    assert.equal(env.el('f-rol').value, '<b>', 'el formulario conserva la query');
});

for (const [nombre, respuesta, patron] of [
    ['HTTP 500', { status: 500, data: { message: 'Error' } }, /HTTP 500/],
    ['HTTP 401', { status: 401, data: { message: 'Unauthenticated.' } }, /expiró/],
    ['HTTP 403', { status: 403, data: { message: 'No' } }, /no tiene permiso/],
    ['fallo de red', { lanzar: new Error('red caída') }, /error de conexión/],
]) {
    test(`error (${nombre}): muestra el estado de error sin rellenar datos`, async () => {
        const env = await entorno('pagos', { respuesta });
        assert.match(env.el('reporte-estado').textContent, patron);
        assert.equal(env.el('reporte-estado').style.display, '');
        assert.equal(env.el('tb-filas')?.innerHTML ?? '', '', 'no debe rellenar el listado');
    });
}

test('índice (sin meta reporte-tipo): no llama a la API', async () => {
    const env = crearEntorno({ source: SOURCE, filename: 'reportes.js', modulo: false, metas: {}, globales: { location: { search: '' }, URLSearchParams } });
    await env.asentar();
    assert.equal(env.fetchCalls.length, 0);
});

// ── Botón «Descargar PDF» (sustituye a «Imprimir / Guardar como PDF» + window.print()) ──

/**
 * Entorno con fetch propio (globales.fetch sustituye al fetch simulado del helper) para poder devolver
 * cabeceras y blob en GET /api/admin/reportes/{tipo}/pdf. Registra URL.createObjectURL/revokeObjectURL,
 * window.print y los <a> creados (con click()).
 */
async function entornoPdf(tipo, { search = '', pdf = { status: 200 }, json = { status: 200, data: USUARIOS } } = {}) {
    const llamadas = [];
    const creadas = [];
    const revocadas = [];
    const impresiones = [];
    const enlaces = [];
    const BLOB = { tipo: 'application/pdf', tam: 1234 };
    const fetchPdf = async (url, opts = {}) => {
        llamadas.push({ url, opts });
        if (url.includes('/pdf')) {
            if (pdf.esperar) await pdf.esperar;
            if (pdf.lanzar) throw pdf.lanzar;
            const cab = pdf.headers ?? { 'content-type': 'application/pdf', 'content-disposition': 'attachment; filename="RedWork_Reporte_Usuarios_20261114.pdf"' };
            return {
                ok: pdf.status >= 200 && pdf.status < 300, status: pdf.status, type: pdf.type ?? 'basic',
                headers: { get: k => cab[k.toLowerCase()] ?? null },
                blob: async () => BLOB, json: async () => pdf.data,
            };
        }
        return { ok: json.status >= 200 && json.status < 300, status: json.status, headers: { get: () => null }, json: async () => json.data };
    };
    const env = crearEntorno({
        source: SOURCE,
        filename: 'reportes.js',
        modulo: false,
        metas: { 'api-token': 'TOKEN-REP', 'reporte-tipo': tipo },
        globales: {
            location: { search, reload() {}, hash: '' }, URLSearchParams, fetch: fetchPdf,
            URL: { createObjectURL: b => { creadas.push(b); return 'blob:pdf-1'; }, revokeObjectURL: u => revocadas.push(u) },
            print: () => impresiones.push(true),
        },
    });
    env.win.document.createElement = tag => {
        const e = new FakeElement(null, { tag });
        e.clicks = 0;
        e.click = () => { e.clicks++; };
        enlaces.push(e);
        return e;
    };
    await env.win.__reporteListo;
    await env.asentar();
    return { env, llamadas, creadas, revocadas, impresiones, enlaces, BLOB };
}

test('PDF: la vista ya no imprime con window.print; el layout muestra «Descargar PDF» (#btn-pdf)', () => {
    const layout = leer('resources/views/reportes/layout.blade.php');
    assert.ok(!SOURCE.includes('window.print'), 'reportes.js no debe llamar a window.print');
    assert.ok(!layout.includes('window.print'), 'el layout no debe llamar a window.print');
    assert.ok(!layout.includes('Imprimir / Guardar como PDF'));
    assert.match(layout, /<button type="button" class="btn no-print" id="btn-pdf">Descargar PDF<\/button>/);
});

test('PDF: fetch a /pdf con los filtros de la query, Bearer y Accept PDF; crea, pulsa y revoca la URL del blob', async () => {
    const { env, llamadas, creadas, revocadas, impresiones, enlaces, BLOB } = await entornoPdf('usuarios', { search: '?rol=trabajador&estado=1&desde=&otro=x' });
    assert.equal(llamadas.length, 1, 'la carga inicial consulta la API JSON');
    await env.win.descargarPdf();

    assert.equal(llamadas.length, 2);
    const c = llamadas[1];
    assert.equal(c.url, '/api/admin/reportes/usuarios/pdf?rol=trabajador&estado=1');
    assert.ok(!c.url.includes('TOKEN-REP'), 'el token nunca va en la URL');
    assert.equal(c.opts.headers.Authorization, 'Bearer TOKEN-REP');
    assert.equal(c.opts.headers.Accept, 'application/pdf');
    assert.equal(c.opts.redirect, 'manual');

    assert.deepEqual(creadas, [BLOB], 'URL.createObjectURL recibe el blob de la respuesta');
    const a = enlaces.at(-1);
    assert.equal(a.tagName, 'A');
    assert.equal(a.href, 'blob:pdf-1');
    assert.equal(a.download, 'RedWork_Reporte_Usuarios_20261114.pdf', 'nombre tomado de Content-Disposition');
    assert.equal(a.clicks, 1);
    assert.deepEqual(revocadas, [], 'se revoca tras un breve margen');
    env.timers.filter(t => t.ms === 1000).forEach(t => t.fn());
    assert.deepEqual(revocadas, ['blob:pdf-1']);

    assert.equal(impresiones.length, 0, 'no se llama a window.print');
    assert.equal(env.el('btn-pdf').disabled, false);
    assert.equal(env.el('btn-pdf').textContent, 'Descargar PDF');
    assert.equal(env.el('reporte-estado').style.display, 'none');
});

test('PDF: sin Content-Disposition usa un nombre de respaldo', async () => {
    const { env, enlaces } = await entornoPdf('usuarios', { pdf: { status: 200, headers: { 'content-type': 'application/pdf' } } });
    await env.win.descargarPdf();
    assert.equal(enlaces.at(-1).download, 'RedWork_Reporte_Usuarios.pdf');
});

test('PDF: muestra «Generando PDF…» y deshabilita el botón mientras descarga (sin descargas duplicadas)', async () => {
    let liberar;
    const esperar = new Promise(r => { liberar = r; });
    const { env, llamadas, creadas } = await entornoPdf('usuarios', { pdf: { status: 200, esperar } });
    const enCurso = env.win.descargarPdf();
    await env.asentar();
    assert.equal(env.el('btn-pdf').disabled, true);
    assert.equal(env.el('btn-pdf').textContent, 'Generando PDF…');
    assert.equal(env.el('reporte-estado').textContent, 'Generando PDF…');
    await env.win.descargarPdf(); // segundo clic mientras está deshabilitado
    assert.equal(llamadas.filter(l => l.url.includes('/pdf')).length, 1);
    liberar();
    await enCurso;
    assert.equal(creadas.length, 1);
    assert.equal(env.el('btn-pdf').disabled, false);
    assert.equal(env.el('btn-pdf').textContent, 'Descargar PDF');
});

test('PDF 422: muestra los errores de filtros escapados y no descarga', async () => {
    const { env, creadas } = await entornoPdf('usuarios', {
        pdf: { status: 422, headers: { 'content-type': 'application/json' }, data: { message: 'x', errors: { rol: ['<b>El rol indicado no existe.</b>'] } } },
    });
    await env.win.descargarPdf();
    assert.equal(env.el('reporte-errores').innerHTML, '<div>&lt;b&gt;El rol indicado no existe.&lt;/b&gt;</div>');
    assert.equal(env.el('reporte-errores').style.display, '');
    assert.match(env.el('reporte-estado').textContent, /Corrija los filtros para generar el PDF/);
    assert.equal(creadas.length, 0);
    assert.equal(env.el('btn-pdf').disabled, false);
});

for (const [nombre, pdf, patron] of [
    ['HTTP 401', { status: 401, headers: { 'content-type': 'application/json' } }, /sesión del reporte expiró/],
    ['redirección al login (token inválido sin Accept JSON)', { status: 0, type: 'opaqueredirect', headers: {} }, /sesión del reporte expiró/],
    ['HTTP 403', { status: 403, headers: { 'content-type': 'text/html' } }, /no tiene permiso/],
    ['HTTP 500', { status: 500, headers: { 'content-type': 'text/html' } }, /HTTP 500/],
    ['200 que no es PDF', { status: 200, headers: { 'content-type': 'text/html' } }, /respuesta inesperada/],
    ['fallo de red', { status: 200, lanzar: new Error('red caída') }, /error de conexión/],
]) {
    test(`PDF error (${nombre}): estado de error visible, sin descarga y botón rehabilitado`, async () => {
        const { env, creadas, enlaces } = await entornoPdf('pagos', { pdf, json: { status: 200, data: PAGOS } });
        await env.win.descargarPdf();
        assert.match(env.el('reporte-estado').textContent, patron);
        assert.match(env.el('reporte-estado').textContent, /^No se pudo generar el PDF/);
        assert.equal(env.el('reporte-estado').style.display, '');
        assert.equal(creadas.length, 0);
        assert.equal(enlaces.length, 0);
        assert.equal(env.el('btn-pdf').disabled, false);
        assert.equal(env.el('btn-pdf').textContent, 'Descargar PDF');
    });
}
