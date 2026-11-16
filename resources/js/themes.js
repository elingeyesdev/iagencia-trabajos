/**
 * RedWork — Tema claro / oscuro compartido por los dashboards (administrador, Business Intelligence,
 * contratador y trabajador).
 *
 * - La preferencia vive en localStorage (`redwork-theme` = light | dark); oscuro por defecto.
 * - Un pequeño script en el <head> de cada vista aplica el tema ANTES de pintar (sin parpadeo);
 *   este módulo sincroniza el interruptor, guarda la preferencia y notifica el cambio.
 * - Los colores salen de variables CSS (resources/css/themes.css): los componentes creados después
 *   de cargar la página (modales, toasts, gráficos SVG) adoptan el tema sin volver a dibujarse.
 * - Interruptor: <button data-theme-toggle role="switch" aria-checked> (marcado = tema claro).
 *   Al ser <button> funciona con ratón, teclado (Enter / Espacio) y pantalla táctil.
 */
(function (global) {
    'use strict';

    const CLAVE = 'redwork-theme';
    const TEMAS = ['light', 'dark'];
    const doc = global.document;

    const normalizar = t => (t === 'light' ? 'light' : 'dark');

    function leer() {
        try { return normalizar(global.localStorage.getItem(CLAVE)); } catch (e) { return 'dark'; }
    }

    function guardar(tema) {
        try { global.localStorage.setItem(CLAVE, tema); } catch (e) { /* almacenamiento no disponible: solo esta sesión */ }
    }

    function actual() {
        return normalizar(doc.documentElement.getAttribute('data-theme'));
    }

    /** Refleja el tema en un interruptor: estado ARIA, texto visible e icono. */
    function sincronizar(boton) {
        const claro = actual() === 'light';
        boton.setAttribute('aria-checked', claro ? 'true' : 'false');
        boton.setAttribute('aria-label', claro ? 'Tema claro activado. Cambiar a tema oscuro' : 'Tema oscuro activado. Cambiar a tema claro');
        boton.setAttribute('title', claro ? 'Cambiar a tema oscuro' : 'Cambiar a tema claro');
        const etiqueta = boton.querySelector('.theme-switch-label');
        if (etiqueta) etiqueta.textContent = claro ? 'Tema claro' : 'Tema oscuro';
        const icono = boton.querySelector('.theme-switch-thumb');
        if (icono) icono.textContent = claro ? '☀️' : '🌙';
    }

    function sincronizarTodos() {
        doc.querySelectorAll('[data-theme-toggle]').forEach(sincronizar);
    }

    /**
     * Aplica un tema. Con `persistir` lo guarda como preferencia; con `animar` suaviza solo colores
     * (nunca tamaños ni posiciones) y respeta prefers-reduced-motion.
     */
    function aplicar(tema, { persistir = false, animar = false } = {}) {
        const t = normalizar(tema);
        const raiz = doc.documentElement;
        const reducido = !!(global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches);

        if (animar && !reducido) {
            raiz.classList.add('rw-theme-anim');
            clearTimeout(aplicar._t);
            aplicar._t = setTimeout(() => raiz.classList.remove('rw-theme-anim'), 350);
        }
        raiz.setAttribute('data-theme', t);
        raiz.style.colorScheme = t;
        if (persistir) guardar(t);
        sincronizarTodos();

        try {
            global.dispatchEvent(new global.CustomEvent('redwork:themechange', { detail: { theme: t } }));
        } catch (e) { /* entornos sin CustomEvent */ }
        return t;
    }

    function alternar() {
        return aplicar(actual() === 'light' ? 'dark' : 'light', { persistir: true, animar: true });
    }

    let iniciado = false;
    function iniciar() {
        if (iniciado) return actual();
        iniciado = true;

        // Delegación: sirve también para interruptores insertados después de cargar la página.
        doc.addEventListener('click', ev => {
            const boton = ev.target && ev.target.closest ? ev.target.closest('[data-theme-toggle]') : null;
            if (!boton) return;
            ev.preventDefault();
            alternar();
        });

        // Otra pestaña cambió la preferencia: aplicarla aquí sin volver a guardarla.
        global.addEventListener('storage', ev => {
            if (ev.key === CLAVE) aplicar(ev.newValue);
        });

        return aplicar(leer());
    }

    global.RedWorkTheme = { CLAVE, TEMAS, leer, guardar, actual, aplicar, alternar, sincronizar, iniciar };

    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(typeof window !== 'undefined' ? window : globalThis);
