@extends('reportes.layout')

@section('titulo', 'Reporte de usuarios')
@section('imprimible', '1')

{{-- Plantilla de presentación: datos desde GET /api/admin/reportes/usuarios (resources/js/reportes.js). --}}
@section('contenido')
    <form method="GET" class="filtros">
        <label>Rol
            <select name="rol" id="f-rol">
                <option value="">Todos</option>
            </select>
        </label>
        <label>Estado
            <select name="estado" id="f-estado">
                <option value="">Todos</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
        </label>
        <button class="btn sec" type="submit">Filtrar</button>
    </form>

    <p class="filtros-aplicados" id="rep-filtros">Filtros: rol = todos · estado = todos</p>

    <div class="tarjetas">
        <div class="tarjeta"><div class="v" data-total="usuarios" id="t-total">—</div><div class="l">Usuarios</div></div>
        <div class="tarjeta"><div class="v" id="t-activos">—</div><div class="l">Activos</div></div>
        <div class="tarjeta"><div class="v" id="t-inactivos">—</div><div class="l">Inactivos</div></div>
    </div>

    <h2>Totales por rol</h2>
    <table>
        <thead><tr><th>Rol</th><th class="num">Usuarios</th></tr></thead>
        <tbody id="tb-roles">
            <tr><td colspan="2" class="vacio">Sin registros</td></tr>
        </tbody>
    </table>
    <p class="nota">Un usuario con varios roles se cuenta en cada uno de ellos.</p>

    <h2>Listado</h2>
    <table>
        <thead><tr><th>Cédula</th><th>Nombre</th><th>Email</th><th>Ciudad</th><th>Rol(es)</th><th>Estado</th><th>Fecha de registro</th></tr></thead>
        <tbody id="tb-filas">
            <tr><td colspan="7" class="vacio">Sin registros</td></tr>
        </tbody>
        <tfoot><tr><td colspan="6">Total de usuarios</td><td class="num" id="tf-total">—</td></tr></tfoot>
    </table>
    <p class="nota" id="rep-limite" style="display:none"></p>
@endsection
