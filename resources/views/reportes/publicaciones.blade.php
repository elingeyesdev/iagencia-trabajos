@extends('reportes.layout')

@section('titulo', 'Reporte de publicaciones')
@section('imprimible', '1')

{{-- Plantilla de presentación: datos desde GET /api/admin/reportes/publicaciones (resources/js/reportes.js). --}}
@section('contenido')
    <form method="GET" class="filtros">
        <label>Estado
            <select name="estado" id="f-estado">
                <option value="">Todos</option>
                <option value="1">Activa</option>
                <option value="0">Inactiva</option>
            </select>
        </label>
        <label>Categoría
            <select name="categoria" id="f-categoria">
                <option value="">Todas</option>
            </select>
        </label>
        <label>Desde <input type="date" name="desde" id="f-desde" value=""></label>
        <label>Hasta <input type="date" name="hasta" id="f-hasta" value=""></label>
        <button class="btn sec" type="submit">Filtrar</button>
    </form>

    <p class="filtros-aplicados" id="rep-filtros">
        Filtros: estado = todos · categoría = todas · fecha de publicación: inicio a hoy
    </p>

    <div class="tarjetas">
        <div class="tarjeta"><div class="v" id="t-total">—</div><div class="l">Publicaciones</div></div>
        <div class="tarjeta"><div class="v" id="t-activos">—</div><div class="l">Activas</div></div>
        <div class="tarjeta"><div class="v" id="t-inactivos">—</div><div class="l">Inactivas</div></div>
        <div class="tarjeta"><div class="v" data-total="suma-pago" id="t-suma-pago">—</div><div class="l">Suma de pagos ofertados</div></div>
    </div>
    <p class="nota">El pago de una publicación es el monto ofertado; no es dinero comprometido ni cobrado.</p>

    <h2>Listado</h2>
    <table>
        <thead><tr><th>ID</th><th>Título</th><th>Tipo</th><th>Categoría</th><th>Contratador</th><th>Fecha</th><th class="num">Pago (Bs)</th><th>Estado</th></tr></thead>
        <tbody id="tb-filas">
            <tr><td colspan="8" class="vacio">Sin registros</td></tr>
        </tbody>
        <tfoot><tr><td colspan="6" id="tf-etiqueta">Total (— publicaciones)</td><td class="num" id="tf-suma-pago">—</td><td></td></tr></tfoot>
    </table>
    <p class="nota" id="rep-limite" style="display:none"></p>
@endsection
