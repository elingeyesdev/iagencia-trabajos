@extends('reportes.layout')

@section('titulo', 'Reporte de categorías')
@section('imprimible', '1')

{{-- Plantilla de presentación: datos desde GET /api/admin/reportes/categorias (resources/js/reportes.js). --}}
@section('contenido')
    <div class="tarjetas">
        <div class="tarjeta"><div class="v" id="t-total">—</div><div class="l">Categorías</div></div>
        <div class="tarjeta"><div class="v" id="t-activos">—</div><div class="l">Activas</div></div>
        <div class="tarjeta"><div class="v" id="t-inactivos">—</div><div class="l">Inactivas</div></div>
    </div>

    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Estado</th><th class="num">Nº de publicaciones</th></tr></thead>
        <tbody id="tb-filas">
            <tr><td colspan="4" class="vacio">Sin registros</td></tr>
        </tbody>
        <tfoot><tr><td colspan="3">Total de publicaciones en categorías existentes</td><td class="num" data-total="publicaciones" id="tf-publicaciones">—</td></tr></tfoot>
    </table>
    <p class="nota">El número de publicaciones incluye activas e inactivas. Las publicaciones con una categoría inexistente no se cuentan.</p>
@endsection
