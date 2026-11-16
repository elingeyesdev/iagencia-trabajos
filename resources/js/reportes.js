/**
 * Incremento 4 — Vista imprimible de reportes (API → Consultas → JSON → Vista imprimible).
 *
 * Lee la query string actual, llama a GET /api/admin/reportes/{tipo} con el token Bearer de
 * <meta name="api-token"> (token Sanctum de corta duración creado por ReporteApiController::tokenParaVista) y rellena
 * las tablas/totales de resources/views/reportes/{tipo}.blade.php. TODO dato se escapa con esc().
 * Los filtros son formularios GET que recargan la página; el servidor valida (422) y aquí se muestran
 * los errores por campo. Sin dependencias; se carga con @vite en reportes/layout.blade.php.
 * Botón «Descargar PDF» (#btn-pdf): GET /api/admin/reportes/{tipo}/pdf con los mismos filtros y el mismo
 * Bearer (nunca en la URL) → blob → URL.createObjectURL → <a download> → URL.revokeObjectURL.
 */
(function () {
    const meta = n => document.querySelector('meta[name="' + n + '"]');
    const TIPOS = ['usuarios', 'publicaciones', 'categorias', 'actividad', 'pagos'];
    const tipo = meta('reporte-tipo') ? meta('reporte-tipo').content : '';
    const TOKEN = meta('api-token') ? meta('api-token').content : '';
    if (!TIPOS.includes(tipo)) return; // índice u otra página: nada que cargar

    const FILTROS = ['rol', 'estado', 'categoria', 'desde', 'hasta'];
    const $ = id => document.getElementById(id);
    const VACIO = cols => '<tr><td colspan="' + cols + '" class="vacio">Sin registros</td></tr>';

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    window.esc = window.esc || esc;

    function setTexto(id, v) { const e = $(id); if (e) e.textContent = v === null || v === undefined ? '' : String(v); }
    function setHtml(id, html) { const e = $(id); if (e) e.innerHTML = html; }
    function filas(id, lista, cols, fila) { setHtml(id, lista && lista.length ? lista.map(fila).join('') : VACIO(cols)); }
    const activa = (estado, m, f) => Number(estado) === 1 ? m : f;

    // ── Query string → filtros (los mismos que valida ReporteApiController::filtros) ──
    const params = new URLSearchParams(window.location.search || '');
    const query = {};
    FILTROS.forEach(k => { const v = params.get(k); if (v !== null && v !== '') query[k] = v; });

    // Rellenar el formulario con la query actual (antes de la respuesta, también tras un 422).
    function rellenarFormulario() {
        FILTROS.forEach(k => { const e = $('f-' + k); if (e && query[k] !== undefined) e.value = query[k]; });
    }

    function opcionesSelect(id, primera, lista, valor, texto) {
        const e = $(id);
        if (!e) return;
        e.innerHTML = '<option value="">' + esc(primera) + '</option>' +
            lista.map(o => '<option value="' + esc(valor(o)) + '">' + esc(texto(o)) + '</option>').join('');
    }

    function nota(d) {
        const e = $('rep-limite');
        if (!e) return;
        if (d.total > d.mostrados) {
            e.textContent = 'Se muestran los primeros ' + d.mostrados + ' de ' + d.total + ' registros; los totales consideran todos.';
            e.style.display = '';
        } else {
            e.style.display = 'none';
        }
    }

    // ── Renderizadores por reporte (mismas columnas y textos que las vistas originales) ──
    const RENDER = {
        usuarios(d) {
            opcionesSelect('f-rol', 'Todos', d.opciones.roles, r => r, r => r);
            rellenarFormulario();
            const f = d.filtros || {};
            setTexto('rep-filtros', 'Filtros: rol = ' + (f.rol ?? 'todos') + ' · estado = ' +
                (f.estado !== undefined ? (f.estado === '1' ? 'activo' : 'inactivo') : 'todos'));
            setTexto('t-total', d.total);
            setTexto('t-activos', d.por_estado.activos);
            setTexto('t-inactivos', d.por_estado.inactivos);
            let roles = d.por_rol.length
                ? d.por_rol.map(r => '<tr><td>' + esc(r.rol) + '</td><td class="num">' + esc(r.total) + '</td></tr>').join('')
                : VACIO(2);
            if (d.sin_rol > 0) roles += '<tr><td>(sin rol)</td><td class="num">' + esc(d.sin_rol) + '</td></tr>';
            setHtml('tb-roles', roles);
            filas('tb-filas', d.filas, 7, u => '<tr>' +
                '<td>' + esc(u.cedula) + '</td><td>' + esc(u.nombre) + '</td><td>' + esc(u.email) + '</td>' +
                '<td>' + esc(u.ciudad ?? '—') + '</td><td>' + esc(u.roles.length ? u.roles.join(', ') : '—') + '</td>' +
                '<td>' + activa(u.estado, 'Activo', 'Inactivo') + '</td><td>' + esc(u.fecha_registro) + '</td></tr>');
            setTexto('tf-total', d.total);
            nota(d);
        },

        publicaciones(d) {
            const cats = d.opciones.categorias;
            opcionesSelect('f-categoria', 'Todas', cats, c => c.id_categoria, c => c.nombre_categoria);
            rellenarFormulario();
            const f = d.filtros || {};
            const cat = f.categoria !== undefined ? cats.find(c => String(c.id_categoria) === String(f.categoria)) : null;
            setTexto('rep-filtros', 'Filtros: estado = ' + (f.estado !== undefined ? (f.estado === '1' ? 'activa' : 'inactiva') : 'todos') +
                ' · categoría = ' + (f.categoria !== undefined ? (cat ? cat.nombre_categoria : '#' + f.categoria) : 'todas') +
                ' · fecha de publicación: ' + (f.desde ?? 'inicio') + ' a ' + (f.hasta ?? 'hoy'));
            setTexto('t-total', d.total);
            setTexto('t-activos', d.por_estado.activos);
            setTexto('t-inactivos', d.por_estado.inactivos);
            setTexto('t-suma-pago', 'Bs ' + d.suma_pago);
            filas('tb-filas', d.filas, 8, p => '<tr>' +
                '<td>' + esc(p.id_labor) + '</td><td>' + esc(p.titulo) + '</td><td>' + esc(p.tipo) + '</td>' +
                '<td>' + esc(p.categoria ?? '(sin categoría)') + '</td>' +
                '<td>' + esc(p.contratador ?? '(usuario ' + p.cedula + ')') + '</td>' +
                '<td>' + esc(p.fecha_publicacion) + '</td><td class="num">' + esc(p.pago) + '</td>' +
                '<td>' + activa(p.estado, 'Activa', 'Inactiva') + '</td></tr>');
            setTexto('tf-etiqueta', 'Total (' + d.total + ' publicaciones)');
            setTexto('tf-suma-pago', d.suma_pago);
            nota(d);
        },

        categorias(d) {
            setTexto('t-total', d.total);
            setTexto('t-activos', d.por_estado.activos);
            setTexto('t-inactivos', d.por_estado.inactivos);
            filas('tb-filas', d.filas, 4, c => '<tr>' +
                '<td>' + esc(c.id_categoria) + '</td><td>' + esc(c.nombre_categoria) + '</td>' +
                '<td>' + activa(c.estado, 'Activa', 'Inactiva') + '</td><td class="num">' + esc(c.publicaciones_count) + '</td></tr>');
            setTexto('tf-publicaciones', d.total_publicaciones);
        },

        actividad(d) {
            rellenarFormulario();
            const f = d.filtros || {};
            setTexto('rep-filtros', 'Periodo: ' + (f.desde ?? 'inicio') + ' a ' + (f.hasta ?? 'hoy'));
            const secciones = [
                ['usuarios', 'Usuarios registrados', 'fecha_registro'],
                ['publicaciones', 'Publicaciones', 'fecha_publicacion'],
                ['postulaciones', 'Postulaciones', 'fecha_postulacion'],
                ['contrataciones', 'Contrataciones', 'fecha_inicio'],
                ['pagos', 'Pagos (simulados)', 'fecha_pago'],
            ];
            const etiqueta = (clave, estado) => ['usuarios', 'publicaciones'].includes(clave)
                ? (Number(estado) === 1 ? 'activo' : 'inactivo') : estado;
            setHtml('tb-resumen', secciones.map(([clave, titulo, columna]) => {
                const s = d[clave];
                const desglose = s.por_estado.length
                    ? s.por_estado.map(e => esc(etiqueta(clave, e.estado)) + ': ' + esc(e.total)).join(' · ')
                    : 'Sin registros';
                return '<tr><td>' + esc(titulo) + '</td><td>' + esc(columna) + '</td>' +
                    '<td class="num" data-total="' + esc(clave) + '">' + esc(s.total) + '</td><td>' + desglose + '</td></tr>';
            }).join(''));
            filas('tb-usuarios', d.usuarios.recientes, 3, u =>
                '<tr><td>' + esc(u.cedula) + '</td><td>' + esc(u.nombre) + '</td><td>' + esc(u.fecha_registro) + '</td></tr>');
            filas('tb-publicaciones', d.publicaciones.recientes, 4, p =>
                '<tr><td>' + esc(p.id_labor) + '</td><td>' + esc(p.titulo) + '</td><td>' + esc(p.fecha_publicacion) + '</td><td>' +
                activa(p.estado, 'Activa', 'Inactiva') + '</td></tr>');
            filas('tb-postulaciones', d.postulaciones.recientes, 5, p =>
                '<tr><td>' + esc(p.id_postulacion) + '</td><td>' + esc(p.id_labor) + '</td><td>' + esc(p.cedula) + '</td><td>' +
                esc(p.fecha_postulacion) + '</td><td>' + esc(p.estado) + '</td></tr>');
            filas('tb-contrataciones', d.contrataciones.recientes, 5, c =>
                '<tr><td>' + esc(c.id_contratacion) + '</td><td>' + esc(c.id_postulacion) + '</td><td>' + esc(c.fecha_inicio) +
                '</td><td class="num">' + esc(c.monto_acordado) + '</td><td>' + esc(c.estado) + '</td></tr>');
            filas('tb-pagos', d.pagos.recientes, 6, p =>
                '<tr><td>' + esc(p.id_pago) + '</td><td>' + esc(p.id_contratacion) + '</td><td>' + esc(p.fecha_pago) +
                '</td><td class="num">' + esc(p.monto) + '</td><td>' + esc(p.estado) + '</td><td>' + (p.es_simulado ? 'Sí' : 'No') + '</td></tr>');
        },

        pagos(d) {
            rellenarFormulario();
            const f = d.filtros || {};
            setTexto('rep-filtros', 'Filtros: estado = ' + (f.estado ?? 'todos') + ' · fecha de pago: ' + (f.desde ?? 'inicio') + ' a ' + (f.hasta ?? 'hoy'));
            const c = d.confirmados;
            setTexto('t-total', d.total);
            setTexto('t-confirmado-monto', 'Bs ' + c.monto);
            setTexto('t-confirmado-comision', 'Bs ' + c.comision);
            setTexto('t-confirmado-neto', 'Bs ' + c.neto);
            filas('tb-estados', d.por_estado, 5, t =>
                '<tr data-estado="' + esc(t.estado) + '"><td>' + esc(t.estado) + '</td><td class="num">' + esc(t.cantidad) +
                '</td><td class="num">' + esc(t.monto) + '</td><td class="num">' + esc(t.comision) + '</td><td class="num">' + esc(t.neto) + '</td></tr>');
            const persona = x => x ? (x.nombre ?? '(usuario ' + x.cedula + ')') : '—';
            filas('tb-filas', d.filas, 12, p => '<tr>' +
                '<td>' + esc(p.id_pago) + '</td><td>' + esc(p.fecha_pago) + '</td>' +
                '<td>#' + esc(p.id_contratacion) + esc(p.titulo !== null && p.titulo !== undefined ? ' — ' + p.titulo : '') + '</td>' +
                '<td>' + esc(persona(p.contratador)) + '</td><td>' + esc(persona(p.trabajador)) + '</td>' +
                '<td>' + esc(p.metodo) + '</td><td>' + esc(p.estado) + '</td>' +
                '<td class="num">' + esc(p.monto) + '</td><td class="num">' + esc(p.porcentaje_comision) + '</td>' +
                '<td class="num">' + esc(p.monto_comision) + '</td><td class="num">' + esc(p.monto_neto) + '</td>' +
                '<td>' + (p.es_simulado ? 'Sí' : 'No') + '</td></tr>');
            setHtml('tf-confirmados', '<td colspan="7">Confirmados (' + esc(c.cantidad) + ')</td><td class="num">' + esc(c.monto) +
                '</td><td></td><td class="num">' + esc(c.comision) + '</td><td class="num">' + esc(c.neto) + '</td><td></td>');
            nota(d);
        },
    };

    // ── Estados de carga / error ──
    function estado(texto) {
        const e = $('reporte-estado');
        if (!e) return;
        e.textContent = texto;
        e.style.display = texto ? '' : 'none';
    }

    function errores(lista) {
        const e = $('reporte-errores');
        if (!e) return;
        e.innerHTML = lista.map(m => '<div>' + esc(m) + '</div>').join('');
        e.style.display = lista.length ? '' : 'none';
    }

    // Filtros de la query actual → query string de la API (los mismos para JSON y PDF).
    function consulta() {
        const qs = Object.keys(query).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(query[k])).join('&');
        return qs ? '?' + qs : '';
    }

    async function cargar() {
        rellenarFormulario();
        estado('Cargando datos del reporte…');
        errores([]);
        const url = '/api/admin/reportes/' + tipo + consulta();
        let res;
        let data = null;
        try {
            res = await fetch(url, { headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' } });
            try { data = await res.json(); } catch (_) { data = null; }
        } catch (_) {
            estado('No se pudo cargar el reporte: error de conexión. Recargue la página para reintentar.');
            return;
        }

        if (res.status === 422) {
            const errs = data && data.errors ? Object.values(data.errors).flat() : [];
            errores(errs.length ? errs : [(data && data.message) || 'Los filtros indicados no son válidos.']);
            estado('Corrija los filtros para generar el reporte.');
            return;
        }
        if (!res.ok || !data) {
            const motivo = res.status === 401 ? 'la sesión del reporte expiró; recargue la página'
                : res.status === 403 ? 'no tiene permiso para ver este reporte'
                : 'error del servidor (HTTP ' + res.status + ')';
            estado('No se pudo cargar el reporte: ' + motivo + '.');
            return;
        }

        RENDER[tipo](data);
        if (data.generado_en_texto) {
            setTexto('reporte-meta', 'RedWork · Generado el ' + data.generado_en_texto + ' por ' + ((data.generado_por && data.generado_por.nombre) || ''));
        }
        estado('');
    }

    // ── Descarga del PDF (GET /api/admin/reportes/{tipo}/pdf, generado por el servidor con TCPDF) ──
    const BTN_PDF_TEXTO = 'Descargar PDF';

    function nombrePdf(res) {
        const cd = res.headers && typeof res.headers.get === 'function' ? res.headers.get('Content-Disposition') : null;
        const m = cd ? /filename="?([^";]+)"?/i.exec(cd) : null;
        return m ? m[1] : 'RedWork_Reporte_' + tipo.charAt(0).toUpperCase() + tipo.slice(1) + '.pdf';
    }

    async function descargarPdf() {
        const btn = $('btn-pdf');
        if (btn && btn.disabled) return; // ya hay una descarga en curso
        if (btn) { btn.disabled = true; btn.textContent = 'Generando PDF…'; }
        errores([]);
        estado('Generando PDF…');
        try {
            let res;
            try {
                // redirect 'manual': si el token no es válido Laravel redirige al login (no 401) → se trata como sesión expirada.
                res = await fetch('/api/admin/reportes/' + tipo + '/pdf' + consulta(), {
                    headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/pdf' },
                    redirect: 'manual',
                });
            } catch (_) {
                estado('No se pudo generar el PDF: error de conexión. Intente de nuevo.');
                return;
            }

            if (res.status === 422) {
                let data = null;
                try { data = await res.json(); } catch (_) { data = null; }
                const errs = data && data.errors ? Object.values(data.errors).flat() : [];
                errores(errs.length ? errs : [(data && data.message) || 'Los filtros indicados no son válidos.']);
                estado('Corrija los filtros para generar el PDF.');
                return;
            }
            const tipoContenido = res.headers && typeof res.headers.get === 'function' ? (res.headers.get('Content-Type') || '') : '';
            if (!res.ok || !tipoContenido.includes('application/pdf')) {
                const motivo = res.status === 401 || res.status === 0 || res.type === 'opaqueredirect' ? 'la sesión del reporte expiró; recargue la página'
                    : res.status === 403 ? 'no tiene permiso para ver este reporte'
                    : res.ok ? 'respuesta inesperada del servidor'
                    : 'error del servidor (HTTP ' + res.status + ')';
                estado('No se pudo generar el PDF: ' + motivo + '.');
                return;
            }

            let blob;
            try { blob = await res.blob(); } catch (_) {
                estado('No se pudo generar el PDF: error de conexión. Intente de nuevo.');
                return;
            }
            const href = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = href;
            a.download = nombrePdf(res);
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            a.remove();
            // Se revoca tras un breve margen para que el navegador inicie la descarga.
            setTimeout(() => URL.revokeObjectURL(href), 1000);
            estado('');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = BTN_PDF_TEXTO; }
        }
    }

    const btnPdf = $('btn-pdf');
    if (btnPdf) btnPdf.addEventListener('click', descargarPdf);

    window.descargarPdf = descargarPdf;
    window.cargarReporte = cargar;
    window.__reporteListo = cargar();
})();
