@extends('reportes.layout')

@section('titulo', 'Reporte de actividad del sistema')
@section('imprimible', '1')

{{-- Plantilla de presentación: datos desde GET /api/admin/reportes/actividad (resources/js/reportes.js). --}}
@section('contenido')
    <form method="GET" class="filtros">
        <label>Desde <input type="date" name="desde" id="f-desde" value=""></label>
        <label>Hasta <input type="date" name="hasta" id="f-hasta" value=""></label>
        <button class="btn sec" type="submit">Filtrar</button>
    </form>
    <p class="filtros-aplicados" id="rep-filtros">Periodo: inicio a hoy</p>

    <h2>Resumen</h2>
    <table>
        <thead><tr><th>Entidad</th><th>Fecha usada</th><th class="num">Total</th><th>Desglose por estado</th></tr></thead>
        <tbody id="tb-resumen">
            <tr><td colspan="4" class="vacio">Sin registros</td></tr>
        </tbody>
    </table>
    <p class="nota">
        Limitaciones: las postulaciones canceladas se eliminan físicamente (no aparecen); no existe registro de inicios de sesión;
        las fechas de publicaciones, postulaciones, contrataciones y pagos no tienen hora. Las contrataciones se fechan por fecha de inicio.
    </p>

    <h2>Usuarios registrados recientes</h2>
    <table>
        <thead><tr><th>Cédula</th><th>Nombre</th><th>Fecha de registro</th></tr></thead>
        <tbody id="tb-usuarios">
            <tr><td colspan="3" class="vacio">Sin registros</td></tr>
        </tbody>
    </table>

    <h2>Publicaciones recientes</h2>
    <table>
        <thead><tr><th>ID</th><th>Título</th><th>Fecha</th><th>Estado</th></tr></thead>
        <tbody id="tb-publicaciones">
            <tr><td colspan="4" class="vacio">Sin registros</td></tr>
        </tbody>
    </table>

    <h2>Postulaciones recientes</h2>
    <table>
        <thead><tr><th>ID</th><th>Publicación</th><th>Cédula trabajador</th><th>Fecha</th><th>Estado</th></tr></thead>
        <tbody id="tb-postulaciones">
            <tr><td colspan="5" class="vacio">Sin registros</td></tr>
        </tbody>
    </table>

    <h2>Contrataciones recientes</h2>
    <table>
        <thead><tr><th>ID</th><th>Postulación</th><th>Fecha de inicio</th><th class="num">Monto acordado (Bs)</th><th>Estado</th></tr></thead>
        <tbody id="tb-contrataciones">
            <tr><td colspan="5" class="vacio">Sin registros</td></tr>
        </tbody>
    </table>

    <h2>Pagos recientes (simulados)</h2>
    <table>
        <thead><tr><th>ID</th><th>Contratación</th><th>Fecha de pago</th><th class="num">Monto (Bs)</th><th>Estado</th><th>Simulado</th></tr></thead>
        <tbody id="tb-pagos">
            <tr><td colspan="6" class="vacio">Sin registros</td></tr>
        </tbody>
    </table>
@endsection
