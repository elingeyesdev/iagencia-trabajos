/**
 * Tarea 1.7.1 — Pruebas que EJECUTAN la lógica real de resources/js/admin-dashboard.js
 * para el modal de usuarios (abrirModalUsuario / editarUsuario / closeModal / guardarUsuario).
 *
 * Ejecución:  node --test "tests/js/*.test.mjs"
 *   (en Node 22, `node --test tests/js/` falla: los argumentos se tratan como archivos/patrones,
 *    no como directorios).
 * Requisitos: solo Node >= 18 (node:test, node:assert, node:vm). Sin dependencias npm.
 *
 * Técnica:
 *  - Se lee el archivo fuente tal cual y se neutralizan las sentencias de módulo ES
 *    (`import ... from '...'` se elimina; `export default` / `export ` se quitan dejando
 *    la declaración). Hoy el archivo no contiene ninguna, la neutralización es defensiva.
 *  - Se ejecuta con vm.runInContext, envuelto en una función (ámbito de módulo, como hace Vite con
 *    type="module"), en un contexto nuevo por prueba, cuyo objeto global
 *    actúa también como `window` (igual que en el navegador, donde las asignaciones
 *    `window.x = x` publican las funciones).
 *  - DOM simulado mínimo: getElementById devuelve elementos fake con value (coaccionado a
 *    string, y para <select> restringido a sus opciones como en el navegador), textContent,
 *    innerHTML, style.display y classList. Los ids del modal se crean a partir de la vista
 *    Blade (estado inicial incluido); otros ids (tablas que rellenan las cargas iniciales)
 *    se crean bajo demanda para que la inicialización no falle.
 *  - fetch simulado: registra url/method/headers/body; GET devuelve [] (o roles para
 *    /api/admin/roles); POST/PUT devuelven 200 o 422 según la variante configurada.
 *  - setTimeout simulado: encola callbacks (no hay esperas reales); location.reload registrado.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const JS_PATH = path.join(ROOT, 'resources', 'js', 'admin-dashboard.js');
const VIEW_PATH = path.join(ROOT, 'resources', 'views', 'admin', 'dashboard.blade.php');

const RAW_SOURCE = readFileSync(JS_PATH, 'utf8');
const VIEW = readFileSync(VIEW_PATH, 'utf8');

/** Neutraliza sintaxis de módulo ES para poder ejecutar el archivo como script en vm. */
function neutralizarModulo(src) {
    return src
        .replace(/^\s*import\s+[^;]*?from\s+['"][^'"]+['"]\s*;?\s*$/gm, '')
        .replace(/^\s*import\s+['"][^'"]+['"]\s*;?\s*$/gm, '')
        .replace(/^(\s*)export\s+default\s+/gm, '$1')
        .replace(/^(\s*)export\s+(?=(async\s+)?function|const|let|var|class)/gm, '$1');
}
const SOURCE = neutralizarModulo(RAW_SOURCE);

// Bloque del modal de usuario en la vista (para validar ids y estado inicial).
const MODAL_HTML = (() => {
    const ini = VIEW.indexOf('id="modal-usuario"');
    const fin = VIEW.indexOf('id="modal-categoria"');
    assert.ok(ini > 0 && fin > ini, 'No se encontró el bloque modal-usuario en la vista');
    return VIEW.slice(ini, fin);
})();
const IDS_EN_VISTA = new Set([...VIEW.matchAll(/\bid="([^"]+)"/g)].map(m => m[1]));
const TITULO_INICIAL = MODAL_HTML.match(/id="modal-usuario-title"[^>]*>([^<]*)</)[1].trim();

const ROLES = [{ name: 'administrador' }, { name: 'contratador' }, { name: 'trabajador' }];

class FakeClassList {
    constructor(initial = []) { this.set = new Set(initial); }
    add(...c) { c.forEach(x => this.set.add(x)); }
    remove(...c) { c.forEach(x => this.set.delete(x)); }
    contains(c) { return this.set.has(c); }
    toggle(c) { if (this.set.has(c)) { this.set.delete(c); return false; } this.set.add(c); return true; }
}

class FakeElement {
    constructor(id, { tag = 'div', options = null, classes = [] } = {}) {
        this.id = id;
        this.tagName = tag.toUpperCase();
        this._value = '';
        this._options = options; // solo <select>
        this.textContent = '';
        this.style = { display: '', cssText: '' };
        this.classList = new FakeClassList(classes);
        this.children = [];
        this._innerHTML = '';
        if (options) this._value = options[0] ?? '';
    }
    get value() { return this._value; }
    set value(v) {
        const s = v === null || v === undefined ? '' : String(v);
        // Igual que el navegador: un <select> sin opción coincidente queda en ''.
        if (this._options) this._value = this._options.includes(s) ? s : '';
        else this._value = s;
    }
    get innerHTML() { return this._innerHTML; }
    set innerHTML(html) {
        this._innerHTML = String(html);
        if (this._options) {
            this._options = [...this._innerHTML.matchAll(/<option value="([^"]*)"/g)].map(m => m[1]);
            if (!this._options.includes(this._value)) this._value = this._options[0] ?? '';
        }
    }
    appendChild(c) { this.children.push(c); return c; }
    remove() {}
    contains() { return false; }
    querySelectorAll() { return []; }
    addEventListener() {}
}

function crearEntorno({ respuestaGuardado = 'ok' } = {}) {
    const elementos = new Map();
    const idsConsultados = [];
    const fetchCalls = [];
    const timers = [];
    const reloads = [];
    const logs = [];

    // Elementos del modal según la vista.
    const def = (id, opts) => elementos.set(id, new FakeElement(id, opts));
    def('modal-usuario', { classes: ['modal-overlay'] });
    def('modal-usuario-title');
    elementos.get('modal-usuario-title').textContent = TITULO_INICIAL;
    for (const id of ['usuario-id', 'f-nombre', 'f-cedula', 'f-email', 'f-ciudad', 'f-password', 'f-fechanac']) {
        def(id, { tag: 'input' });
    }
    def('f-estado', { tag: 'select', options: [...MODAL_HTML.matchAll(/id="f-estado">(.*?)<\/select>/g)]
        .flatMap(m => [...m[1].matchAll(/<option value="([^"]*)"/g)].map(o => o[1])) });
    def('f-rol', { tag: 'select', options: [] }); // se rellena con cargarTodosRoles()
    def('grupo-password');
    def('grupo-fechanac');

    const document = {
        body: new FakeElement('body'),
        querySelector(sel) {
            if (sel === 'meta[name="api-token"]') return { content: 'TOKEN-PRUEBA' };
            return null;
        },
        querySelectorAll() { return []; },
        getElementById(id) {
            idsConsultados.push(id);
            if (!elementos.has(id)) elementos.set(id, new FakeElement(id));
            return elementos.get(id);
        },
        createElement(tag) { return new FakeElement(null, { tag }); },
        addEventListener() {},
    };

    async function fetch(url, opts = {}) {
        const method = (opts.method || 'GET').toUpperCase();
        const call = { url, method, headers: opts.headers || {}, body: opts.body ? JSON.parse(opts.body) : undefined };
        fetchCalls.push(call);
        let status = 200;
        let data = [];
        if (method === 'GET') {
            data = url === '/api/admin/roles' ? ROLES : [];
        } else if (respuestaGuardado === '422') {
            status = 422;
            data = { message: 'Datos inválidos.', errors: { fechanac: ['Debe ser mayor de 18 años.'] } };
        } else {
            data = { message: 'Guardado correctamente.' };
        }
        return { ok: status >= 200 && status < 300, status, json: async () => data };
    }

    const sandbox = {
        document,
        fetch,
        console: { log: (...a) => logs.push(a), error: (...a) => logs.push(a), warn: (...a) => logs.push(a) },
        setTimeout: (fn, ms) => { timers.push({ fn, ms }); return timers.length; },
        location: { reload: () => reloads.push(true) },
        Promise, JSON, Object, Array, String, Number, Set, Map,
    };
    sandbox.window = sandbox;
    vm.createContext(sandbox);
    // Vite inyecta el archivo como <script type="module">: sus declaraciones de nivel superior NO son
    // globales. Se envuelve en una función para reproducir ese ámbito; solo lo asignado a window.* es visible.
    vm.runInContext('(function () {\n' + SOURCE + '\n})();', sandbox, { filename: 'admin-dashboard.js' });

    return {
        win: sandbox,
        el: id => elementos.get(id),
        fetchCalls,
        timers,
        reloads,
        logs,
        idsConsultados,
        /** Llamadas de escritura (no GET de cargas iniciales). */
        escrituras: () => fetchCalls.filter(c => c.method !== 'GET'),
        /** Espera a que terminen las cargas iniciales (microtareas). */
        asentar: () => new Promise(r => setImmediate(r)),
        flushTimers: () => { while (timers.length) timers.shift().fn(); },
    };
}

function rellenar(env, datos) {
    for (const [id, v] of Object.entries(datos)) env.el(id).value = v;
}

function assertModalNuevoLimpio(env) {
    assert.equal(env.el('modal-usuario-title').textContent, 'Nuevo Usuario');
    for (const id of ['usuario-id', 'f-nombre', 'f-email', 'f-cedula', 'f-ciudad', 'f-password', 'f-fechanac']) {
        assert.equal(env.el(id).value, '', `${id} debería estar vacío`);
    }
    assert.equal(env.el('f-estado').value, '1');
    assert.equal(env.el('f-rol').value, '');
    assert.equal(env.el('grupo-password').style.display, '');
    assert.equal(env.el('grupo-fechanac').style.display, '');
    assert.ok(env.el('modal-usuario').classList.contains('open'), 'el modal debería estar abierto');
}

const DATOS_NUEVO = {
    'f-nombre': 'Nueva Persona',
    'f-email': 'nueva@example.com',
    'f-cedula': '777000111',
    'f-ciudad': 'Quito',
    'f-estado': '1',
    'f-rol': 'trabajador',
    'f-password': 'Segura#Clave2026',
    'f-fechanac': '1990-05-10',
};

// ── Precondiciones ──

test('carga: el archivo no tiene import/export y se ejecuta sin errores; expone las funciones en window', async () => {
    assert.equal(/^\s*(import|export)\s/m.test(RAW_SOURCE), false, 'Si aparece sintaxis de módulo, revisar neutralizarModulo()');
    const env = crearEntorno();
    await env.asentar();
    for (const fn of ['abrirModalUsuario', 'editarUsuario', 'guardarUsuario', 'openModal', 'closeModal']) {
        assert.equal(typeof env.win[fn], 'function', `window.${fn} no está expuesta`);
    }
    // Las cargas iniciales capturan sus errores; no debe haberse registrado ninguno.
    assert.deepEqual(env.logs, []);
    assert.deepEqual(env.escrituras(), []);
});

test('la vista: el botón «Guardar +» llama a abrirModalUsuario() y no a openModal directo', () => {
    assert.match(VIEW, /onclick="abrirModalUsuario\(\)"[^>]*>\s*Guardar \+\s*</);
    assert.doesNotMatch(VIEW, /onclick="openModal\('modal-usuario'\)"/);
    assert.equal(TITULO_INICIAL, 'Nuevo Usuario');
});

test('todos los ids que usan las funciones del modal existen en la vista Blade', async () => {
    const env = crearEntorno();
    await env.asentar();
    env.idsConsultados.length = 0;
    env.win.editarUsuario(1, 'A', 'a@x.com', 'Quito', 1, 'trabajador');
    env.win.closeModal('modal-usuario');
    env.win.abrirModalUsuario();
    await env.win.guardarUsuario();
    const faltantes = [...new Set(env.idsConsultados)].filter(id => !IDS_EN_VISTA.has(id));
    assert.deepEqual(faltantes, [], `ids usados por el JS que no existen en la vista: ${faltantes}`);
});

// ── (a) ──
test('(a) abrir «Nuevo Usuario» directamente deja el modal en estado de creación', async () => {
    const env = crearEntorno();
    await env.asentar();
    env.win.abrirModalUsuario();
    assertModalNuevoLimpio(env);
    assert.deepEqual(env.escrituras(), [], 'abrir el modal no debe hacer peticiones');
});

// ── (b) ──
test('(b) editar → cancelar → nuevo → guardar: POST limpio con fechanac y sin el id anterior', async () => {
    const env = crearEntorno();
    await env.asentar();
    env.win.editarUsuario(123456789, 'Ana Previa', 'ana@example.com', 'Cuenca', 0, 'contratador');
    assert.equal(env.el('usuario-id').value, '123456789');
    env.win.closeModal('modal-usuario');
    assert.equal(env.el('modal-usuario').classList.contains('open'), false);

    env.win.abrirModalUsuario();
    assertModalNuevoLimpio(env);

    rellenar(env, DATOS_NUEVO);
    await env.win.guardarUsuario();

    const w = env.escrituras();
    assert.equal(w.length, 1);
    assert.equal(w[0].method, 'POST');
    assert.equal(w[0].url, '/api/admin/usuarios');
    assert.equal(w[0].headers.Authorization, 'Bearer TOKEN-PRUEBA');
    assert.equal(w[0].body.fechanac, '1990-05-10');
    assert.equal(w[0].body.cedula, '777000111');
    assert.equal(w[0].body.nombre, 'Nueva Persona');
    assert.ok(!JSON.stringify(w[0].body).includes('123456789'), 'el body no debe arrastrar la cédula editada antes');
    assert.ok(!JSON.stringify(w[0].body).includes('ana@example.com'));
    // Respuesta OK: cierra el modal y programa la recarga.
    assert.equal(env.el('modal-usuario').classList.contains('open'), false);
    env.flushTimers();
    assert.equal(env.reloads.length, 1);
});

// ── (c) ──
test('(c) crear: rellenar, cerrar sin guardar y reabrir deja el formulario vacío', async () => {
    const env = crearEntorno();
    await env.asentar();
    env.win.abrirModalUsuario();
    rellenar(env, DATOS_NUEVO);
    env.win.closeModal('modal-usuario');
    env.win.abrirModalUsuario();
    assertModalNuevoLimpio(env);
    assert.deepEqual(env.escrituras(), []);
});

// ── (d) ──
test('(d) editar → guardar: PUT a /api/admin/usuarios/{id} sin fechanac', async () => {
    const env = crearEntorno();
    await env.asentar();
    env.win.editarUsuario(987654321, 'Beto', 'beto@example.com', '—', 1, 'trabajador');
    assert.equal(env.el('modal-usuario-title').textContent, 'Editar Usuario');
    assert.equal(env.el('f-ciudad').value, '', "la ciudad '—' se convierte en vacío");
    env.el('f-fechanac').value = '2000-01-01'; // aunque alguien la rellenara, no debe enviarse
    await env.win.guardarUsuario();

    const w = env.escrituras();
    assert.equal(w.length, 1);
    assert.equal(w[0].method, 'PUT');
    assert.equal(w[0].url, '/api/admin/usuarios/987654321');
    assert.equal(Object.hasOwn(w[0].body, 'fechanac'), false, 'PUT no debe incluir fechanac');
    assert.equal(w[0].body.nombre, 'Beto');
    assert.equal(w[0].body.estado, '1');
    assert.equal(w[0].body.rol, 'trabajador');
});

// ── (e) ──
test('(e) visibilidad: editar oculta password/fechanac; nuevo los vuelve a mostrar', async () => {
    const env = crearEntorno();
    await env.asentar();
    env.win.editarUsuario(5, 'C', 'c@x.com', 'Loja', 1, '');
    assert.equal(env.el('grupo-password').style.display, 'none');
    assert.equal(env.el('grupo-fechanac').style.display, 'none');
    assert.equal(env.el('f-fechanac').value, '');
    env.win.closeModal('modal-usuario');
    env.win.abrirModalUsuario();
    assert.equal(env.el('grupo-password').style.display, '');
    assert.equal(env.el('grupo-fechanac').style.display, '');
    env.win.editarUsuario(6, 'D', 'd@x.com', 'Loja', 1, '');
    assert.equal(env.el('grupo-password').style.display, 'none');
    assert.equal(env.el('grupo-fechanac').style.display, 'none');
});

// ── (f) ──
test('(f) editar A → nuevo → guardar no genera ningún PUT a A', async () => {
    const env = crearEntorno();
    await env.asentar();
    const A = 111222333;
    env.win.editarUsuario(A, 'Usuario A', 'a@example.com', 'Ambato', 1, 'administrador');
    env.win.abrirModalUsuario();
    rellenar(env, DATOS_NUEVO);
    await env.win.guardarUsuario();

    const w = env.escrituras();
    assert.equal(w.filter(c => c.method === 'PUT').length, 0, 'no debe haber PUT');
    assert.equal(w.filter(c => c.url.includes(String(A))).length, 0, `ninguna URL debe contener ${A}`);
    assert.equal(w.length, 1);
    assert.equal(w[0].method, 'POST');
});

// ── Variante 422 ──
test('variante 422 en creación: el modal sigue abierto, conserva los datos, muestra el error y no recarga', async () => {
    const env = crearEntorno({ respuestaGuardado: '422' });
    await env.asentar();
    env.win.abrirModalUsuario();
    rellenar(env, DATOS_NUEVO);
    await env.win.guardarUsuario();

    const w = env.escrituras();
    assert.equal(w.length, 1);
    assert.equal(w[0].method, 'POST');
    assert.ok(env.el('modal-usuario').classList.contains('open'), 'el modal debe seguir abierto');
    assert.equal(env.el('f-fechanac').value, '1990-05-10', 'los datos se conservan para corregir');
    const toast = env.win.document.body.children.at(-1);
    assert.match(toast.textContent, /Debe ser mayor de 18 años\./);
    env.flushTimers();
    assert.equal(env.reloads.length, 0, 'no debe recargar tras un 422');
});
