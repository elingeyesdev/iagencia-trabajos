/**
 * Bloque 6 — Sin referencias JavaScript indefinidas en los dashboards.
 *  - Admin y contratador: cada función invocada en atributos on*="fn(" de las vistas Blade y de las
 *    plantillas HTML generadas por el JS existe en window tras ejecutar el script real en node:vm
 *    (Vite lo carga como módulo: lo no asignado a window no es accesible desde onclick).
 *  - Trabajador (resources/js/trabajador-dashboard.js, cargado con @vite): se verifica su sintaxis con `node --check`,
 *    se ejecuta en vm y se comprueba que cargarPagos existe, solo hace GET a /api/trabajador/pagos
 *    y no genera botones de escritura; además, cada acción del despacho por delegación existe.
 * Ejecución: node --test "tests/js/*.test.mjs"
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { leer, neutralizarModulo, crearEntorno, idsDeVista } from './helpers/entorno-vm.mjs';

/** Nombres de función invocados en atributos on*="nombre(...)" (excluye this.x(), event.x()). */
function funcionesEnAtributos(texto) {
    return new Set([...texto.matchAll(/\son[a-z]+="\s*([A-Za-z_$][\w$]*)\s*\(/g)].map(m => m[1]));
}

const CASOS = [
    { nombre: 'admin', vista: 'resources/views/admin/dashboard.blade.php', js: 'resources/js/admin-dashboard.js', metas: { 'api-token': 'T' } },
    { nombre: 'contratador', vista: 'resources/views/dashboardContratador.blade.php', js: 'resources/js/contratador-dashboard.js', metas: { 'api-token': 'T', 'user-cedula': '1' } },
];

for (const caso of CASOS) {
    test(`${caso.nombre}: toda función usada en on*="..." de la vista y del JS está expuesta en window`, async () => {
        const vista = leer(caso.vista);
        const fuente = leer(caso.js);
        const usadas = new Set([...funcionesEnAtributos(vista), ...funcionesEnAtributos(fuente)]);
        assert.ok(usadas.size > 5, 'no se detectaron atributos on*; revisar la expresión regular');

        const env = crearEntorno({ source: neutralizarModulo(fuente), filename: path.basename(caso.js), metas: caso.metas });
        await env.asentar();
        const faltantes = [...usadas].filter(fn => typeof env.win[fn] !== 'function').sort();
        assert.deepEqual(faltantes, [], `${caso.nombre}: funciones usadas en on* sin window.${faltantes.join(', window.')}`);
    });

    test(`${caso.nombre}: la vista carga su script mediante @vite`, () => {
        assert.match(leer(caso.vista), new RegExp(`@vite\\(\\[[^\\]]*'${caso.js.replace(/[.]/g, '\\.')}'`));
    });
}

test('admin: las funciones de pagos se publican explícitamente con window.fn = fn', () => {
    const fuente = leer('resources/js/admin-dashboard.js');
    for (const fn of ['filtrarPagos', 'paginaPagos', 'verPago', 'confirmarPago', 'ejecutarConfirmarPago', 'abrirAnularPago', 'ejecutarAnularPago']) {
        assert.match(fuente, new RegExp(`window\\.${fn}\\s*=\\s*${fn}\\b`), `falta window.${fn}`);
    }
});

// ── Trabajador: resources/js/trabajador-dashboard.js (separado de la vista, cargado con @vite) ──
const VISTA_T = leer('resources/views/dashboard.blade.php');
const SCRIPT_T = (() => {
    const src = leer('resources/js/trabajador-dashboard.js');
    assert.doesNotMatch(src, /@json|\{\{|@if|@foreach/, 'el JS del trabajador no debe contener directivas Blade');
    return src;
})();

test('trabajador: la vista carga su CSS y JS mediante @vite, sin <style> ni <script> de código incrustados', () => {
    assert.match(VISTA_T, /@vite\(\[[^\]]*'resources\/css\/trabajador-dashboard\.css'[^\]]*'resources\/js\/trabajador-dashboard\.js'/);
    assert.doesNotMatch(VISTA_T, /<style[\s>]/, 'no debe quedar <style> incrustado');
    const scripts = [...VISTA_T.matchAll(/<script([^>]*)>([\s\S]*?)<\/script>/g)];
    // Únicos bloques permitidos: el arranque del tema (debe ejecutarse antes de pintar, sin lógica de negocio)
    // y el bloque de datos JSON del usuario. Todo el código del dashboard está en trabajador-dashboard.js.
    assert.deepEqual(scripts.map(m => m[1]), ['', ' type="application/json" id="trabajador-me"'], 'solo el arranque del tema y el bloque JSON del usuario');
    assert.equal(scripts[0][2], "/* Tema antes de pintar (sin parpadeo). Ver resources/js/themes.js */try{document.documentElement.setAttribute('data-theme',localStorage.getItem('redwork-theme')==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}");
    assert.match(VISTA_T, /<script type="application\/json" id="trabajador-me">@json\(\$me\)<\/script>/);
    const vite = leer('vite.config.js');
    assert.match(vite, /'resources\/css\/trabajador-dashboard\.css'/);
    assert.match(vite, /'resources\/js\/trabajador-dashboard\.js'/);
});

test('trabajador: el script inline pasa `node --check`', () => {
    const dir = mkdtempSync(path.join(tmpdir(), 'redwork-js-'));
    try {
        const archivo = path.join(dir, 'dashboard-trabajador.js');
        writeFileSync(archivo, SCRIPT_T);
        const r = spawnSync(process.execPath, ['--check', archivo], { encoding: 'utf8' });
        assert.equal(r.status, 0, `node --check falló:\n${r.stderr}`);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});

test('trabajador: define cargarPagos y todas las funciones del despacho por delegación', () => {
    for (const fn of ['cargarPagos', 'cargar', 'showPanel', 'closeModal', 'abrirOferta', 'enviarPostulacion', 'cancelarPostulacion',
                      'abrirCalificar', 'enviarCalificacion', 'guardarDatos', 'guardarProfesional', 'toggleDisponibilidad',
                      'setPuntos', 'setFiltro', 'renderBuscar', 'api']) {
        assert.match(SCRIPT_T, new RegExp(`(async\\s+)?function\\s+${fn}\\s*\\(|const\\s+${fn}\\s*=`), `no se define ${fn}`);
    }
    assert.match(SCRIPT_T, /^\s*cargarPagos\(\);\s*$/m, 'cargarPagos() debe invocarse al iniciar');
});

test('trabajador: cargarPagos ejecutado en vm solo hace GET /api/trabajador/pagos y pinta filas sin acciones', async () => {
    const respuesta = {
        simulado: true, aviso: 'Operaciones simuladas: no representan dinero real.',
        data: [{ id_pago: 1, estado: 'confirmado', monto: '40.00', metodo: 'qr', fecha_pago: '2026-11-01', publicacion: { titulo: 'Pintar <b>' }, contratador: { nombre: 'Carlos' } }],
        resumen: [{ id_contratacion: 3, estado_contratacion: 'activa', monto_acordado: '100.00', pagado_confirmado: '40.00', pagado_pendiente: '0.00', saldo_disponible: '60.00' }],
    };
    const env = crearEntorno({
        source: SCRIPT_T,
        filename: 'dashboard-trabajador.js',
        modulo: false, // script clásico: sus funciones son globales, como en el navegador
        metas: { 'api-token': 'TOKEN-T' },
        idsValidos: idsDeVista(VISTA_T),
        globales: { history: { replaceState() {} }, scrollTo() {} },
        rutas: c => (c.url === '/api/trabajador/pagos' ? { status: 200, data: respuesta } : undefined),
    });
    await env.asentar();
    assert.equal(typeof env.win.cargarPagos, 'function');

    const llamadas = env.fetchCalls.filter(c => c.url.includes('pagos'));
    assert.ok(llamadas.length >= 1);
    for (const c of llamadas) {
        assert.equal(c.url, '/api/trabajador/pagos');
        assert.equal(c.method, 'GET');
    }
    assert.deepEqual(env.escrituras(), [], 'el dashboard del trabajador no debe escribir al cargar');

    const filas = env.el('tbody-pagos').innerHTML;
    const resumen = env.el('tbody-pagos-resumen').innerHTML;
    assert.match(filas, /CONFIRMADO/);
    assert.match(filas, /Pintar &lt;b&gt;/, 'el título se escapa');
    assert.match(resumen, /#3/);
    for (const html of [filas, resumen]) {
        assert.doesNotMatch(html, /<button|onclick=|data-action=/, 'sin acciones de escritura');
    }
    assert.match(env.el('pagos-aviso').textContent, /SIMULADO/);
});

// ── Admin: botones de acción de solo icono con descripción uniforme ──
test('admin: todo botón .icon-btn (vista y plantillas JS) tiene title (tooltip) y aria-label', () => {
    const fuentes = {
        vista: leer('resources/views/admin/dashboard.blade.php'),
        js: leer('resources/js/admin-dashboard.js'),
    };
    let total = 0;
    for (const [nombre, texto] of Object.entries(fuentes)) {
        for (const m of texto.matchAll(/<button\b[^>]*class="icon-btn[^"]*"[^>]*>/g)) {
            total++;
            assert.match(m[0], /\stitle="[^"]+"/, `${nombre}: botón sin title → ${m[0].slice(0, 90)}`);
            assert.match(m[0], /\saria-label="[^"]+"/, `${nombre}: botón sin aria-label → ${m[0].slice(0, 90)}`);
        }
    }
    assert.ok(total >= 17, `se esperaban al menos 17 botones de acción (sin el «Ver» retirado de Usuarios), hay ${total}`);
    for (const m of fuentes.vista.matchAll(/<span class="modal-close"[^>]*>/g)) {
        assert.match(m[0], /title="Cerrar"/, 'cada cierre de modal debe describirse con title="Cerrar"');
    }
});

// ── Admin: menú lateral en móvil (≤ 900px usa .mobile-open; escritorio usa .collapsed) ──
for (const [nombre, movil] of [['móvil', true], ['escritorio', false]]) {
    test(`admin: toggleSidebar en ${nombre}`, async () => {
        const env = crearEntorno({
            source: neutralizarModulo(leer('resources/js/admin-dashboard.js')), filename: 'admin-dashboard.js',
            metas: { 'api-token': 'T' }, globales: { matchMedia: q => ({ matches: movil && q === '(max-width: 900px)' }) },
        });
        await env.asentar();
        const sidebar = env.el('sidebar') ?? env.win.document.getElementById('sidebar');
        const main = env.win.document.getElementById('main');
        env.win.toggleSidebar();
        assert.equal(sidebar.classList.contains('mobile-open'), movil);
        assert.equal(sidebar.classList.contains('collapsed'), !movil);
        assert.equal(main.classList.contains('expanded'), !movil);
        env.win.toggleSidebar();
        assert.equal(sidebar.classList.contains('mobile-open'), false, 'segundo clic cierra');
        if (movil) {
            env.win.toggleSidebar();
            env.win.showPanel('usuarios');
            assert.equal(sidebar.classList.contains('mobile-open'), false, 'elegir un módulo cierra el menú móvil');
        }
    });
}

test('admin/kpis: el menú de la página de KPIs usa el mismo mecanismo móvil', () => {
    const vista = leer('resources/views/admin/kpis.blade.php');
    assert.match(vista, /matchMedia\('\(max-width: 900px\)'\)/);
    assert.match(vista, /classList\.toggle\('mobile-open'\)/);
});
