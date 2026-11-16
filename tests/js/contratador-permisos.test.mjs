/**
 * Dashboard del contratador — acciones visibles según rol, propietario y estado (nivel 1 de la matriz de permisos)
 * y menú móvil. Ejecuta la lógica REAL de resources/js/contratador-dashboard.js en node:vm.
 * Ejecución: node --test "tests/js/*.test.mjs"
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { leer, neutralizarModulo, crearEntorno, idsDeVista } from './helpers/entorno-vm.mjs';

const SOURCE = neutralizarModulo(leer('resources/js/contratador-dashboard.js'));
const VIEW = leer('resources/views/dashboardContratador.blade.php');
const ME = '7001';

const POSTULACIONES = [
    { id_postulacion: 1, estado: 'pendiente', fecha_postulacion: '2026-11-01', mensaje: 'Hola', trabajador: { nombre: 'Ana' }, publicacion: { titulo: 'Pintura' } },
    { id_postulacion: 2, estado: 'aceptada', fecha_postulacion: '2026-11-01', trabajador: { nombre: 'Luis' }, publicacion: { titulo: 'Pintura' } },
    { id_postulacion: 3, estado: 'rechazada', fecha_postulacion: '2026-11-01', trabajador: { nombre: 'Eva' }, publicacion: { titulo: 'Pintura' } },
];
const CONTRATACIONES = [
    { id_contratacion: 10, estado: 'activa', monto_acordado: '100', calificaciones: [], trabajador: { nombre: 'Ana' }, publicacion: { titulo: 'A' } },
    { id_contratacion: 11, estado: 'finalizada', monto_acordado: '100', calificaciones: [], trabajador: { nombre: 'Luis' }, publicacion: { titulo: 'B' } },
    { id_contratacion: 12, estado: 'finalizada', monto_acordado: '100', trabajador: { nombre: 'Eva' }, publicacion: { titulo: 'C' },
      calificaciones: [{ cedula_calificador: 7001, cedula_calificado: 9, puntuacion: 5 }, { cedula_calificador: 9, cedula_calificado: 7001, puntuacion: 4 }] },
    { id_contratacion: 13, estado: 'cancelada', monto_acordado: '100', calificaciones: [], trabajador: { nombre: 'Leo' }, publicacion: { titulo: 'D' } },
];

function entorno(globales = {}) {
    const env = crearEntorno({
        source: SOURCE, filename: 'contratador-dashboard.js',
        metas: { 'api-token': 'T', 'user-cedula': ME },
        idsValidos: idsDeVista(VIEW),
        globales,
        rutas: c => {
            if (c.url === '/api/contratador/postulaciones') return { status: 200, data: POSTULACIONES };
            if (c.url === '/api/contratador/contrataciones') return { status: 200, data: CONTRATACIONES };
            return undefined;
        },
    });
    return env;
}

/** Divide el HTML de un tbody en filas y devuelve, por fila, el número de celdas y los textos de sus botones. */
function filas(html) {
    return html.split('<tr>').slice(1).map(tr => ({
        html: tr,
        celdas: (tr.match(/<td[\s>]/g) || []).length,
        botones: [...tr.matchAll(/<button[^>]*>([^<]*)<\/button>/g)].map(m => m[1].trim()),
    }));
}

test('postulaciones recibidas: 5 celdas por fila (igual que el encabezado) y Aceptar/Rechazar solo en pendientes', async () => {
    const env = entorno();
    await env.asentar();

    const encabezado = [...VIEW.slice(VIEW.indexOf('id="panel-postulaciones"'), VIEW.indexOf('id="tbody-postulaciones-recibidas"')).matchAll(/<th>([^<]+)<\/th>/g)].map(m => m[1]);
    assert.deepEqual(encabezado, ['Trabajador', 'Labor / Oferta', 'Mensaje', 'Estado', 'Acciones']);

    const f = filas(env.el('tbody-postulaciones-recibidas').innerHTML);
    assert.equal(f.length, 3);
    for (const fila of f) assert.equal(fila.celdas, encabezado.length, 'cada fila debe tener tantas celdas como columnas');
    assert.deepEqual(f.map(x => x.botones), [['Aceptar', 'Rechazar'], [], []]);
    assert.equal(env.el('stat-pendientes').textContent, 1);
});

test('contrataciones: acciones según estado; nunca confirmar ni anular pagos', async () => {
    const env = entorno();
    await env.asentar();

    const f = filas(env.el('tbody-contrataciones').innerHTML);
    assert.deepEqual(f.map(x => x.botones), [
        ['✔ Finalizar', '✖ Cancelar', '💳 Pagos'],   // activa
        ['⭐ Calificar', '💳 Pagos'],                  // finalizada sin calificar
        ['💳 Pagos'],                                  // finalizada ya calificada
        ['💳 Pagos'],                                  // cancelada: solo consulta de pagos
    ]);
    assert.match(f[2].html, /⭐ 5\/5 calificado/);
    assert.match(f[2].html, /Recibida: ⭐ 4\/5/, 'muestra la calificación recibida del trabajador');
    assert.doesNotMatch(f[1].html, /Recibida/);
    const todo = env.el('tbody-contrataciones').innerHTML;
    assert.doesNotMatch(todo, /confirmar|anular/i);
    assert.equal(env.el('stat-contrataciones').textContent, 1);
});

test('publicaciones: la acción de baja se presenta como «Desactivar» y solo para publicaciones activas', () => {
    const tabla = VIEW.slice(VIEW.indexOf('id="panel-mis-publicaciones"'), VIEW.indexOf('id="panel-postulaciones"'));
    assert.match(tabla, /@if\(\$pub->estado\)\s*<button class="btn btn-danger" title="Desactivar" onclick="eliminarPublicacion\(/);
    assert.doesNotMatch(tabla, /🗑️ Eliminar/);
    const js = leer('resources/js/contratador-dashboard.js');
    assert.match(js, /¿Deseas desactivar esta oferta laboral\?/);
});

for (const [nombre, movil] of [['móvil', true], ['escritorio', false]]) {
    test(`menú lateral del contratador en ${nombre}`, async () => {
        const env = entorno({ matchMedia: q => ({ matches: movil && q === '(max-width: 900px)' }) });
        await env.asentar();
        const sidebar = env.win.document.getElementById('sidebar'), main = env.win.document.getElementById('main');
        env.win.toggleSidebar();
        assert.equal(sidebar.classList.contains('mobile-open'), movil);
        assert.equal(sidebar.classList.contains('collapsed'), !movil);
        assert.equal(main.classList.contains('expanded'), !movil);
        if (movil) {
            env.win.showPanel('contrataciones');
            assert.equal(sidebar.classList.contains('mobile-open'), false, 'elegir una sección cierra el menú móvil');
        }
    });
}

test('contratador-dashboard.css: reglas móviles con el mismo mecanismo que el administrador', () => {
    const css = leer('resources/css/contratador-dashboard.css');
    const movil = css.slice(css.indexOf('@media (max-width: 900px)'));
    assert.match(movil, /\.sidebar \{ transform: translateX\(calc\(-1 \* var\(--sidebar-w\)\)\); \}/);
    assert.match(movil, /\.sidebar\.mobile-open \{ transform: translateX\(0\); \}/);
    assert.match(movil, /\.main \{ margin-left: 0; \}/);
    assert.match(css, /\.menu-toggle span \{/, 'el botón de menú debe mostrar sus líneas');
});
