/**
 * RedWork — Sistema de KPIs: presentación compartida por los dashboards de
 * administrador, contratador y trabajador.
 *
 * Este archivo SOLO presenta: todos los cálculos (tasas, cohortes, comparaciones,
 * evaluación estadística y alertas) los entrega la API (KpiApiController).
 * Se inicia sobre cada elemento con [data-kpi-dashboard]:
 *   data-endpoint   URL de la API de indicadores del rol
 *   data-alcance    global | contratador | trabajador
 *   data-categorias (opcional) URL de categorías para el filtro (solo administrador)
 */
(function (global) {
    'use strict';

    const PERIODOS = [
        ['30d', 'Últimos 30 días'],
        ['90d', 'Últimos 90 días'],
        ['anio', 'Año actual'],
        ['personalizado', 'Intervalo personalizado'],
    ];

    const OBJETIVOS = {
        A: 'Efectividad de las publicaciones',
        B: 'Eficiencia de las postulaciones',
        C: 'Conversión a contrataciones',
        D: 'Cumplimiento de contrataciones',
        E: 'Control de pagos simulados',
        F: 'Calidad del servicio',
    };

    /** Significado de color: positivo = mejora demostrada, negativo = deterioro demostrado, neutro = sin evaluación suficiente. */
    const EVALUACIONES = {
        mejora:                   { clase: 'kpi-pos', texto: 'Mejora significativa (95 %)' },
        deterioro:                { clase: 'kpi-neg', texto: 'Deterioro significativo (95 %)' },
        sin_cambio_significativo: { clase: 'kpi-neu', texto: 'Variación no significativa' },
        insuficiente:             { clase: 'kpi-neu', texto: 'Muestra insuficiente para comparar' },
    };

    const SIN_DATOS = 'Sin datos suficientes';
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    // Colores de serie como variables CSS (resources/css/kpi-dashboard.css y themes.css): al cambiar el tema
    // los gráficos SVG se recolorean sin volver a dibujarse ni pedir datos. Se aplican con style="" porque
    // los atributos de presentación SVG (fill/stroke) no admiten var().
    const COLORES = { s1: 'var(--kpi-s1)', s2: 'var(--kpi-s2)', s3: 'var(--kpi-s3)', brand: 'var(--kpi-brand)' };

    // ── Formato ──

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function numero(v, decimales = 0) {
        return Number(v).toLocaleString('es-BO', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
    }

    /** Porcentaje con hasta 1 decimal; null/undefined → null (el llamador muestra «Sin datos suficientes»). */
    function porcentaje(v) {
        if (v === null || v === undefined) return null;
        return numero(v, Number.isInteger(v) ? 0 : 1) + ' %';
    }

    function bs(texto) {
        if (texto === null || texto === undefined) return '—';
        return 'Bs ' + numero(Number(texto), 2);
    }

    /** 'YYYY-MM-DD' → 'DD/MM/YYYY' sin conversión de zona horaria. */
    function fecha(iso) {
        if (!iso) return '—';
        const [a, m, d] = String(iso).slice(0, 10).split('-');
        return `${d}/${m}/${a}`;
    }

    /** 'YYYY-MM' → 'jun 26'. */
    function mesCorto(ym) {
        const [a, m] = String(ym).split('-');
        return `${MESES[Number(m) - 1]} ${a.slice(2)}`;
    }

    function valorIndicador(ind) {
        if (ind.valor === null || ind.valor === undefined) return null;
        if (ind.unidad === '%') return porcentaje(ind.valor);
        return numero(ind.valor, 2) + ' / 5';
    }

    function variacionTexto(ind) {
        const p = ind.variacion && ind.variacion.puntos;
        if (p === null || p === undefined) return null;
        const flecha = p > 0 ? '▲' : p < 0 ? '▼' : '＝';
        const signo = p > 0 ? '+' : '';
        return ind.unidad === '%'
            ? `${flecha} ${signo}${numero(p, 1)} pp`
            : `${flecha} ${signo}${numero(p, 2)}`;
    }

    // ── Componentes (devuelven HTML; sin cálculos de negocio) ──

    function renderTarjeta(ind) {
        const valor = valorIndicador(ind);
        const ev = EVALUACIONES[ind.variacion?.evaluacion] || EVALUACIONES.insuficiente;
        const coh = ind.cohorte || {};
        const base = ind.unidad === '%'
            ? (ind.denominador ? `${numero(ind.numerador)} de ${numero(ind.denominador)} casos` : '0 casos evaluables')
            : `n = ${numero(ind.denominador || 0)} calificación(es)`;
        const cohorte = coh.hasta
            ? `Cohorte ${fecha(coh.desde)} – ${fecha(coh.hasta)}`
            : 'Cohorte aún en maduración';
        const ant = ind.anterior || {};
        const valorAnt = ant.valor === null || ant.valor === undefined
            ? SIN_DATOS.toLowerCase()
            : (ind.unidad === '%' ? porcentaje(ant.valor) : numero(ant.valor, 2));
        const delta = variacionTexto(ind);

        return `<article class="kpi-card" data-kpi="${esc(ind.clave)}">
            <header>
                <span class="kpi-obj" title="Objetivo ${esc(ind.objetivo)}: ${esc(OBJETIVOS[ind.objetivo] || '')}">Obj. ${esc(ind.objetivo)}</span>
                <span class="kpi-nombre">${esc(ind.nombre)}</span>
                ${ind.simulado ? '<span class="kpi-sim">SIMULADO</span>' : ''}
            </header>
            <div class="kpi-valor${valor === null ? ' kpi-vacio' : ''}">${esc(valor === null ? SIN_DATOS : valor)}</div>
            <div class="kpi-base">${esc(base)} · ${esc(cohorte)}</div>
            ${coh.en_maduracion && coh.hasta ? '<div class="kpi-madurez">Los casos más recientes aún no maduran y se evaluarán después.</div>' : ''}
            <div class="kpi-comp ${ev.clase}">
                ${delta ? `<span class="kpi-badge">${esc(delta)}</span>` : ''}
                <span>Anterior: ${esc(valorAnt)}${ant.denominador ? ` (n = ${numero(ant.denominador)})` : ''}</span>
                <span class="kpi-eval">${esc(ev.texto)}</span>
            </div>
            <p class="kpi-interp">${esc(ind.interpretacion)}</p>
            <p class="kpi-pregunta">${esc(ind.pregunta)}</p>
        </article>`;
    }

    function renderAlertas(alertas) {
        if (!alertas || !alertas.length) return '';
        const etiqueta = { alta: 'Atención', media: 'Revisar', info: 'Datos' };
        return `<ul class="kpi-alertas" aria-label="Alertas">${alertas.map(a =>
            `<li class="kpi-alerta kpi-alerta-${esc(a.nivel)}"><b>${esc(etiqueta[a.nivel] || a.nivel)}</b><span>${esc(a.mensaje)}</span></li>`
        ).join('')}</ul>`;
    }

    function panel(titulo, pregunta, cuerpo, extra = '') {
        return `<section class="kpi-panel"><h3>${esc(titulo)}</h3><p class="kpi-pregunta">${esc(pregunta)}</p>${cuerpo}${extra}</section>`;
    }

    function vacio(texto = 'Sin datos en el período seleccionado.') {
        return `<div class="kpi-estado">${esc(texto)}</div>`;
    }

    function tablaDatos(meses, series) {
        return `<details class="kpi-datos"><summary>Ver datos</summary><div class="kpi-tabla-wrap"><table class="kpi-tabla">
            <thead><tr><th>Mes</th>${series.map(s => `<th class="kpi-num">${esc(s.etiqueta)}</th>`).join('')}</tr></thead>
            <tbody>${meses.map(m => `<tr><td>${esc(mesCorto(m.mes))}${m.parcial ? ' (parcial)' : ''}</td>${series.map(s => {
                const v = m[s.clave];
                return v === null || v === undefined
                    ? '<td class="kpi-num kpi-sd">s/d</td>'
                    : `<td class="kpi-num">${esc(s.sufijo === '%' ? porcentaje(v) : numero(v))}</td>`;
            }).join('')}</tr>`).join('')}</tbody></table></div></details>`;
    }

    /**
     * Gráfico de líneas (tasas 0–100 %). Los meses sin denominador (null) cortan la línea:
     * nunca se interpolan ni se rellenan con ceros.
     */
    function renderLineas(meses, series) {
        const hayDatos = meses.some(m => series.some(s => m[s.clave] !== null && m[s.clave] !== undefined));
        if (!hayDatos) return vacio('Sin datos históricos suficientes para trazar una tendencia.');

        const W = 640, H = 230, L = 40, R = 12, T = 12, B = 30;
        const ancho = W - L - R, alto = H - T - B;
        const x = i => L + (meses.length === 1 ? ancho / 2 : (i * ancho) / (meses.length - 1));
        const y = v => T + alto - (v / 100) * alto;

        let svg = `<svg class="kpi-svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(series.map(s => s.etiqueta).join(', '))} por mes">`;
        for (const t of [0, 25, 50, 75, 100]) {
            svg += `<line class="kpi-eje" x1="${L}" x2="${W - R}" y1="${y(t)}" y2="${y(t)}"/><text x="${L - 6}" y="${y(t) + 4}" text-anchor="end">${t}%</text>`;
        }
        meses.forEach((m, i) => {
            if (meses.length <= 12 || i % 2 === 0) {
                svg += `<text x="${x(i)}" y="${H - 8}" text-anchor="middle">${esc(mesCorto(m.mes))}</text>`;
            }
        });

        for (const s of series) {
            let tramo = [];
            const tramos = [];
            meses.forEach((m, i) => {
                const v = m[s.clave];
                if (v === null || v === undefined) { if (tramo.length) tramos.push(tramo); tramo = []; }
                else tramo.push([x(i), y(v), m]);
            });
            if (tramo.length) tramos.push(tramo);
            for (const tr of tramos) {
                if (tr.length > 1) {
                    svg += `<polyline fill="none" style="stroke:${s.color}" stroke-width="2.2" stroke-linejoin="round" points="${tr.map(p => p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ')}"/>`;
                }
                for (const [px, py, m] of tr) {
                    const hueco = m.en_maduracion;
                    svg += `<circle cx="${px.toFixed(1)}" cy="${py.toFixed(1)}" r="3.6" ${hueco ? 'fill="none" ' : ''}style="${hueco ? '' : `fill:${s.color};`}stroke:${s.color}" stroke-width="1.6"><title>${esc(s.etiqueta)} · ${esc(mesCorto(m.mes))}: ${esc(porcentaje(m[s.clave]))}${hueco ? ' (en maduración)' : ''}</title></circle>`;
                }
            }
        }
        svg += '</svg>';

        const leyenda = `<div class="kpi-leyenda">${series.map(s => `<span><i style="background:${s.color}"></i>${esc(s.etiqueta)}</span>`).join('')}</div>`;
        const nota = '<p class="kpi-nota">Punto hueco: mes parcial o con casos que aún no maduran. Un corte en la línea indica un mes sin casos (no se inventan valores).</p>';

        return svg + leyenda + nota + tablaDatos(meses, series.map(s => ({ ...s, sufijo: '%' })));
    }

    /** Barras agrupadas de conteos mensuales (los ceros son ceros reales). */
    function renderBarras(meses, series) {
        const max = Math.max(0, ...meses.flatMap(m => series.map(s => Number(m[s.clave] || 0))));
        if (max === 0) return vacio('Sin actividad registrada en los últimos meses.');

        const W = 640, H = 230, L = 36, R = 8, T = 12, B = 30;
        const ancho = W - L - R, alto = H - T - B;
        const grupo = ancho / meses.length;
        const barra = Math.max(2, (grupo * 0.78) / series.length);
        const paso = Math.max(1, Math.ceil(max / 4));
        const tope = paso * 4;
        const y = v => T + alto - (v / tope) * alto;

        let svg = `<svg class="kpi-svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(series.map(s => s.etiqueta).join(', '))} por mes">`;
        for (let t = 0; t <= tope; t += paso) {
            svg += `<line class="kpi-eje" x1="${L}" x2="${W - R}" y1="${y(t)}" y2="${y(t)}"/><text x="${L - 6}" y="${y(t) + 4}" text-anchor="end">${t}</text>`;
        }
        meses.forEach((m, i) => {
            const x0 = L + i * grupo + (grupo - barra * series.length) / 2;
            series.forEach((s, k) => {
                const v = Number(m[s.clave] || 0);
                if (v > 0) {
                    svg += `<rect x="${(x0 + k * barra).toFixed(1)}" y="${y(v).toFixed(1)}" width="${(barra - 1).toFixed(1)}" height="${(T + alto - y(v)).toFixed(1)}" rx="2" style="fill:${s.color}"${m.parcial ? ' fill-opacity=".55"' : ''}><title>${esc(s.etiqueta)} · ${esc(mesCorto(m.mes))}: ${v}</title></rect>`;
                }
            });
            if (meses.length <= 12 || i % 2 === 0) {
                svg += `<text x="${(L + i * grupo + grupo / 2).toFixed(1)}" y="${H - 8}" text-anchor="middle">${esc(mesCorto(m.mes))}</text>`;
            }
        });
        svg += '</svg>';

        return svg
            + `<div class="kpi-leyenda">${series.map(s => `<span><i style="background:${s.color}"></i>${esc(s.etiqueta)}</span>`).join('')}</div>`
            + '<p class="kpi-nota">Barras atenuadas: mes en curso (parcial).</p>'
            + tablaDatos(meses, series);
    }

    function renderEmbudo(embudo) {
        const etapas = (embudo && embudo.etapas) || [];
        if (!etapas.length || !etapas[0].valor) return vacio('Sin postulaciones evaluables en la cohorte del período.');
        return `<div class="kpi-embudo">${etapas.map(e => `<div class="kpi-etapa">
                <span>${esc(e.etiqueta)}</span>
                <div class="kpi-etapa-barra" role="img" aria-label="${esc(e.etiqueta)}: ${e.valor} (${esc(porcentaje(e.porcentaje) || SIN_DATOS)})">
                    <span style="width:${Math.max(0, Math.min(100, Number(e.porcentaje) || 0))}%"></span>
                    <em>${numero(e.valor)} · ${esc(porcentaje(e.porcentaje) || SIN_DATOS)}</em>
                </div>
            </div>`).join('')}</div>
            <p class="kpi-nota">Todas las etapas usan la misma cohorte de postulaciones (denominador común)${embudo.cohorte_hasta ? `, registradas hasta el ${esc(fecha(embudo.cohorte_hasta))}` : ''}.</p>`;
    }

    function metrica(etiqueta, valor, detalle = '', atencion = false) {
        return `<div class="kpi-metrica${atencion ? ' kpi-atencion' : ''}"><span>${esc(etiqueta)}</span><strong>${esc(valor)}</strong>${detalle ? `<small>${esc(detalle)}</small>` : ''}</div>`;
    }

    function dias(v) {
        return v === null || v === undefined ? '—' : `${numero(v)} día(s)`;
    }

    function renderPagos(pagos, pendientes, umbral, alcance) {
        if (!pagos) return vacio('El módulo de pagos simulados aún no está disponible.');
        const t = pagos.tiempo_confirmacion_horas || {};
        const cuerpo = `<div class="kpi-metricas">
            ${metrica('Registrados', numero(pagos.registrados), 'en el período')}
            ${metrica('Confirmados', numero(pagos.confirmado.cantidad), bs(pagos.confirmado.monto_bs))}
            ${metrica('Pendientes', numero(pagos.pendiente.cantidad), bs(pagos.pendiente.monto_bs))}
            ${metrica('Anulados', numero(pagos.anulado.cantidad), 'Tasa: ' + (porcentaje(pagos.tasa_anulacion) || SIN_DATOS))}
            ${alcance !== 'trabajador' ? metrica('Mediana hasta confirmar', t.mediana === null || t.mediana === undefined ? SIN_DATOS : `${numero(t.mediana, 1)} h`,
                `n = ${numero(t.n || 0)} · anterior: ${t.anterior === null || t.anterior === undefined ? 's/d' : numero(t.anterior, 1) + ' h'}`) : ''}
        </div>`;
        const p = pendientes || {};
        const pend = `<h3 style="margin-top:1rem">Pendientes de confirmación (estado actual)</h3>
            <div class="kpi-metricas" style="margin-top:.6rem">
                ${metrica('Registros', numero(p.cantidad || 0))}
                ${metrica('Monto simulado', bs(p.monto_bs))}
                ${metrica('Antigüedad mediana', dias(p.mediana_dias), 'máxima: ' + dias(p.max_dias))}
                ${metrica(`Más de ${umbral} días`, numero(p.sobre_umbral || 0), '', (p.sobre_umbral || 0) > 0)}
            </div>`;
        return cuerpo + pend + '<p class="kpi-nota">Montos de registros SIMULADOS: no son dinero depositado, ingresos reales ni deuda bancaria.</p>';
    }

    function renderPendientesPostulaciones(p, umbral) {
        if (!p) return '';
        const lista = p.mas_antiguas && p.mas_antiguas.length
            ? `<ul class="kpi-lista">${p.mas_antiguas.map(x => `<li><span>#${esc(x.id_postulacion)} · ${esc(x.titulo)}</span><span>${esc(dias(x.dias))}</span></li>`).join('')}</ul>`
            : '';
        return `<div class="kpi-metricas">
                ${metrica('Pendientes', numero(p.cantidad))}
                ${metrica('Antigüedad mediana', dias(p.mediana_dias))}
                ${metrica('Más antigua', dias(p.max_dias))}
                ${metrica(`Más de ${umbral} días`, numero(p.sobre_umbral), '', p.sobre_umbral > 0)}
            </div>${lista}
            <p class="kpi-nota">Antigüedad = días desde la fecha de postulación hasta hoy. No existe una fecha de decisión registrada, por lo que el tiempo de respuesta de las ya evaluadas no se calcula.</p>`;
    }

    function renderCalidad(calidad, alcance) {
        if (!calidad || !calidad.n) return vacio('Sin calificaciones en el período seleccionado.');
        const dist = calidad.distribucion || {};
        const max = Math.max(1, ...Object.values(dist).map(Number));
        const barras = ['5', '4', '3', '2', '1'].map(k => `<div class="kpi-etapa">
                <span>${'★'.repeat(Number(k))}</span>
                <div class="kpi-etapa-barra" role="img" aria-label="${k} estrellas: ${dist[k] || 0}"><span style="width:${((dist[k] || 0) / max) * 100}%;background:linear-gradient(90deg,rgba(245,158,11,.6),rgba(245,158,11,.25))"></span><em>${numero(dist[k] || 0)}</em></div>
            </div>`).join('');
        let extra = '';
        if (alcance === 'global' && calidad.por_rol) {
            const r = calidad.por_rol;
            const pr = x => (x.promedio === null || x.promedio === undefined ? SIN_DATOS : numero(x.promedio, 2) + ' / 5');
            extra += `<div class="kpi-metricas" style="margin-top:.8rem">
                ${metrica('Trabajadores evaluados', pr(r.trabajador), `n = ${numero(r.trabajador.n)}`)}
                ${metrica('Contratadores evaluados', pr(r.contratador), `n = ${numero(r.contratador.n)}`)}
                ${calidad.cobertura ? metrica('Cobertura', porcentaje(calidad.cobertura.porcentaje) || SIN_DATOS,
                    `${numero(calidad.cobertura.calificadas)} de ${numero(calidad.cobertura.finalizadas)} finalizadas calificadas`) : ''}
            </div>`;
        }
        return `<div class="kpi-embudo">${barras}</div>${extra}<p class="kpi-nota">Promedio ${esc(numero(calidad.promedio, 2))} / 5 sustentado en ${numero(calidad.n)} calificación(es). Solo se califican contrataciones finalizadas.</p>`;
    }

    function renderEstadoContrataciones(c) {
        if (!c) return '';
        const e = c.estado_actual || {};
        return `<div class="kpi-metricas">
            ${metrica('Activas', numero(e.activa || 0), 'estado actual')}
            ${metrica('Finalizadas', numero(e.finalizada || 0), 'histórico')}
            ${metrica('Canceladas', numero(e.cancelada || 0), 'histórico')}
            ${metrica('Cancelación', porcentaje(c.tasa_cancelacion) || SIN_DATOS, `${numero(c.canceladas_periodo)} de ${numero(c.finalizadas_periodo + c.canceladas_periodo)} cerradas en el período`)}
        </div>`;
    }

    function renderCategorias(filas) {
        if (!filas || !filas.length) return vacio('Sin actividad por categoría en el período.');
        const c = v => v === null || v === undefined ? `<td class="kpi-num kpi-sd">${SIN_DATOS}</td>` : `<td class="kpi-num">${esc(porcentaje(v))}</td>`;
        return `<div class="kpi-tabla-wrap"><table class="kpi-tabla">
            <thead><tr><th>Categoría</th><th class="kpi-num">Publicaciones (cohorte)</th><th class="kpi-num">Efectividad</th><th class="kpi-num">Postulaciones</th><th class="kpi-num">Conversión</th><th class="kpi-num">Contr. cerradas</th><th class="kpi-num">Finalización</th></tr></thead>
            <tbody>${filas.map(f => `<tr><td>${esc(f.nombre)}</td><td class="kpi-num">${numero(f.publicaciones)}</td>${c(f.efectividad)}<td class="kpi-num">${numero(f.postulaciones)}</td>${c(f.conversion)}<td class="kpi-num">${numero(f.cerradas)}</td>${c(f.finalizacion)}</tr>`).join('')}</tbody>
        </table></div><p class="kpi-nota">Compare tasas solo cuando el número de casos sea suficiente; una tasa con pocos casos es poco confiable.</p>`;
    }

    function renderPeriodo(d) {
        const p = d.periodo;
        return `<div class="kpi-periodo">
            <span>Período: <strong>${esc(p.etiqueta)}</strong> (${esc(fecha(p.desde))} – ${esc(fecha(p.hasta))}${p.parcial ? ', parcial' : ''})</span>
            <span>Comparado con: <strong>${esc(fecha(p.anterior.desde))} – ${esc(fecha(p.anterior.hasta))}</strong></span>
            <span>Actualizado: ${esc(String(d.generado_en || '').replace('T', ' ').slice(0, 16))}</span>
        </div>`;
    }

    /** Composición completa según el alcance (rol). */
    function renderTodo(d) {
        const alcance = d.alcance;
        const u = d.parametros.umbral_antiguedad_dias;
        const ev = d.evolucion || [];
        const trabajador = alcance === 'trabajador';

        const lineas = trabajador
            ? [{ clave: 'tasa_gestion', etiqueta: 'Con respuesta', color: COLORES.s1 }, { clave: 'tasa_conversion', etiqueta: 'Convertidas en contratación', color: COLORES.s2 }]
            : [{ clave: 'tasa_gestion', etiqueta: 'Gestión', color: COLORES.s3 }, { clave: 'tasa_conversion', etiqueta: 'Conversión', color: COLORES.s1 }, { clave: 'tasa_finalizacion', etiqueta: 'Finalización', color: COLORES.s2 }];
        const barras = [
            { clave: 'postulaciones', etiqueta: trabajador ? 'Mis postulaciones' : 'Postulaciones', color: COLORES.s3 },
            { clave: 'contrataciones_iniciadas', etiqueta: 'Contrataciones iniciadas', color: COLORES.s1 },
            { clave: 'finalizadas', etiqueta: 'Finalizadas', color: COLORES.s2 },
        ];

        let html = renderPeriodo(d)
            + `<div class="kpi-aviso">${esc(d.aviso)}</div>`
            + renderAlertas(d.alertas)
            + `<div class="kpi-tarjetas">${d.indicadores.map(renderTarjeta).join('')}</div>`;

        html += '<div class="kpi-grid">'
            + panel('Embudo de conversión', '¿En qué etapa se pierden las oportunidades de una misma cohorte de postulaciones?', renderEmbudo(d.embudo))
            + panel('Tendencia de las tasas', '¿Mejoran los procesos mes a mes? (últimos 12 meses)', renderLineas(ev, lineas))
            + '</div><div class="kpi-grid">'
            + panel('Volumen mensual', '¿Cómo evoluciona la actividad de postulaciones y contrataciones?', renderBarras(ev, barras))
            + panel(trabajador ? 'Mis pagos simulados' : 'Seguimiento de pagos simulados',
                trabajador ? '¿Qué parte de los pagos simulados asociados a mis contrataciones está confirmada o pendiente?' : '¿Se confirman a tiempo los registros de pago simulados?',
                renderPagos(d.pagos, d.pendientes.pagos, u, alcance))
            + '</div><div class="kpi-grid">'
            + panel(trabajador ? 'Calificaciones recibidas' : 'Calidad percibida', '¿Cómo se distribuyen las puntuaciones (1–5) del período?', renderCalidad(d.calidad, alcance))
            + (trabajador
                ? panel('Estado de mis contrataciones', '¿Cuántas contrataciones tengo en curso, finalizadas o canceladas?', renderEstadoContrataciones(d.contrataciones))
                : panel('Postulaciones pendientes de decisión', '¿Qué solicitudes esperan respuesta y desde cuándo? (estado actual)', renderPendientesPostulaciones(d.pendientes.postulaciones, u)
                    + '<h3 style="margin-top:1rem">Contrataciones</h3><div style="margin-top:.6rem">' + renderEstadoContrataciones(d.contrataciones) + '</div>'))
            + '</div>';

        if (alcance === 'global') {
            html += `<div class="kpi-grid">${panel('Resultados por categoría laboral', '¿Qué categorías concentran baja efectividad o baja conversión?', renderCategorias(d.categorias))}</div>`;
        }

        const pr = d.parametros;
        html += `<p class="kpi-pie">Metodología: cohortes de postulaciones con ${pr.maduracion_postulaciones_dias} día(s) de maduración; publicaciones observadas durante ${pr.ventana_publicaciones_dias} días;
            alertas de antigüedad a partir de ${pr.umbral_antiguedad_dias} días; las variaciones se califican como mejora o deterioro solo con al menos ${pr.muestra_minima} casos por período y significación del ${esc(pr.confianza)}.
            «${SIN_DATOS}» indica denominador cero: no se muestra 0 % ni 100 %.</p>`;

        return html;
    }

    // ── Carga desde la API ──

    function construirUrl(endpoint, filtros) {
        const q = new URLSearchParams();
        q.set('periodo', filtros.periodo || '30d');
        if (filtros.periodo === 'personalizado') {
            q.set('desde', filtros.desde || '');
            q.set('hasta', filtros.hasta || '');
        }
        if (filtros.categoria) q.set('categoria', filtros.categoria);
        return `${endpoint}?${q.toString()}`;
    }

    function token() {
        const m = global.document && global.document.querySelector('meta[name="api-token"]');
        return m ? m.content : '';
    }

    async function pedir(url) {
        const r = await global.fetch(url, { headers: { Accept: 'application/json', Authorization: 'Bearer ' + token() } });
        let datos = null;
        try { datos = await r.json(); } catch (e) { datos = null; }
        return { status: r.status, ok: r.ok, datos };
    }

    function esqueleto(id, cfg) {
        const opciones = PERIODOS.map(([v, t]) => `<option value="${v}">${esc(t)}</option>`).join('');
        return `<div class="kpi-cabecera">
                <div>
                    <div class="kpi-titulo">${esc(cfg.titulo)}</div>
                    <div class="kpi-subtitulo">${esc(cfg.subtitulo)}</div>
                </div>
                <form class="kpi-filtros" id="${id}-form" novalidate>
                    <label class="kpi-campo">Período<select id="${id}-periodo">${opciones}</select></label>
                    <label class="kpi-campo" id="${id}-campo-desde" hidden>Desde<input type="date" id="${id}-desde"></label>
                    <label class="kpi-campo" id="${id}-campo-hasta" hidden>Hasta<input type="date" id="${id}-hasta"></label>
                    ${cfg.categorias ? `<label class="kpi-campo">Categoría<select id="${id}-categoria"><option value="">Todas</option></select></label>` : ''}
                    <button type="submit" class="kpi-boton" id="${id}-aplicar">Aplicar</button>
                </form>
            </div>
            <div id="${id}-errores" role="alert"></div>
            <div id="${id}-cuerpo" aria-live="polite"></div>`;
    }

    const TEXTOS = {
        global:      ['Indicadores de desempeño', 'Efectividad, eficiencia, conversión, cumplimiento, control de pagos simulados y calidad de RedWork, con comparación entre períodos.'],
        contratador: ['Mis indicadores', 'Desempeño de tus ofertas, postulaciones recibidas y contrataciones. Solo incluye tus propios datos.'],
        trabajador:  ['Mi seguimiento', 'Evolución de tus postulaciones, contrataciones, pagos simulados y calificaciones. Solo incluye tus propios datos.'],
    };

    function crear(contenedor) {
        const id = contenedor.id;
        const doc = global.document;
        const $ = s => doc.getElementById(`${id}-${s}`);
        const alcance = contenedor.dataset.alcance || 'global';
        const cfg = {
            endpoint: contenedor.dataset.endpoint,
            categorias: contenedor.dataset.categorias || '',
            titulo: (TEXTOS[alcance] || TEXTOS.global)[0],
            subtitulo: (TEXTOS[alcance] || TEXTOS.global)[1],
        };

        function filtros() {
            return {
                periodo: $('periodo') ? $('periodo').value : '30d',
                desde: $('desde') ? $('desde').value : '',
                hasta: $('hasta') ? $('hasta').value : '',
                categoria: $('categoria') ? $('categoria').value : '',
            };
        }

        function alternarFechas() {
            const p = $('periodo') && $('periodo').value === 'personalizado';
            if ($('campo-desde')) $('campo-desde').hidden = !p;
            if ($('campo-hasta')) $('campo-hasta').hidden = !p;
        }

        async function cargar() {
            const cuerpo = $('cuerpo');
            const errores = $('errores');
            if (errores) errores.innerHTML = '';
            cuerpo.innerHTML = '<div class="kpi-cargando" aria-label="Cargando indicadores"><span></span><span></span><span></span><span></span></div>';
            try {
                const r = await pedir(construirUrl(cfg.endpoint, filtros()));
                if (r.status === 422) {
                    const lista = Object.values((r.datos && r.datos.errors) || {}).flat();
                    if (errores) errores.innerHTML = lista.map(m => `<p class="kpi-error-campo">${esc(m)}</p>`).join('');
                    cuerpo.innerHTML = vacio('Corrige los filtros para consultar los indicadores.');
                    return null;
                }
                if (!r.ok || !r.datos || !Array.isArray(r.datos.indicadores)) {
                    const msg = r.status === 401 || r.status === 403
                        ? 'No tienes permiso para consultar estos indicadores.'
                        : 'No se pudieron cargar los indicadores. Intenta nuevamente.';
                    cuerpo.innerHTML = `<div class="kpi-estado kpi-error">${esc(msg)}</div>`;
                    return null;
                }
                cuerpo.innerHTML = renderTodo(r.datos);
                return r.datos;
            } catch (e) {
                cuerpo.innerHTML = '<div class="kpi-estado kpi-error">No se pudo conectar con el servidor de indicadores.</div>';
                return null;
            }
        }

        async function cargarCategorias() {
            if (!cfg.categorias || !$('categoria')) return;
            try {
                const r = await pedir(cfg.categorias);
                const lista = Array.isArray(r.datos) ? r.datos : (r.datos && r.datos.data) || [];
                $('categoria').innerHTML = '<option value="">Todas</option>' + lista
                    .map(c => `<option value="${esc(c.id_categoria)}">${esc(c.nombre_categoria)}</option>`).join('');
            } catch (e) { /* el filtro queda en «Todas» */ }
        }

        contenedor.innerHTML = esqueleto(id, cfg);
        if ($('periodo')) $('periodo').addEventListener('change', () => { alternarFechas(); if ($('periodo').value !== 'personalizado') cargar(); });
        if ($('categoria')) $('categoria').addEventListener('change', cargar);
        if ($('form')) $('form').addEventListener('submit', ev => { ev.preventDefault(); cargar(); });

        cargarCategorias();
        return { cargar, filtros, alternarFechas };
    }

    function iniciarTodos() {
        const doc = global.document;
        if (!doc || !doc.querySelectorAll) return [];
        return [...doc.querySelectorAll('[data-kpi-dashboard]')].map(el => {
            const ctl = crear(el);
            ctl.cargar();
            return ctl;
        });
    }

    global.RedWorkKpi = {
        PERIODOS, EVALUACIONES, SIN_DATOS,
        esc, porcentaje, bs, fecha, mesCorto, valorIndicador, variacionTexto,
        renderTarjeta, renderAlertas, renderLineas, renderBarras, renderEmbudo, renderPagos,
        renderPendientesPostulaciones, renderCalidad, renderEstadoContrataciones, renderCategorias, renderTodo,
        construirUrl, crear, iniciarTodos,
    };

    if (global.document && global.document.readyState === 'loading') {
        global.document.addEventListener('DOMContentLoaded', iniciarTodos);
    } else {
        iniciarTodos();
    }
})(typeof window !== 'undefined' ? window : globalThis);
