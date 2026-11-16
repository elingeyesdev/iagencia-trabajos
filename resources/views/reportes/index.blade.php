@extends('reportes.layout')

@section('titulo', 'Reportes')

@section('contenido')
    <table>
        <thead><tr><th>Reporte</th><th>Contenido</th><th>Filtros</th></tr></thead>
        <tbody>
            <tr><td><a href="{{ route('reportes.usuarios') }}">Usuarios</a></td><td>Listado con roles, estado y totales por rol/estado</td><td>Rol, estado</td></tr>
            <tr><td><a href="{{ route('reportes.publicaciones') }}">Publicaciones</a></td><td>Listado con categoría, contratador, pago y estado; suma de pagos ofertados</td><td>Estado, categoría, fechas</td></tr>
            <tr><td><a href="{{ route('reportes.categorias') }}">Categorías</a></td><td>Categorías con número de publicaciones</td><td>—</td></tr>
            <tr><td><a href="{{ route('reportes.actividad') }}">Actividad del sistema</a></td><td>Registros, publicaciones, postulaciones, contrataciones y pagos por estado</td><td>Fechas</td></tr>
            <tr><td><a href="{{ route('reportes.pagos') }}">Pagos simulados, ingresos y comisiones</a></td><td>Pagos con comisión y neto; totales por estado</td><td>Estado, fechas</td></tr>
        </tbody>
    </table>
@endsection
