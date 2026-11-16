/**
 * Bloque 6 — Ejecuta la lógica REAL de resources/js/contratador-dashboard.js para el modal de pagos:
 * registrarPago (POST con monto/fecha/metodo/observacion a la URL correcta, errores por campo en 422)
 * y abrirPagos/cargarPagosContratacion (bloqueo del formulario con saldo 0 o contratación cancelada).
 * Ejecución: node --test "tests/js/*.test.mjs"
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { leer, neutralizarModulo, crearEntorno, idsDeVista, FakeElement } from './helpers/entorno-vm.mjs';

const SOURCE = neutralizarModulo(leer('resources/js/contratador-dashboard.js'));
const VIEW = leer('resources/views/dashboardContratador.blade.php');
const IDS_VISTA = idsDeVista(VIEW);

const FORM_HTML = (() => {
    const ini = VIEW.indexOf('<form id="form-pago"');
    const fin = VIEW.indexOf('</form>', ini);
    assert.ok(ini > 0 && fin > ini, 'No se encontró form-pago en la vista');
    return VIEW.slice(ini, fin);
})();
const METODOS_VISTA = [...FORM_HTML.matchAll(/<option value="([^"]*)"/g)].map(m => m[1]);
const CAMPOS_ERROR = [...FORM_HTML.matchAll(/class="pago-error" data-campo="([^"]+)"/g)].map(m => m[1]);

const hoy = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };

function resumen(cambios = {}) {
    return { id_contratacion: 7, estado_contratacion: 'activa', monto_acordado: '100.00', pagado_no_anulado: '40.00', pagado_confirmado: '0.00', pagado_pendiente: '40.00', saldo_disponible: '60.00', estado_pago: 'parcial', ...cambios };
}

function entorno({ registro = { status: 201, data: { message: 'Pago simulado registrado. No se realizó ningún movimiento real de dinero.' } }, resumenPorId = {} } = {}) {
    const elementos = new Map();
    const def = (id, opts) => { const e = new FakeElement(id, opts); elementos.set(id, e); return e; };
    const campos = [
        def('pago-contratacion', { tag: 'input', type: 'hidden' }),
        def('pago-monto', { tag: 'input', type: 'number' }),
        def('pago-fecha', { tag: 'input', type: 'date' }),
        def('pago-metodo', { tag: 'select', options: METODOS_VISTA, value: METODOS_VISTA[0] }),
        def('pago-observacion', { tag: 'textarea' }),
    ];
    const guardar = def('pago-guardar', { tag: 'button', type: 'submit' });
    const form = def('form-pago', { tag: 'form' });
    // Como en el navegador: reset() no altera input[type=hidden] (su value es el atributo por defecto).
    form.reset = () => campos.forEach(c => { if (c.type !== 'hidden') c.value = c._default; });
    const errores = new Map(CAMPOS_ERROR.map(c => [c, new FakeElement(null, { dataset: { campo: c } })]));

    const env = crearEntorno({
        source: SOURCE,
        filename: 'contratador-dashboard.js',
        metas: { 'api-token': 'TOKEN-C', 'user-cedula': '5001' },
        elementos,
        querySelectorAll: sel => {
            if (sel === '#form-pago .pago-error') return [...errores.values()];
            if (sel === '#form-pago input, #form-pago select, #form-pago textarea, #pago-guardar') return [...campos, guardar];
            return undefined;
        },
        querySelector: sel => {
            const m = sel.match(/^#form-pago \.pago-error\[data-campo="([^"]+)"\]$/);
            return m ? errores.get(m[1]) ?? null : undefined;
        },
        rutas: c => {
            const m = c.url.match(/^\/api\/contratador\/contrataciones\/(\d+)\/pagos$/);
            if (m && c.method === 'POST') return registro;
            if (m && c.method === 'GET') {
                return { status: 200, data: { simulado: true, aviso: 'Operaciones simuladas.', data: { resumen: resumen({ id_contratacion: Number(m[1]), ...(resumenPorId[m[1]] || {}) }), pagos: [] } } };
            }
            return undefined;
        },
    });
    return Object.assign(env, { campos, guardar, errores });
}

const evento = () => { const e = { prevenido: false, preventDefault() { e.prevenido = true; } }; return e; };

test('la vista: form-pago tiene los 5 métodos y contenedores de error para cada campo enviado', () => {
    assert.deepEqual(METODOS_VISTA, ['efectivo', 'transferencia', 'qr', 'deposito', 'otro']);
    assert.deepEqual(CAMPOS_ERROR, ['monto', 'fecha_pago', 'metodo', 'observacion']);
    assert.match(FORM_HTML, /onsubmit="registrarPago\(event\)"/);
});

test('carga: se ejecuta sin errores y expone abrirPagos/registrarPago en window', async () => {
    const env = entorno();
    await env.asentar();
    assert.equal(typeof env.win.abrirPagos, 'function');
    assert.equal(typeof env.win.registrarPago, 'function');
    assert.deepEqual(env.escrituras(), []);
});

test('registrarPago: POST a /api/contratador/contrataciones/{id}/pagos con monto, fecha_pago, metodo y observacion', async () => {
    const env = entorno();
    await env.asentar();
    await env.win.abrirPagos(7);
    env.el('pago-monto').value = '40.50';
    env.el('pago-fecha').value = '2026-11-01';
    env.el('pago-metodo').value = 'qr';
    env.el('pago-observacion').value = '  Adelanto  ';
    const ev = evento();
    await env.win.registrarPago(ev);

    assert.ok(ev.prevenido, 'debe llamar a preventDefault');
    const w = env.escrituras();
    assert.equal(w.length, 1);
    assert.equal(w[0].method, 'POST');
    assert.equal(w[0].url, '/api/contratador/contrataciones/7/pagos');
    assert.equal(w[0].headers.Authorization, 'Bearer TOKEN-C');
    assert.equal(w[0].headers['Content-Type'], 'application/json');
    assert.deepEqual(w[0].body, { monto: '40.50', fecha_pago: '2026-11-01', metodo: 'qr', observacion: 'Adelanto' });

    // Tras 201: recarga pagos de la contratación y contrataciones; formulario reiniciado con fecha de hoy.
    const gets = env.fetchCalls.slice(env.fetchCalls.indexOf(w[0]) + 1).map(c => c.url);
    assert.ok(gets.includes('/api/contratador/contrataciones/7/pagos'));
    assert.ok(gets.includes('/api/contratador/contrataciones'));
    assert.equal(env.el('pago-monto').value, '');
    assert.equal(env.el('pago-fecha').value, hoy());
    assert.equal(env.guardar.disabled, false);
    assert.match(env.ultimoToast(), /simulado/i);
});

test('registrarPago: observación vacía se envía como null', async () => {
    const env = entorno();
    await env.asentar();
    await env.win.abrirPagos(7);
    env.el('pago-monto').value = '10';
    env.el('pago-observacion').value = '   ';
    await env.win.registrarPago(evento());
    assert.equal(env.escrituras()[0].body.observacion, null);
    assert.equal(env.escrituras()[0].body.metodo, 'efectivo');
    assert.equal(env.escrituras()[0].body.fecha_pago, hoy());
});

test('registrarPago con 422: muestra cada error en su campo, no recarga y reactiva el botón', async () => {
    const errors = {
        monto: ['El monto supera el saldo disponible de la contratación (60.00).'],
        fecha_pago: ['La fecha de pago no puede ser posterior a hoy.'],
        metodo: ['El método de pago no es válido. Use: efectivo, transferencia, qr, deposito u otro.'],
    };
    const env = entorno({ registro: { status: 422, data: { message: 'Datos inválidos.', errors } } });
    await env.asentar();
    await env.win.abrirPagos(7);
    env.el('pago-monto').value = '999';
    const antes = env.fetchCalls.length;
    await env.win.registrarPago(evento());

    assert.equal(env.errores.get('monto').textContent, errors.monto[0]);
    assert.equal(env.errores.get('fecha_pago').textContent, errors.fecha_pago[0]);
    assert.equal(env.errores.get('metodo').textContent, errors.metodo[0]);
    assert.equal(env.errores.get('observacion').textContent, '');
    assert.equal(env.fetchCalls.length, antes + 1, 'solo el POST; sin recargas tras 422');
    assert.equal(env.el('pago-monto').value, '999', 'los datos se conservan');
    assert.equal(env.guardar.disabled, false);

    // Un error previo en un campo que ya no falla se limpia en el siguiente intento.
    env.errores.get('observacion').textContent = 'error antiguo';
    await env.win.registrarPago(evento());
    assert.equal(env.errores.get('observacion').textContent, '');
    assert.equal(env.errores.get('monto').textContent, errors.monto[0]);
});

test('registrarPago con 403/503 sin errors: toast con el mensaje del servidor', async () => {
    const env = entorno({ registro: { status: 403, data: { message: 'No puede registrar pagos sobre una contratación ajena.' } } });
    await env.asentar();
    await env.win.abrirPagos(7);
    env.el('pago-monto').value = '5';
    await env.win.registrarPago(evento());
    assert.match(env.ultimoToast(), /contratación ajena/);
    for (const e of env.errores.values()) assert.equal(e.textContent, '');
});

test('abrirPagos: con saldo disponible el formulario queda habilitado', async () => {
    const env = entorno();
    await env.asentar();
    await env.win.abrirPagos(7);
    assert.equal(env.el('pago-contratacion').value, '7');
    assert.equal(env.el('pagos-titulo').textContent, '#7');
    assert.ok(env.el('modal-pagos').classList.contains('open'));
    for (const c of [...env.campos, env.guardar]) assert.equal(c.disabled, false, c.id);
    assert.equal(env.el('pago-bloqueo').style.display, 'none');
});

test('abrirPagos: saldo 0 deshabilita el formulario y muestra el aviso', async () => {
    const env = entorno({ resumenPorId: { 8: { saldo_disponible: '0.00', pagado_no_anulado: '100.00', estado_pago: 'completo' } } });
    await env.asentar();
    await env.win.abrirPagos(8);
    for (const c of env.campos.filter(c => c.type !== 'hidden')) assert.equal(c.disabled, true, c.id);
    assert.equal(env.guardar.disabled, true);
    assert.equal(env.el('pago-bloqueo').style.display, 'block');
    assert.match(env.el('pago-bloqueo').textContent, /saldo disponible/);
});

test('abrirPagos: contratación cancelada deshabilita el formulario aunque tenga saldo', async () => {
    const env = entorno({ resumenPorId: { 9: { estado_contratacion: 'cancelada', saldo_disponible: '60.00' } } });
    await env.asentar();
    await env.win.abrirPagos(9);
    assert.equal(env.guardar.disabled, true);
    assert.equal(env.el('pago-monto').disabled, true);
    assert.match(env.el('pago-bloqueo').textContent, /cancelada/);
});

test('abrirPagos: el bloqueo de una contratación no se arrastra a la siguiente', async () => {
    const env = entorno({ resumenPorId: { 9: { estado_contratacion: 'cancelada' } } });
    await env.asentar();
    await env.win.abrirPagos(9);
    assert.equal(env.guardar.disabled, true);
    await env.win.abrirPagos(7);
    assert.equal(env.guardar.disabled, false);
    assert.equal(env.el('pago-monto').disabled, false);
    assert.equal(env.el('pago-bloqueo').style.display, 'none');
    assert.equal(env.el('pago-bloqueo').textContent, '');
});

test('todos los ids que usan abrirPagos/registrarPago existen en la vista del contratador', async () => {
    const env = entorno();
    await env.asentar();
    env.idsConsultados.length = 0;
    await env.win.abrirPagos(7);
    env.el('pago-monto').value = '1';
    await env.win.registrarPago(evento());
    await env.asentar();
    const faltantes = [...new Set(env.idsConsultados)].filter(id => !IDS_VISTA.has(id));
    assert.deepEqual(faltantes, [], `ids usados por el JS que no existen en la vista: ${faltantes}`);
});
