/**
 * Sistema de KPIs — ejecuta la lógica REAL de resources/js/kpi-dashboard.js (presentación compartida
 * por los tres dashboards): tarjetas, colores con significado, estados vacíos, gráficos sin valores
 * inventados, carga desde la API (Bearer, filtros, 422/403/500/red) e integración en las vistas.
 * Ejecución: node --test "tests/js/*.test.mjs"
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { leer, neutralizarModulo, crearEntorno, FakeElement } from './helpers/entorno-vm.mjs';

const SOURCE = neutralizarModulo(leer('resources/js/kpi-dashboard.js'));

function cargarModulo(opts = {}) {
    const env = crearEntorno({
        source: SOURCE, filename: 'kpi-dashboard.js', modulo: false,
        metas: { 'api-token': 'TOKEN-PRUEBA' },
        globales: { URLSearchParams, Intl, setImmediate },
        ...opts,
    });
    return { env, K: env.win.RedWorkKpi };
}

function indicador(cambios = {}) {
    return {
        clave: 'conversion_contratacion', nombre: 'Conversión a contratación', objetivo: 'C',
        pregunta: '¿Qué proporción…?', unidad: '%', sentido: 'mayor_mejor',
        valor: 60, numerador: 6, denominador: 10,
        cohorte: { desde: '2026-06-01', hasta: '2026-06-23', en_maduracion: true },
        anterior: { valor: 50, numerador: 5, denominador: 10 },
        variacion: { puntos: 10, evaluacion: 'sin_cambio_significativo' },
        interpretacion: '6 de 10 postulaciones con decisión generaron una contratación.',
        ...cambios,
    };
}

function mes(m, cambios = {}) {
    return { mes: m, parcial: false, en_maduracion: false, postulaciones: 0, evaluadas: 0, contratadas: 0,
        contrataciones_iniciadas: 0, finalizadas: 0, canceladas: 0, pagos_registrados: 0, pagos_confirmados: 0,
        calificaciones: 0, tasa_gestion: null, tasa_conversion: null, tasa_finalizacion: null, promedio_calificacion: null, ...cambios };
}

function respuesta(alcance = 'global', cambios = {}) {
    return {
        simulado: true, aviso: 'Los importes corresponden a registros de pagos SIMULADOS.', generado_en: '2026-06-30T12:00:00-04:00',
        alcance,
        periodo: { clave: '30d', etiqueta: 'Últimos 30 días', desde: '2026-06-01', hasta: '2026-06-30', dias: 30, parcial: false,
            anterior: { desde: '2026-05-02', hasta: '2026-05-31', nota: '' } },
        filtros: { categoria: null },
        parametros: { maduracion_postulaciones_dias: 7, ventana_publicaciones_dias: 14, umbral_antiguedad_dias: 7, muestra_minima: 10, confianza: '95 %' },
        indicadores: [indicador()],
        embudo: { cohorte_hasta: '2026-06-23', en_maduracion: true, etapas: [
            { clave: 'recibidas', etiqueta: 'Postulaciones evaluables', valor: 12, porcentaje: 100 },
            { clave: 'evaluadas', etiqueta: 'Con decisión', valor: 10, porcentaje: 83.3 },
            { clave: 'con_contratacion', etiqueta: 'Con contratación', valor: 6, porcentaje: 50 },
            { clave: 'finalizadas', etiqueta: 'Contratación finalizada', valor: 2, porcentaje: 16.7 }] },
        pendientes: {
            postulaciones: alcance === 'trabajador' ? null : { cantidad: 2, mediana_dias: 9, max_dias: 12, sobre_umbral: 1,
                mas_antiguas: alcance === 'contratador' ? [{ id_postulacion: 4, titulo: 'Pintor <b>', fecha: '2026-06-18', dias: 12 }] : null },
            pagos: { simulado: true, cantidad: 1, monto_bs: '30.00', mediana_dias: 15, max_dias: 15, sobre_umbral: 1 },
        },
        pagos: { simulado: true, registrados: 4, confirmado: { cantidad: 2, monto_bs: '150.50' }, pendiente: { cantidad: 1, monto_bs: '30.00' },
            anulado: { cantidad: 1, monto_bs: '10.00' }, tasa_anulacion: 25, tiempo_confirmacion_horas: { mediana: 18, n: 2, anterior: null },
            anterior: { registrados: 0, confirmado: { cantidad: 0, monto_bs: '0.00' }, tasa_anulacion: null } },
        contrataciones: { finalizadas_periodo: 3, canceladas_periodo: 1, tasa_cancelacion: 25, estado_actual: { activa: 1, finalizada: 4, cancelada: 1 } },
        calidad: { promedio: 3.5, n: 4, distribucion: { 1: 0, 2: 1, 3: 1, 4: 1, 5: 1 },
            por_rol: alcance === 'global' ? { trabajador: { promedio: 4, n: 3 }, contratador: { promedio: 2, n: 1 } } : null,
            cobertura: alcance === 'global' ? { finalizadas: 3, calificadas: 3, porcentaje: 100 } : null },
        evolucion: [mes('2026-04', { postulaciones: 2, tasa_conversion: 50 }), mes('2026-05'), mes('2026-06', { postulaciones: 1, tasa_conversion: 40, parcial: true, en_maduracion: true })],
        calidad_datos: { aceptadas_sin_contratacion: 0, cerradas_sin_fecha_fin: 0 },
        categorias: alcance === 'global' ? [{ id_categoria: 1, nombre: 'Limpieza', publicaciones: 3, efectividad: null, postulaciones: 2, evaluadas: 2, conversion: 50, cerradas: 0, finalizacion: null }] : undefined,
        alertas: [{ nivel: 'media', indicador: 'gestion_postulaciones', mensaje: '1 postulación(es) llevan más de 7 días sin decisión.' }],
        ...cambios,
    };
}

// ═══════════════ Tarjetas ═══════════════

test('tarjeta: muestra valor, casos, cohorte, período anterior y evaluación neutra', () => {
    const { K } = cargarModulo();
    const html = K.renderTarjeta(indicador());
    assert.match(html, /data-kpi="conversion_contratacion"/);
    assert.match(html, /Obj\. C/);
    assert.ok(html.includes(K.porcentaje(60)), 'valor formateado');
    assert.match(html, /6 de 10 casos/);
    assert.match(html, /Cohorte 01\/06\/2026 – 23\/06\/2026/);
    assert.match(html, /Anterior: 50 % \(n = 10\)/);
    assert.match(html, /kpi-comp kpi-neu/);
    assert.match(html, /Variación no significativa/);
    assert.match(html, /aún no maduran/);
});

test('tarjeta: denominador cero muestra «Sin datos suficientes», nunca 0 % ni 100 %', () => {
    const { K } = cargarModulo();
    const html = K.renderTarjeta(indicador({
        valor: null, numerador: 0, denominador: 0, anterior: { valor: null, numerador: 0, denominador: 0 },
        variacion: { puntos: null, evaluacion: 'insuficiente' }, interpretacion: 'Sin datos suficientes en el período evaluado.',
    }));
    assert.match(html, /kpi-valor kpi-vacio">Sin datos suficientes</);
    assert.doesNotMatch(html, /\b0 %|100 %/);
    assert.doesNotMatch(html, /kpi-badge/);
    assert.match(html, /Muestra insuficiente para comparar/);
});

test('tarjeta: el color depende de la evaluación de la API, no del signo (una subida puede ser deterioro)', () => {
    const { K } = cargarModulo();
    const sube = K.renderTarjeta(indicador({ variacion: { puntos: 12.5, evaluacion: 'deterioro' } }));
    assert.match(sube, /kpi-comp kpi-neg/);
    assert.match(sube, /▲ \+12,5 pp/);
    const baja = K.renderTarjeta(indicador({ variacion: { puntos: -8, evaluacion: 'mejora' } }));
    assert.match(baja, /kpi-comp kpi-pos/);
    assert.match(baja, /▼ -8,0 pp/);
});

test('tarjeta de calificación: promedio sobre 5 con número de calificaciones', () => {
    const { K } = cargarModulo();
    const html = K.renderTarjeta(indicador({ clave: 'calidad_calificaciones', objetivo: 'F', unidad: 'puntos (1–5)', valor: 3.5, numerador: null, denominador: 4,
        anterior: { valor: null, numerador: null, denominador: 0 }, variacion: { puntos: null, evaluacion: 'insuficiente' } }));
    assert.match(html, /3,50 \/ 5/);
    assert.match(html, /n = 4 calificación\(es\)/);
});

test('tarjetas y alertas escapan HTML', () => {
    const { K } = cargarModulo();
    const html = K.renderTarjeta(indicador({ nombre: '<script>x</script>', interpretacion: '<img onerror=1>' }))
        + K.renderAlertas([{ nivel: 'alta', mensaje: '<b>x</b>' }]);
    assert.doesNotMatch(html, /<script>|<img|<b>x/);
    assert.match(html, /&lt;script&gt;/);
    assert.equal(K.renderAlertas([]), '');
});

// ═══════════════ Gráficos ═══════════════

test('líneas: un mes sin datos corta la línea (no interpola ni rellena con 0)', () => {
    const { K } = cargarModulo();
    const meses = [mes('2026-01', { tasa_conversion: 50 }), mes('2026-02', { tasa_conversion: 60 }), mes('2026-03'), mes('2026-04', { tasa_conversion: 40 }), mes('2026-05', { tasa_conversion: 45 })];
    const html = K.renderLineas(meses, [{ clave: 'tasa_conversion', etiqueta: 'Conversión', color: '#2DD4BF' }]);
    assert.equal((html.match(/<polyline/g) || []).length, 2, 'dos tramos separados por el mes sin datos');
    assert.equal((html.match(/<circle/g) || []).length, 4, 'sin punto para el mes sin datos');
    assert.match(html, /<td class="kpi-num kpi-sd">s\/d<\/td>/);
});

test('líneas: meses en maduración se dibujan huecos; sin histórico → estado vacío', () => {
    const { K } = cargarModulo();
    const html = K.renderLineas([mes('2026-06', { tasa_gestion: 20, en_maduracion: true })], [{ clave: 'tasa_gestion', etiqueta: 'Gestión', color: '#fff' }]);
    assert.match(html, /fill="none"/);
    assert.match(html, /en maduración/);
    const vacio = K.renderLineas([mes('2026-05'), mes('2026-06')], [{ clave: 'tasa_gestion', etiqueta: 'Gestión', color: '#fff' }]);
    assert.match(vacio, /kpi-estado/);
    assert.doesNotMatch(vacio, /<svg/);
});

test('barras: sin actividad → estado vacío; con datos dibuja solo valores > 0', () => {
    const { K } = cargarModulo();
    const series = [{ clave: 'postulaciones', etiqueta: 'Postulaciones', color: '#a' }, { clave: 'finalizadas', etiqueta: 'Finalizadas', color: '#b' }];
    assert.match(K.renderBarras([mes('2026-05'), mes('2026-06')], series), /Sin actividad/);
    const html = K.renderBarras([mes('2026-05', { postulaciones: 3 }), mes('2026-06', { postulaciones: 1, finalizadas: 2, parcial: true })], series);
    assert.equal((html.match(/<rect/g) || []).length, 3);
    assert.match(html, /fill-opacity=".55"/);
});

test('embudo: anchos proporcionales y estado vacío sin cohorte', () => {
    const { K } = cargarModulo();
    const html = K.renderEmbudo(respuesta().embudo);
    assert.match(html, /width:100%/);
    assert.match(html, /width:16\.7%/);
    assert.match(html, /denominador común/);
    assert.match(K.renderEmbudo({ etapas: [{ valor: 0, porcentaje: null }] }), /Sin postulaciones evaluables/);
});

test('pagos: siempre identificados como simulados; categorías con «Sin datos suficientes»', () => {
    const { K } = cargarModulo();
    const r = respuesta();
    const pagos = K.renderPagos(r.pagos, r.pendientes.pagos, 7, 'global');
    assert.match(pagos, /SIMULADOS/);
    assert.match(pagos, /Bs 150,50/);
    assert.match(pagos, /Más de 7 días/);
    assert.match(pagos, /kpi-atencion/);
    assert.match(K.renderCategorias(r.categorias), /kpi-sd">Sin datos suficientes/);
});

// ═══════════════ Composición por rol ═══════════════

test('composición: admin incluye categorías y calidad por rol; contratador sus pendientes; trabajador sin datos de gestión ajenos', () => {
    const { K } = cargarModulo();
    const admin = K.renderTodo(respuesta('global'));
    assert.match(admin, /Resultados por categoría laboral/);
    assert.match(admin, /Trabajadores evaluados/);
    assert.match(admin, /SIMULADOS/);
    assert.match(admin, /Comparado con: <strong>02\/05\/2026 – 31\/05\/2026/);

    const contr = K.renderTodo(respuesta('contratador'));
    assert.doesNotMatch(contr, /Resultados por categoría/);
    assert.match(contr, /Postulaciones pendientes de decisión/);
    assert.match(contr, /Pintor &lt;b&gt;/);

    const trab = K.renderTodo(respuesta('trabajador'));
    assert.doesNotMatch(trab, /Postulaciones pendientes de decisión|Resultados por categoría|Trabajadores evaluados/);
    assert.match(trab, /Estado de mis contrataciones/);
    assert.match(trab, /Mis pagos simulados/);
});

// ═══════════════ Carga desde la API ═══════════════

test('construirUrl: período por defecto, intervalo personalizado y categoría', () => {
    const { K } = cargarModulo();
    assert.equal(K.construirUrl('/api/x', {}), '/api/x?periodo=30d');
    assert.equal(K.construirUrl('/api/x', { periodo: '90d', desde: '2026-01-01' }), '/api/x?periodo=90d');
    assert.equal(K.construirUrl('/api/x', { periodo: 'personalizado', desde: '2026-01-01', hasta: '2026-01-31', categoria: '2' }),
        '/api/x?periodo=personalizado&desde=2026-01-01&hasta=2026-01-31&categoria=2');
});

function entornoCarga(rutas) {
    const elementos = new Map();
    const id = 'kpi-admin';
    const def = (sufijo, o) => { const e = new FakeElement(`${id}-${sufijo}`, o); elementos.set(e.id, e); return e; };
    def('periodo', { tag: 'select', value: '90d' });
    def('desde', { tag: 'input' });
    def('hasta', { tag: 'input' });
    def('categoria', { tag: 'select', value: '' });
    def('cuerpo');
    def('errores');
    const cont = new FakeElement(id, { dataset: { endpoint: '/api/admin/kpis/indicadores', alcance: 'global', categorias: '/api/categorias' } });
    const { env, K } = cargarModulo({ elementos, rutas });
    return { env, K, cont, el: s => elementos.get(`${id}-${s}`) };
}

test('carga: GET con Bearer y filtros, y renderiza las tarjetas', async () => {
    const { env, K, cont, el } = entornoCarga(c => c.url.startsWith('/api/categorias')
        ? { data: [{ id_categoria: 1, nombre_categoria: 'Limpieza' }] }
        : { data: respuesta() });
    const ctl = K.crear(cont);
    await ctl.cargar();
    await env.asentar();

    const llamada = env.fetchCalls.find(c => c.url.startsWith('/api/admin/kpis/indicadores'));
    assert.equal(llamada.url, '/api/admin/kpis/indicadores?periodo=90d', 'usa el período seleccionado en el filtro');
    assert.equal(llamada.headers.Authorization, 'Bearer TOKEN-PRUEBA');
    assert.match(el('cuerpo').innerHTML, /data-kpi="conversion_contratacion"/);
    assert.match(el('categoria').innerHTML, /<option value="1">Limpieza<\/option>/);
});

test('carga: 422 muestra los mensajes del servidor; 403, 500 y fallo de red muestran estados de error', async () => {
    const casos = [
        [{ status: 422, data: { errors: { hasta: ['La fecha final no puede ser futura.'] } } }, /Corrige los filtros/],
        [{ status: 403, data: { message: 'No' } }, /No tienes permiso/],
        [{ status: 500, data: { message: 'Error' } }, /No se pudieron cargar/],
        [{ lanzar: new Error('red') }, /No se pudo conectar/],
    ];
    for (const [resp, esperado] of casos) {
        const { env, K, cont, el } = entornoCarga(c => c.url.startsWith('/api/categorias') ? { data: [] } : resp);
        const datos = await K.crear(cont).cargar();
        await env.asentar();
        assert.equal(datos, null);
        assert.match(el('cuerpo').innerHTML, esperado);
        if (resp.status === 422) assert.match(el('errores').innerHTML, /La fecha final no puede ser futura\./);
    }
});

// ═══════════════ Integración en las vistas ═══════════════

test('las tres vistas montan el módulo con el endpoint de su rol y lo cargan con Vite', () => {
    const rutas = leer('routes/api.php');
    const vistas = [
        ['resources/views/admin/kpis.blade.php', 'global', '/api/admin/kpis/indicadores', "Route::get('/kpis/indicadores'"],
        ['resources/views/dashboardContratador.blade.php', 'contratador', '/api/contratador/kpis', "[KpiApiController::class, 'contratador']"],
        ['resources/views/dashboard.blade.php', 'trabajador', '/api/trabajador/kpis', "[KpiApiController::class, 'trabajador']"],
    ];
    for (const [ruta, alcance, endpoint, enRutas] of vistas) {
        const html = leer(ruta);
        assert.match(html, /resources\/js\/kpi-dashboard\.js/, `${ruta} debe cargar kpi-dashboard.js`);
        assert.match(html, /resources\/css\/kpi-dashboard\.css/, `${ruta} debe cargar kpi-dashboard.css`);
        assert.ok(html.includes(`data-alcance="${alcance}"`) && html.includes(`data-endpoint="${endpoint}"`), `${ruta}: contenedor KPI`);
        assert.ok(rutas.includes(enRutas), `routes/api.php debe registrar ${endpoint}`);
    }
    assert.match(leer('vite.config.js'), /resources\/js\/kpi-dashboard\.js/);
});

test('admin: los KPIs viven solo en su página propia (Business Intelligence), no en el inicio', () => {
    const inicio = leer('resources/views/admin/dashboard.blade.php');
    assert.doesNotMatch(inicio, /data-kpi-dashboard|kpi-dashboard\.js/, 'el inicio no debe montar ni cargar el módulo de KPIs');
    const iReportes = inicio.indexOf('id="group-reportes"');
    const iBi = inicio.indexOf('id="group-bi"');
    assert.ok(iReportes > 0 && iBi > iReportes, 'Business Intelligence debe ir debajo de Reportes');
    assert.match(inicio.slice(iBi), /route\('admin\.kpis'\)[^<]*>.*Dashboard de KPIs/);
    assert.equal((leer('resources/views/admin/kpis.blade.php').match(/data-kpi-dashboard/g) || []).length, 1);
});

test('admin-dashboard.js: /dashboard#panel abre ese panel desde la página de KPIs', async () => {
    const SRC_ADMIN = neutralizarModulo(leer('resources/js/admin-dashboard.js'));
    const paneles = [];
    const enlace = new FakeElement('enlace-usuarios');
    const grupo = new FakeElement('group-seguridad');
    enlace.closest = () => grupo;
    const env = crearEntorno({
        source: SRC_ADMIN, filename: 'admin-dashboard.js', metas: { 'api-token': 'T' },
        querySelector: sel => (sel === `.nav-item[onclick="showPanel('usuarios')"]` ? enlace : undefined),
        querySelectorAll: sel => (sel === '.panel' ? paneles : undefined),
        globales: { location: { hash: '#usuarios', reload() {} } },
    });
    await env.asentar();
    assert.ok(env.el('panel-usuarios').classList.contains('active'), 'abre el panel indicado');
    assert.ok(enlace.classList.contains('active'), 'marca la opción del menú');
    assert.ok(grupo.classList.contains('open'), 'despliega su grupo');
});
