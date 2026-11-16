/**
 * Sistema de temas claro/oscuro (resources/js/themes.js + resources/css/themes.css) e integración en los
 * cuatro dashboards; sección Calificaciones del contratador y menú simplificado del trabajador.
 * Ejecución: node --test "tests/js/*.test.mjs"
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { leer, neutralizarModulo, crearEntorno, idsDeVista, FakeClassList } from './helpers/entorno-vm.mjs';

const THEMES_JS = leer('resources/js/themes.js');
const THEMES_CSS = leer('resources/css/themes.css');
const VISTAS = {
    admin: 'resources/views/admin/dashboard.blade.php',
    bi: 'resources/views/admin/kpis.blade.php',
    contratador: 'resources/views/dashboardContratador.blade.php',
    trabajador: 'resources/views/dashboard.blade.php',
};

// ── Entorno mínimo para themes.js (documentElement, localStorage, eventos) ──
function entornoTemas({ guardado = null, lanzarStorage = false, reducido = false } = {}) {
    const almacen = new Map(guardado === null ? [] : [['redwork-theme', guardado]]);
    const atributos = { 'data-theme': 'dark' };
    const oyentesDoc = {}, oyentesWin = {}, eventos = [];
    const raiz = {
        classList: new FakeClassList(), style: {},
        getAttribute: k => atributos[k] ?? null,
        setAttribute: (k, v) => { atributos[k] = String(v); },
    };
    const crearBoton = () => {
        const attrs = {};
        const etiqueta = { textContent: 'Tema oscuro' }, icono = { textContent: '🌙' };
        const boton = {
            attrs, etiqueta, icono,
            getAttribute: k => attrs[k] ?? null, setAttribute: (k, v) => { attrs[k] = String(v); },
            querySelector: s => (s === '.theme-switch-label' ? etiqueta : s === '.theme-switch-thumb' ? icono : null),
            closest: s => (s === '[data-theme-toggle]' ? boton : null),
        };
        return boton;
    };
    const botones = [crearBoton(), crearBoton()];
    const document = {
        documentElement: raiz, readyState: 'complete',
        querySelectorAll: s => (s === '[data-theme-toggle]' ? botones : []),
        addEventListener: (t, fn) => { (oyentesDoc[t] ||= []).push(fn); },
    };
    const localStorage = {
        getItem: k => { if (lanzarStorage) throw new Error('bloqueado'); return almacen.has(k) ? almacen.get(k) : null; },
        setItem: (k, v) => { if (lanzarStorage) throw new Error('bloqueado'); almacen.set(k, v); },
    };
    class CustomEvent { constructor(tipo, init) { this.type = tipo; this.detail = init?.detail; } }
    const sandbox = {
        document, localStorage, CustomEvent, setTimeout: () => 1, clearTimeout() {},
        matchMedia: q => ({ matches: reducido && q.includes('reduce') }),
        addEventListener: (t, fn) => { (oyentesWin[t] ||= []).push(fn); },
        dispatchEvent: ev => { eventos.push(ev); return true; },
    };
    sandbox.window = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(THEMES_JS, sandbox, { filename: 'themes.js' });
    return {
        T: sandbox.RedWorkTheme, raiz, atributos, almacen, botones, eventos,
        clic: objetivo => oyentesDoc.click.forEach(fn => fn({ target: objetivo, preventDefault() {} })),
        storage: (key, newValue) => oyentesWin.storage.forEach(fn => fn({ key, newValue })),
    };
}

test('themes.js: oscuro por defecto, sin preferencia guardada', () => {
    const e = entornoTemas();
    assert.equal(e.atributos['data-theme'], 'dark');
    assert.equal(e.raiz.style.colorScheme, 'dark');
    for (const b of e.botones) {
        assert.equal(b.attrs['aria-checked'], 'false');
        assert.equal(b.etiqueta.textContent, 'Tema oscuro');
        assert.equal(b.icono.textContent, '🌙');
    }
    assert.equal(e.almacen.has('redwork-theme'), false, 'cargar no guarda nada por sí solo');
});

test('themes.js: aplica la preferencia guardada y descarta valores no válidos', () => {
    assert.equal(entornoTemas({ guardado: 'light' }).atributos['data-theme'], 'light');
    assert.equal(entornoTemas({ guardado: 'neon' }).atributos['data-theme'], 'dark');
    assert.equal(entornoTemas({ lanzarStorage: true }).atributos['data-theme'], 'dark', 'sin almacenamiento disponible: oscuro');
});

test('themes.js: el interruptor alterna, sincroniza todos los interruptores, guarda y notifica', () => {
    const e = entornoTemas();
    e.clic(e.botones[0]);
    assert.equal(e.atributos['data-theme'], 'light');
    assert.equal(e.almacen.get('redwork-theme'), 'light');
    for (const b of e.botones) {
        assert.equal(b.attrs['aria-checked'], 'true');
        assert.equal(b.etiqueta.textContent, 'Tema claro');
        assert.equal(b.icono.textContent, '☀️');
        assert.match(b.attrs['aria-label'], /Tema claro activado/);
    }
    assert.ok(e.raiz.classList.contains('rw-theme-anim'), 'anima solo durante el cambio');
    assert.equal(e.eventos.at(-1).type, 'redwork:themechange');
    assert.equal(e.eventos.at(-1).detail.theme, 'light');

    e.clic(e.botones[1]);
    assert.equal(e.atributos['data-theme'], 'dark');
    assert.equal(e.almacen.get('redwork-theme'), 'dark');
    e.clic({ closest: () => null });
    assert.equal(e.atributos['data-theme'], 'dark', 'clics fuera del interruptor no cambian el tema');
});

test('themes.js: respeta prefers-reduced-motion y sincroniza otras pestañas', () => {
    const e = entornoTemas({ reducido: true });
    e.T.alternar();
    assert.equal(e.raiz.classList.contains('rw-theme-anim'), false);
    e.storage('redwork-theme', 'dark');
    assert.equal(e.atributos['data-theme'], 'dark');
    e.storage('otra-clave', 'light');
    assert.equal(e.atributos['data-theme'], 'dark');
});

// ── Integración en las cuatro vistas ──

for (const [nombre, ruta] of Object.entries(VISTAS)) {
    test(`vista ${nombre}: tema antes de pintar, interruptor accesible y recursos por Vite`, () => {
        const html = leer(ruta);
        assert.match(html, /<html lang="es" data-theme="dark">/);
        const init = html.indexOf("localStorage.getItem('redwork-theme')");
        assert.ok(init > 0 && init < html.indexOf('@vite('), 'el script de arranque va antes de cargar los estilos');
        assert.match(html, /<button type="button" class="theme-switch" data-theme-toggle role="switch" aria-checked="false"[^>]*>/);
        assert.equal((html.match(/data-theme-toggle/g) || []).length, 1);
        const vite = html.match(/@vite\(\[([^\]]+)\]\)/)[1];
        assert.match(vite, /'resources\/css\/themes\.css', 'resources\/js\/themes\.js'$/, 'themes.css se carga después del CSS del dashboard');
        assert.match(html, /<body class="rw-(admin|contratador|trabajador)">/);
    });
}

test('vite.config.js registra themes.css y themes.js', () => {
    const vite = leer('vite.config.js');
    assert.match(vite, /'resources\/css\/themes\.css'/);
    assert.match(vite, /'resources\/js\/themes\.js'/);
});

// ── themes.css: tema oscuro intacto, tema claro completo ──

test('themes.css: no redefine colores del tema oscuro; todo el tema claro va bajo html[data-theme="light"]', () => {
    assert.doesNotMatch(THEMES_CSS, /data-theme="dark"/);
    assert.doesNotMatch(THEMES_CSS, /^:root/m);
    const reglas = THEMES_CSS.replace(/\/\*[\s\S]*?\*\//g, '').match(/[^{}]+\{[^{}]*\}/g) || [];
    for (const r of reglas) {
        const sel = r.slice(0, r.indexOf('{')).trim();
        const declaraciones = r.slice(r.indexOf('{') + 1);
        if (/(background|color|border|shadow|fill|stroke)\s*:/.test(declaraciones) && !sel.startsWith('@')) {
            const permitido = sel.includes('html[data-theme="light"]') || /theme-switch|rw-theme-anim/.test(sel);
            assert.ok(permitido, `Regla de color fuera del tema claro: ${sel}`);
        }
    }
});

test('themes.css: paleta clara, componentes cubiertos y movimiento reducido', () => {
    for (const [v, c] of [['--bg-main', '--rw-bg'], ['--bg-card', '--rw-surface'], ['--text-main', '--rw-text'], ['--border', '--rw-border']]) {
        assert.match(THEMES_CSS, new RegExp(`${v}: var\\(${c}\\);`));
    }
    for (const color of ['#F7F9FC', '#FFFFFF', '#24364A', '#64748B', '#DFE7EF', '#DCEBFA', '#DDF3E8', '#D9F2EF', '#FBE3E5', '#FFF1D7']) {
        assert.ok(THEMES_CSS.includes(color), `falta ${color}`);
    }
    for (const sel of ['.topbar', '.sidebar', '.nav-item.active', '.dropdown-menu', '.modal-overlay', '.modal', 'tbody tr:nth-child(even)',
        'tbody tr:hover', '.form-input', '.form-select', 'input[type="date"]', '.chip-red', '.btn-danger', '.page-btn', '.kpi-card',
        '.kpi-svg .kpi-eje', '.kpi-svg text', '.kpi-alerta', '.job-card', 'body > div[style*="position: fixed"]']) {
        assert.ok(THEMES_CSS.includes(`html[data-theme="light"] ${sel}`), `el tema claro no cubre ${sel}`);
    }
    assert.match(THEMES_CSS, /prefers-reduced-motion: reduce/);
    assert.match(THEMES_CSS, /transition-property: background-color, color, border-color, box-shadow, fill, stroke !important/);
    assert.doesNotMatch(THEMES_CSS.slice(THEMES_CSS.indexOf('rw-theme-anim')), /transition-property:[^;]*(transform|width|height|top|left)/);
});

test('gráficos KPI: colores de serie como variables CSS (se recolorean sin redibujar)', () => {
    const env = crearEntorno({ source: neutralizarModulo(leer('resources/js/kpi-dashboard.js')), filename: 'kpi-dashboard.js', modulo: false,
        metas: { 'api-token': 'T' }, globales: { URLSearchParams, Intl, setImmediate } });
    const K = env.win.RedWorkKpi;
    const meses = [{ mes: '2026-05', tasa_gestion: 50, postulaciones: 3 }, { mes: '2026-06', tasa_gestion: 70, postulaciones: 2, en_maduracion: true }];
    const html = K.renderTodo({
        alcance: 'trabajador', aviso: 'SIMULADOS', generado_en: '', parametros: { umbral_antiguedad_dias: 7, maduracion_postulaciones_dias: 0, ventana_publicaciones_dias: 14, muestra_minima: 10, confianza: '95 %' },
        periodo: { etiqueta: 'x', desde: '2026-06-01', hasta: '2026-06-30', anterior: { desde: '2026-05-01', hasta: '2026-05-31' } },
        indicadores: [], embudo: { etapas: [] }, evolucion: meses, pagos: null, pendientes: { pagos: null, postulaciones: null },
        calidad: { n: 0 }, contrataciones: { estado_actual: {}, finalizadas_periodo: 0, canceladas_periodo: 0, tasa_cancelacion: null }, alertas: [],
    });
    assert.match(html, /<polyline fill="none" style="stroke:var\(--kpi-s1\)"/);
    assert.match(html, /<circle [^>]*style="fill:var\(--kpi-s1\);stroke:var\(--kpi-s1\)"/);
    assert.match(html, /<circle [^>]*fill="none" style="stroke:var\(--kpi-s1\)"/, 'mes en maduración: punto hueco');
    assert.match(html, /<rect [^>]*style="fill:var\(--kpi-s3\)"/);
    assert.match(html, /<i style="background:var\(--kpi-s2\)">/);
    assert.doesNotMatch(html, /#2DD4BF|#F59E0B|#A78BFA/i, 'sin colores fijos en los gráficos');
});

// ── Parte 1: Calificaciones del contratador ──

test('contratador: sección Calificaciones entre Mis Contrataciones y Mis Indicadores, reutilizando datos y modal', async () => {
    const VIEW = leer(VISTAS.contratador);
    const menu = [...VIEW.matchAll(/data-panel="([^"]+)" onclick="showPanel/g)].map(m => m[1]);
    assert.deepEqual(menu, ['home', 'mis-publicaciones', 'postulaciones', 'contrataciones', 'calificaciones', 'indicadores', 'perfil']);
    assert.match(VIEW, /id="panel-calificaciones"/);

    const contrataciones = [
        { id_contratacion: 1, estado: 'activa', calificaciones: [], trabajador: { nombre: 'Ana' }, publicacion: { titulo: 'A' } },
        { id_contratacion: 2, estado: 'finalizada', fecha_fin: '2026-06-10', calificaciones: [], trabajador: { nombre: 'Luis' }, publicacion: { titulo: 'B' } },
        { id_contratacion: 3, estado: 'finalizada', fecha_fin: '2026-06-11', trabajador: { nombre: 'Eva <b>' }, publicacion: { titulo: 'C' },
          calificaciones: [{ cedula_calificador: 7001, cedula_calificado: 9, puntuacion: 4, comentario: 'Bien' }, { cedula_calificador: 9, cedula_calificado: 7001, puntuacion: 2 }] },
        { id_contratacion: 4, estado: 'cancelada', calificaciones: [], trabajador: { nombre: 'Leo' }, publicacion: { titulo: 'D' } },
    ];
    const env = crearEntorno({
        source: neutralizarModulo(leer('resources/js/contratador-dashboard.js')), filename: 'contratador-dashboard.js',
        metas: { 'api-token': 'T', 'user-cedula': '7001' }, idsValidos: idsDeVista(VIEW),
        rutas: c => (c.url === '/api/contratador/contrataciones' ? { status: 200, data: contrataciones } : undefined),
    });
    await env.asentar();

    assert.equal(env.fetchCalls.filter(c => c.url === '/api/contratador/contrataciones').length, 1, 'una sola consulta compartida con Mis Contrataciones');
    const filas = env.el('tbody-calificaciones').innerHTML.split('<tr>').slice(1);
    assert.equal(filas.length, 2, 'solo contrataciones finalizadas');
    assert.match(filas[0], /onclick="abrirModalCalificar\(2\)">⭐ Calificar<\/button>/, 'reutiliza el modal existente');
    assert.match(filas[0], /Aún no te calificó/);
    assert.doesNotMatch(filas[1], /<button/, 'no se puede calificar dos veces');
    assert.match(filas[1], /4\/5/);
    assert.match(filas[1], /"Bien"/);
    assert.match(filas[1], /2\/5/);
    assert.match(filas[1], /Eva &lt;b&gt;/);
    const resumen = env.el('calif-resumen-contratador').innerHTML;
    assert.match(resumen, /Promedio recibido<\/div><div class="stat-value"[^>]*>2\.0 \/ 5/);
    assert.match(resumen, /Pendientes de calificar<\/div><div class="stat-value"[^>]*>1</);
    // La información de calificaciones sigue en Mis Contrataciones.
    assert.match(env.el('tbody-contrataciones').innerHTML, /Recibida: ⭐ 2\/5/);
});

// ── Parte 2: menú del trabajador ──

test('trabajador: una sola opción «Buscar Trabajos» en el menú; las pestañas internas se conservan', () => {
    const VIEW = leer(VISTAS.trabajador);
    const lateral = VIEW.slice(VIEW.indexOf('<aside class="sidebar"'), VIEW.indexOf('</aside>'));
    assert.equal((lateral.match(/data-panel="buscar"/g) || []).length, 1);
    assert.doesNotMatch(lateral, /data-filtro="(recurrente|temporal)"/, 'sin entradas Temporales/Recurrentes en el menú');
    assert.doesNotMatch(lateral, />\s*(Recurrentes|Temporales)\s*</);
    const pestanas = VIEW.slice(VIEW.indexOf('id="filtros"'), VIEW.indexOf('id="lista-jobs"'));
    for (const f of ['todos', 'recurrente', 'temporal', 'ciudad', 'mejor']) assert.match(pestanas, new RegExp(`data-filtro="${f}"`));
});
