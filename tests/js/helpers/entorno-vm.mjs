/**
 * Bloque 6 — Utilidades compartidas para ejecutar los scripts reales de los dashboards en node:vm
 * con un DOM y un fetch simulados mínimos (sin dependencias npm). Mismo enfoque que
 * tests/js/admin-usuario-modal.test.mjs. No es un archivo de prueba (no termina en .test.mjs).
 */
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', '..');
export const leer = rel => readFileSync(path.join(ROOT, ...rel.split('/')), 'utf8');

/** Neutraliza sintaxis de módulo ES (defensivo; hoy los archivos no la usan). */
export function neutralizarModulo(src) {
    return src
        .replace(/^\s*import\s+[^;]*?from\s+['"][^'"]+['"]\s*;?\s*$/gm, '')
        .replace(/^\s*import\s+['"][^'"]+['"]\s*;?\s*$/gm, '')
        .replace(/^(\s*)export\s+default\s+/gm, '$1')
        .replace(/^(\s*)export\s+(?=(async\s+)?function|const|let|var|class)/gm, '$1');
}

export const idsDeVista = html => new Set([...html.matchAll(/\bid="([^"]+)"/g)].map(m => m[1]));

export class FakeClassList {
    constructor(initial = []) { this.set = new Set(initial); }
    add(...c) { c.forEach(x => this.set.add(x)); }
    remove(...c) { c.forEach(x => this.set.delete(x)); }
    contains(c) { return this.set.has(c); }
    toggle(c, force) {
        const on = force === undefined ? !this.set.has(c) : !!force;
        if (on) this.set.add(c); else this.set.delete(c);
        return on;
    }
}

export class FakeElement {
    constructor(id, { tag = 'div', type = '', options = null, value = '', dataset = {} } = {}) {
        this.id = id;
        this.tagName = tag.toUpperCase();
        this.type = type;
        this._options = options;
        this._value = '';
        this._default = value;
        this.value = value;
        this.textContent = '';
        this.innerHTML = '';
        this.disabled = false;
        this.dataset = { ...dataset };
        this.style = { display: '', cssText: '' };
        this.classList = new FakeClassList();
        this.children = [];
    }
    get value() { return this._value; }
    set value(v) {
        const s = v === null || v === undefined ? '' : String(v);
        if (this._options) this._value = this._options.includes(s) ? s : '';
        else this._value = s;
    }
    appendChild(c) { this.children.push(c); return c; }
    remove() {}
    contains() { return false; }
    closest() { return null; }
    querySelectorAll() { return []; }
    addEventListener() {}
}

/**
 * Crea un contexto vm, ejecuta `source` y devuelve utilidades de inspección.
 * @param {object} o
 * @param {string} o.source      código a ejecutar
 * @param {string} o.filename
 * @param {boolean} [o.modulo]   envolver en función (ámbito de módulo, como Vite type="module")
 * @param {object} [o.metas]     name → content para document.querySelector('meta[name="x"]')
 * @param {Map}    [o.elementos] elementos precreados (id → FakeElement)
 * @param {Function} [o.rutas]   (call) => {status, data} | {lanzar: Error} | undefined (→ 200 [])
 * @param {Function} [o.querySelector]    selectores adicionales
 * @param {Function} [o.querySelectorAll] selectores adicionales
 * @param {Set}    [o.idsValidos] si se indica, getElementById devuelve null para ids ausentes (como el navegador)
 * @param {object} [o.globales]   globales adicionales del sandbox (p. ej. history)
 */
export function crearEntorno({ source, filename, modulo = true, metas = {}, elementos = new Map(), rutas = () => undefined,
    querySelector = () => undefined, querySelectorAll = () => undefined, idsValidos = null, globales = {} }) {
    const idsConsultados = [];
    const fetchCalls = [];
    const timers = [];
    const reloads = [];
    const logs = [];
    const body = new FakeElement('body');

    const document = {
        body,
        getElementById(id) {
            idsConsultados.push(id);
            if (!elementos.has(id)) {
                if (idsValidos && !idsValidos.has(id)) return null;
                elementos.set(id, new FakeElement(id));
            }
            return elementos.get(id);
        },
        querySelector(sel) {
            const m = sel.match(/^meta\[name="([^"]+)"\]$/);
            if (m) return Object.hasOwn(metas, m[1]) ? { content: metas[m[1]] } : null;
            return querySelector(sel) ?? null;
        },
        querySelectorAll(sel) { return querySelectorAll(sel) ?? []; },
        createElement(tag) { return new FakeElement(null, { tag }); },
        addEventListener() {},
    };

    async function fetch(url, opts = {}) {
        const method = (opts.method || 'GET').toUpperCase();
        const call = { url, method, headers: opts.headers || {}, body: opts.body ? JSON.parse(opts.body) : undefined };
        fetchCalls.push(call);
        const r = rutas(call);
        if (r && r.lanzar) throw r.lanzar;
        const status = r?.status ?? 200;
        const data = r && Object.hasOwn(r, 'data') ? r.data : [];
        return { ok: status >= 200 && status < 300, status, json: async () => data };
    }

    const sandbox = {
        document,
        fetch,
        console: { log: (...a) => logs.push(a), error: (...a) => logs.push(a), warn: (...a) => logs.push(a) },
        setTimeout: (fn, ms) => { timers.push({ fn, ms }); return timers.length; },
        location: { reload: () => reloads.push(true), hash: '' },
        Promise, JSON, Object, Array, String, Number, Set, Map, Date, Math, encodeURIComponent,
        ...globales,
    };
    sandbox.window = sandbox;
    vm.createContext(sandbox);
    const code = modulo ? '(function () {\n' + source + '\n})();' : source;
    vm.runInContext(code, sandbox, { filename });

    return {
        win: sandbox,
        el: id => elementos.get(id),
        elementos,
        fetchCalls,
        timers,
        reloads,
        logs,
        idsConsultados,
        escrituras: () => fetchCalls.filter(c => c.method !== 'GET'),
        ultimoToast: () => body.children.at(-1)?.textContent ?? '',
        asentar: async () => { for (let i = 0; i < 5; i++) await new Promise(r => setImmediate(r)); },
    };
}
