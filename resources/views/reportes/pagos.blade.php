@extends('reportes.layout')

@section('titulo', 'Reporte de pagos simulados, ingresos y comisiones')
@section('imprimible', '1')

{{-- Plantilla de presentación: datos desde GET /api/admin/reportes/pagos (resources/js/reportes.js). --}}
@section('contenido')
    <div class="banda-simulado">OPERACIONES SIMULADAS — NO REPRESENTAN DINERO REAL</div>

    <form method="GET" class="filtros">
        <label>Estado
            <select name="estado" id="f-estado">
                <option value="">Todos</option>
                @foreach (\App\Models\Pago::ESTADOS as $e)
                    <option value="{{ $e }}">{{ $e }}</option>
                @endforeach
            </select>
        </label>
        <label>Desde <input type="date" name="desde" id="f-desde" value=""></label>
        <label>Hasta <input type="date" name="hasta" id="f-hasta" value=""></label>
        <button class="btn sec" type="submit">Filtrar</button>
    </form>
    <p class="filtros-aplicados" id="rep-filtros">Filtros: estado = todos · fecha de pago: inicio a hoy</p>

    <div class="tarjetas">
        <div class="tarjeta"><div class="v" id="t-total">—</div><div class="l">Pagos</div></div>
        <div class="tarjeta"><div class="v" data-total="confirmado-monto" id="t-confirmado-monto">—</div><div class="l">Cobrado (confirmados)</div></div>
        <div class="tarjeta"><div class="v" data-total="confirmado-comision" id="t-confirmado-comision">—</div><div class="l">Comisión RedWork (confirmados)</div></div>
        <div class="tarjeta"><div class="v" data-total="confirmado-neto" id="t-confirmado-neto">—</div><div class="l">Neto trabajadores (confirmados)</div></div>
    </div>
    <p class="nota">Solo los pagos confirmados se consideran cobrados. Los pendientes no están confirmados y los anulados no cuentan como ingreso.
        La comisión es la copiada en cada pago al registrarlo (no se recalcula).</p>

    <h2>Totales por estado</h2>
    <table>
        <thead><tr><th>Estado</th><th class="num">Cantidad</th><th class="num">Monto (Bs)</th><th class="num">Comisión (Bs)</th><th class="num">Neto (Bs)</th></tr></thead>
        <tbody id="tb-estados">
            <tr><td colspan="5" class="vacio">Sin registros</td></tr>
        </tbody>
    </table>

    <h2>Listado</h2>
    <table>
        <thead><tr>
            <th>ID</th><th>Fecha de pago</th><th>Contratación</th><th>Contratador</th><th>Trabajador</th><th>Método</th><th>Estado</th>
            <th class="num">Monto (Bs)</th><th class="num">% comisión</th><th class="num">Comisión (Bs)</th><th class="num">Neto (Bs)</th><th>Simulado</th>
        </tr></thead>
        <tbody id="tb-filas">
            <tr><td colspan="12" class="vacio">Sin registros</td></tr>
        </tbody>
        <tfoot><tr id="tf-confirmados"><td colspan="7">Confirmados (—)</td><td class="num">—</td><td></td><td class="num">—</td><td class="num">—</td><td></td></tr></tfoot>
    </table>
    <p class="nota" id="rep-limite" style="display:none"></p>
@endsection
