<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Publicacion;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Indicadores (KPIs) de RedWork — solo lectura, datos reales.
 *
 * - index(): estadísticas auxiliares heredadas (Bloque 5). Se conserva sin cambios
 *   por compatibilidad con las pruebas y consumidores existentes.
 * - indicadores() / contratador() / trabajador(): sistema de KPIs rediseñado.
 *   Un único conjunto de cálculos parametrizado por "alcance" (global, propietario
 *   contratador o trabajador): no hay implementaciones duplicadas por rol.
 *
 * Fichas técnicas, fórmulas y limitaciones: docs/kpis-redwork.md.
 * Todos los importes corresponden a pagos SIMULADOS (es_simulado = 1).
 */
class KpiApiController extends Controller
{
    // ── Parámetros metodológicos (docs/kpis-redwork.md §3; pendientes de aprobación) ──

    /** Antigüedad mínima de una postulación para entrar en una cohorte evaluable. */
    public const MADURACION_POSTULACIONES_DIAS = 7;

    /** Ventana de observación de una publicación para considerar que "atrajo" postulantes. */
    public const VENTANA_PUBLICACIONES_DIAS = 14;

    /** Antigüedad a partir de la cual un registro pendiente genera alerta. */
    public const UMBRAL_ANTIGUEDAD_DIAS = 7;

    /** Casos mínimos por período para evaluar estadísticamente una variación. */
    public const MUESTRA_MINIMA = 10;

    /** Valor crítico bilateral al 95 % de confianza. */
    public const Z_CRITICO = 1.96;

    /** Meses que cubre la serie de evolución. */
    public const MESES_EVOLUCION = 12;

    /** Amplitud máxima de un intervalo personalizado (días). */
    public const MAX_DIAS_PERSONALIZADO = 731;

    public const PERIODOS = ['30d', '90d', 'anio', 'personalizado'];

    /** Estados de postulación que requieren evaluación (cancelada: retirada por el trabajador). */
    private const ESTADOS_EVALUABLES = ['pendiente', 'aceptada', 'rechazada'];

    // ════════════════════════════════════════════════════════════
    //  Estadísticas auxiliares (sin cambios)
    // ════════════════════════════════════════════════════════════

    public function index(): JsonResponse
    {
        $pagosConfirmados = 0;
        if (Schema::hasTable('pagos')) {
            $pagosConfirmados = DB::table('pagos')
                ->where('estado', Pago::ESTADO_CONFIRMADO)
                ->selectRaw(Pago::sumaCentavosSql('monto') . ' as c')
                ->value('c');
        }

        return response()->json([
            'publicaciones_activas'  => Publicacion::where('estado', 1)->count(),
            'contrataciones_activas' => Contratacion::where('estado', 'activa')->count(),
            'pagos_confirmados_bs'   => Pago::centavosATexto($pagosConfirmados),
            'simulado'               => true,
            'generado_en'            => now()->toIso8601String(),
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  Endpoints del sistema de KPIs
    // ════════════════════════════════════════════════════════════

    /** GET /api/admin/kpis/indicadores — visión global (filtros: periodo, desde, hasta, categoria). */
    public function indicadores(Request $request): JsonResponse
    {
        $filtros = $this->validarFiltros($request, true);

        return response()->json($this->construir([
            'tipo'      => 'global',
            'cedula'    => null,
            'categoria' => $filtros['categoria'],
        ], $filtros['periodo']), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /** GET /api/contratador/kpis — solo publicaciones, postulaciones y contrataciones propias. */
    public function contratador(Request $request): JsonResponse
    {
        $filtros = $this->validarFiltros($request, false);

        return response()->json($this->construir([
            'tipo'      => 'contratador',
            'cedula'    => (int) $request->user()->cedula,
            'categoria' => null,
        ], $filtros['periodo']), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /** GET /api/trabajador/kpis — seguimiento personal del trabajador autenticado. */
    public function trabajador(Request $request): JsonResponse
    {
        $filtros = $this->validarFiltros($request, false);

        return response()->json($this->construir([
            'tipo'      => 'trabajador',
            'cedula'    => (int) $request->user()->cedula,
            'categoria' => null,
        ], $filtros['periodo']), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    // ════════════════════════════════════════════════════════════
    //  Filtros y períodos
    // ════════════════════════════════════════════════════════════

    private function validarFiltros(Request $request, bool $admiteCategoria): array
    {
        $hoy = Carbon::today()->toDateString();

        $reglas = [
            'periodo' => ['nullable', Rule::in(self::PERIODOS)],
            'desde'   => ['exclude_unless:periodo,personalizado', 'required', 'date_format:Y-m-d', 'before_or_equal:' . $hoy],
            'hasta'   => ['exclude_unless:periodo,personalizado', 'required', 'date_format:Y-m-d', 'after_or_equal:desde', 'before_or_equal:' . $hoy],
        ];
        if ($admiteCategoria) {
            $reglas['categoria'] = ['nullable', 'integer', 'exists:categorias,id_categoria'];
        }

        $datos = $request->validate($reglas, [
            'periodo.in'             => 'El período debe ser 30d, 90d, anio o personalizado.',
            'desde.required'         => 'La fecha inicial es obligatoria en un intervalo personalizado.',
            'hasta.required'         => 'La fecha final es obligatoria en un intervalo personalizado.',
            'desde.date_format'      => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'hasta.date_format'      => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'desde.before_or_equal'  => 'La fecha inicial no puede ser futura.',
            'hasta.before_or_equal'  => 'La fecha final no puede ser futura.',
            'hasta.after_or_equal'   => 'La fecha final debe ser igual o posterior a la inicial.',
            'categoria.integer'      => 'La categoría no es válida.',
            'categoria.exists'       => 'La categoría seleccionada no existe.',
        ]);

        $clave = $datos['periodo'] ?? '30d';

        if ($clave === 'personalizado') {
            $dias = $this->diasEntre($datos['desde'], $datos['hasta']) + 1;
            if ($dias > self::MAX_DIAS_PERSONALIZADO) {
                throw ValidationException::withMessages([
                    'hasta' => 'El intervalo personalizado no puede superar ' . self::MAX_DIAS_PERSONALIZADO . ' días.',
                ]);
            }
        }

        return [
            'periodo'   => $this->periodo($clave, $datos['desde'] ?? null, $datos['hasta'] ?? null),
            'categoria' => isset($datos['categoria']) ? (int) $datos['categoria'] : null,
        ];
    }

    /**
     * Período evaluado y período anterior comparable (misma duración e inmediatamente
     * anterior; para "año actual" se compara con el mismo tramo del año anterior).
     */
    private function periodo(string $clave, ?string $desde, ?string $hasta): array
    {
        $hoy = Carbon::today();

        switch ($clave) {
            case '90d':
                $d = $hoy->copy()->subDays(89); $h = $hoy->copy();
                $etiqueta = 'Últimos 90 días';
                break;
            case 'anio':
                $d = $hoy->copy()->startOfYear(); $h = $hoy->copy();
                $etiqueta = 'Año actual';
                break;
            case 'personalizado':
                $d = Carbon::parse($desde)->startOfDay(); $h = Carbon::parse($hasta)->startOfDay();
                $etiqueta = 'Intervalo personalizado';
                break;
            default:
                $clave = '30d';
                $d = $hoy->copy()->subDays(29); $h = $hoy->copy();
                $etiqueta = 'Últimos 30 días';
        }

        if ($clave === 'anio') {
            $ad = $d->copy()->subYearNoOverflow();
            $ah = $h->copy()->subYearNoOverflow();
            $notaAnterior = 'Mismo tramo del año anterior (' . $ad->format('d/m/Y') . ' – ' . $ah->format('d/m/Y') . ').';
        } else {
            $dias = $this->diasEntre($d->toDateString(), $h->toDateString()) + 1;
            $ah = $d->copy()->subDay();
            $ad = $ah->copy()->subDays($dias - 1);
            $notaAnterior = 'Período inmediatamente anterior de igual duración.';
        }

        return [
            'clave'    => $clave,
            'etiqueta' => $etiqueta,
            'desde'    => $d->toDateString(),
            'hasta'    => $h->toDateString(),
            'dias'     => $this->diasEntre($d->toDateString(), $h->toDateString()) + 1,
            // El año en curso es un período parcial; se compara contra el mismo tramo parcial.
            'parcial'  => $clave === 'anio',
            'anterior' => [
                'desde' => $ad->toDateString(),
                'hasta' => $ah->toDateString(),
                'nota'  => $notaAnterior,
            ],
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  Ensamblado de la respuesta por alcance
    // ════════════════════════════════════════════════════════════

    private function construir(array $alcance, array $p): array
    {
        $tipo       = $alcance['tipo'];
        $maduracion = $tipo === 'trabajador' ? 0 : self::MADURACION_POSTULACIONES_DIAS;
        $hayPagos   = Schema::hasTable('pagos');
        $a          = $p['anterior'];

        // Cohortes de postulaciones (B, C) — período actual y anterior.
        $post  = $this->cohortePostulaciones($alcance, $p['desde'], $p['hasta'], $maduracion);
        $postA = $this->cohortePostulaciones($alcance, $a['desde'], $a['hasta'], $maduracion);

        // Resultado de contrataciones (D).
        $contr  = $this->resultadoContrataciones($alcance, $p['desde'], $p['hasta']);
        $contrA = $this->resultadoContrataciones($alcance, $a['desde'], $a['hasta']);

        // Calidad (F).
        $cal  = $this->calificaciones($alcance, $p['desde'], $p['hasta']);
        $calA = $this->calificaciones($alcance, $a['desde'], $a['hasta']);

        $indicadores = [];
        $categorias  = null;

        if ($tipo !== 'trabajador') {
            // Efectividad de publicaciones (A).
            $pub  = $this->efectividadPublicaciones($alcance, $p['desde'], $p['hasta']);
            $pubA = $this->efectividadPublicaciones($alcance, $a['desde'], $a['hasta']);
            $indicadores[] = $this->indicadorProporcion(
                'efectividad_publicaciones',
                $tipo === 'global' ? 'Efectividad de publicaciones' : 'Efectividad de mis ofertas',
                'A',
                '¿Qué proporción de publicaciones atrae al menos una postulación en sus primeros ' . self::VENTANA_PUBLICACIONES_DIAS . ' días?',
                'mayor_mejor',
                [$pub['total']['con_postulacion'], $pub['total']['publicaciones']],
                [$pubA['total']['con_postulacion'], $pubA['total']['publicaciones']],
                ['desde' => $p['desde'], 'hasta' => $pub['hasta'], 'en_maduracion' => $pub['en_maduracion']],
                fn ($n, $d) => "{$n} de {$d} publicaciones de la cohorte recibieron al menos una postulación en sus primeros " . self::VENTANA_PUBLICACIONES_DIAS . ' días.'
            );
        }

        $indicadores[] = $this->indicadorProporcion(
            'gestion_postulaciones',
            match ($tipo) {
                'trabajador'  => 'Postulaciones con respuesta',
                'contratador' => 'Postulaciones que evalué',
                default       => 'Tasa de gestión de postulaciones',
            },
            'B',
            $tipo === 'trabajador'
                ? '¿Qué proporción de mis postulaciones ya recibió una decisión del contratador?'
                : '¿Qué porcentaje de las postulaciones recibidas obtuvo una decisión (aceptada o rechazada)?',
            'mayor_mejor',
            [$post['total']['evaluadas'], $post['total']['recibidas']],
            [$postA['total']['evaluadas'], $postA['total']['recibidas']],
            ['desde' => $p['desde'], 'hasta' => $post['hasta'], 'en_maduracion' => $post['en_maduracion']],
            fn ($n, $d) => "{$n} de {$d} postulaciones evaluables tienen decisión; pendientes de decisión: " . ($d - $n) . '.'
        );

        $indicadores[] = $this->indicadorProporcion(
            'conversion_contratacion',
            $tipo === 'trabajador' ? 'Postulaciones convertidas en contratación' : 'Conversión a contratación',
            'C',
            '¿Qué proporción de las postulaciones con decisión terminó en una contratación?',
            'mayor_mejor',
            [$post['total']['con_contratacion'], $post['total']['evaluadas']],
            [$postA['total']['con_contratacion'], $postA['total']['evaluadas']],
            ['desde' => $p['desde'], 'hasta' => $post['hasta'], 'en_maduracion' => $post['en_maduracion']],
            fn ($n, $d) => "{$n} de {$d} postulaciones con decisión generaron una contratación (cada contratación se cuenta una vez)."
        );

        $indicadores[] = $this->indicadorProporcion(
            'finalizacion_contrataciones',
            'Finalización de contrataciones',
            'D',
            '¿Qué proporción de las contrataciones cerradas en el período concluyó como finalizada (y no cancelada)?',
            'mayor_mejor',
            [$contr['total']['finalizadas'], $contr['total']['cerradas']],
            [$contrA['total']['finalizadas'], $contrA['total']['cerradas']],
            ['desde' => $p['desde'], 'hasta' => $p['hasta'], 'en_maduracion' => false],
            fn ($n, $d) => "{$n} de {$d} contrataciones cerradas en el período finalizaron; " . ($d - $n) . ' fueron canceladas. Las activas no se cuentan.'
        );

        $pagos = $pagosA = null;
        if ($hayPagos) {
            $pagos  = $this->pagos($alcance, $p['desde'], $p['hasta']);
            $pagosA = $this->pagos($alcance, $a['desde'], $a['hasta']);

            if ($tipo !== 'trabajador') {
                $indicadores[] = $this->indicadorProporcion(
                    'confirmacion_pagos',
                    $tipo === 'global' ? 'Confirmación de pagos simulados' : 'Confirmación de mis pagos simulados',
                    'E',
                    '¿Qué proporción de los pagos simulados válidos (no anulados) registrados en el período ya fue confirmada?',
                    'mayor_mejor',
                    [$pagos['confirmado']['cantidad'], $pagos['confirmado']['cantidad'] + $pagos['pendiente']['cantidad']],
                    [$pagosA['confirmado']['cantidad'], $pagosA['confirmado']['cantidad'] + $pagosA['pendiente']['cantidad']],
                    ['desde' => $p['desde'], 'hasta' => $p['hasta'], 'en_maduracion' => false],
                    fn ($n, $d) => "{$n} de {$d} registros simulados no anulados están confirmados; los anulados ("
                        . $pagos['anulado']['cantidad'] . ') se informan aparte.',
                    ['simulado' => true]
                );
            }
        }

        $indicadores[] = $this->indicadorPromedio($tipo, $cal, $calA, $p);

        $respuesta = [
            'simulado'    => true,
            'aviso'       => 'Los importes corresponden a registros de pagos SIMULADOS: no representan dinero depositado, ingresos reales ni deudas bancarias.',
            'generado_en' => now()->toIso8601String(),
            'alcance'     => $tipo,
            'periodo'     => $p,
            'filtros'     => ['categoria' => $alcance['categoria']],
            'parametros'  => [
                'maduracion_postulaciones_dias' => $maduracion,
                'ventana_publicaciones_dias'    => self::VENTANA_PUBLICACIONES_DIAS,
                'umbral_antiguedad_dias'        => self::UMBRAL_ANTIGUEDAD_DIAS,
                'muestra_minima'                => self::MUESTRA_MINIMA,
                'confianza'                     => '95 %',
            ],
            'indicadores' => $indicadores,
            'embudo'      => $this->embudo($post),
            'pendientes'  => [
                'postulaciones' => $tipo === 'trabajador' ? null : $this->postulacionesPendientes($alcance),
                'pagos'         => $hayPagos ? $this->pagosPendientes($alcance) : null,
            ],
            'pagos'       => $hayPagos ? $this->resumenPagos($pagos, $pagosA) : null,
            'contrataciones' => [
                'finalizadas_periodo' => $contr['total']['finalizadas'],
                'canceladas_periodo'  => $contr['total']['canceladas'],
                'tasa_cancelacion'    => $this->porcentaje($contr['total']['canceladas'], $contr['total']['cerradas']),
                'estado_actual'       => $this->estadoActualContrataciones($alcance),
            ],
            'calidad'     => [
                'promedio'     => $cal['promedio'],
                'n'            => $cal['n'],
                'distribucion' => $cal['distribucion'],
                'por_rol'      => $tipo === 'global' ? $cal['por_rol'] : null,
                'cobertura'    => $tipo === 'global' ? $this->coberturaCalificacion($alcance, $p['desde'], $p['hasta']) : null,
            ],
            'evolucion'   => $this->evolucion($alcance, $p['hasta'], $maduracion, $hayPagos),
            'calidad_datos' => [
                'aceptadas_sin_contratacion' => $post['total']['aceptadas_sin_contratacion'],
                'cerradas_sin_fecha_fin'     => $this->cerradasSinFechaFin($alcance),
            ],
        ];

        if ($tipo === 'global') {
            $respuesta['categorias'] = $this->porCategoria($pub['por_categoria'], $post['por_categoria'], $contr['por_categoria']);
        }

        $respuesta['alertas'] = $this->alertas($respuesta);

        return $respuesta;
    }

    // ════════════════════════════════════════════════════════════
    //  Consultas base con aislamiento por alcance
    // ════════════════════════════════════════════════════════════

    /** Publicaciones del alcance (contratador: solo propias; global: filtro opcional de categoría). */
    private function publicacionesBase(array $alcance): Builder
    {
        $q = DB::table('publicaciones as pu');
        $this->aplicarAlcancePublicacion($q, $alcance);

        return $q;
    }

    /** Postulaciones con su publicación (las huérfanas sin publicación se excluyen por el JOIN). */
    private function postulacionesBase(array $alcance): Builder
    {
        $q = DB::table('postulaciones as po')
            ->join('publicaciones as pu', 'pu.id_labor', '=', 'po.id_labor');
        $this->aplicarAlcancePublicacion($q, $alcance);
        if ($alcance['tipo'] === 'trabajador') {
            $q->where('po.cedula', $alcance['cedula']);
        }

        return $q;
    }

    private function contratacionesBase(array $alcance): Builder
    {
        $q = DB::table('contrataciones as co')
            ->join('postulaciones as po', 'po.id_postulacion', '=', 'co.id_postulacion')
            ->join('publicaciones as pu', 'pu.id_labor', '=', 'po.id_labor');
        $this->aplicarAlcancePublicacion($q, $alcance);
        if ($alcance['tipo'] === 'trabajador') {
            $q->where('po.cedula', $alcance['cedula']);
        }

        return $q;
    }

    /** Solo pagos simulados (es_simulado = 1). */
    private function pagosBase(array $alcance): Builder
    {
        $q = DB::table('pagos as pa')
            ->join('contrataciones as co', 'co.id_contratacion', '=', 'pa.id_contratacion')
            ->join('postulaciones as po', 'po.id_postulacion', '=', 'co.id_postulacion')
            ->join('publicaciones as pu', 'pu.id_labor', '=', 'po.id_labor')
            ->where('pa.es_simulado', true);
        $this->aplicarAlcancePublicacion($q, $alcance);
        if ($alcance['tipo'] === 'trabajador') {
            $q->where('po.cedula', $alcance['cedula']);
        }

        return $q;
    }

    /** Calificaciones: el contratador y el trabajador solo ven las que recibieron. */
    private function calificacionesBase(array $alcance): Builder
    {
        $q = DB::table('calificaciones as ca')
            ->join('contrataciones as co', 'co.id_contratacion', '=', 'ca.id_contratacion')
            ->join('postulaciones as po', 'po.id_postulacion', '=', 'co.id_postulacion')
            ->join('publicaciones as pu', 'pu.id_labor', '=', 'po.id_labor');

        if ($alcance['tipo'] === 'global') {
            if ($alcance['categoria'] !== null) {
                $q->where('pu.id_categoria', $alcance['categoria']);
            }
        } else {
            $q->where('ca.cedula_calificado', $alcance['cedula']);
        }

        return $q;
    }

    private function aplicarAlcancePublicacion(Builder $q, array $alcance): void
    {
        if ($alcance['tipo'] === 'contratador') {
            $q->where('pu.cedula', $alcance['cedula']);
        }
        if ($alcance['categoria'] !== null) {
            $q->where('pu.id_categoria', $alcance['categoria']);
        }
    }

    // ════════════════════════════════════════════════════════════
    //  Cálculos (una sola implementación por indicador)
    // ════════════════════════════════════════════════════════════

    /**
     * Objetivo A. Cohorte: publicaciones con fecha_publicacion en el período y con al menos
     * VENTANA días de antigüedad. Éxito: primera postulación dentro de esa ventana.
     */
    private function efectividadPublicaciones(array $alcance, string $desde, string $hasta): array
    {
        $corte   = Carbon::today()->subDays(self::VENTANA_PUBLICACIONES_DIAS)->toDateString();
        $hastaEf = min($hasta, $corte);
        $vacio   = ['publicaciones' => 0, 'con_postulacion' => 0];
        $res     = ['hasta' => $hastaEf, 'en_maduracion' => $hastaEf < $hasta, 'total' => $vacio, 'por_categoria' => []];

        if ($hastaEf < $desde) {
            $res['hasta'] = null;

            return $res;
        }

        $filas = $this->publicacionesBase($alcance)
            ->leftJoin('postulaciones as po', 'po.id_labor', '=', 'pu.id_labor')
            ->whereBetween('pu.fecha_publicacion', [$desde, $hastaEf])
            ->groupBy('pu.id_labor', 'pu.fecha_publicacion', 'pu.id_categoria')
            ->selectRaw('pu.id_labor, pu.fecha_publicacion as fecha, pu.id_categoria as categoria, MIN(po.fecha_postulacion) as primera')
            ->get();

        foreach ($filas as $f) {
            $exito = $f->primera !== null
                && $this->diasEntre($f->fecha, $f->primera) <= self::VENTANA_PUBLICACIONES_DIAS;
            $cat = (int) $f->categoria;
            $res['por_categoria'][$cat] ??= $vacio;
            $res['por_categoria'][$cat]['publicaciones']++;
            $res['total']['publicaciones']++;
            if ($exito) {
                $res['por_categoria'][$cat]['con_postulacion']++;
                $res['total']['con_postulacion']++;
            }
        }

        return $res;
    }

    /**
     * Objetivos B y C. Cohorte: postulaciones evaluables (pendiente/aceptada/rechazada) con
     * fecha_postulacion en el período y antigüedad mínima de $maduracion días.
     */
    private function cohortePostulaciones(array $alcance, string $desde, string $hasta, int $maduracion): array
    {
        $corte   = Carbon::today()->subDays($maduracion)->toDateString();
        $hastaEf = min($hasta, $corte);
        $vacio   = ['recibidas' => 0, 'pendientes' => 0, 'aceptadas' => 0, 'rechazadas' => 0, 'evaluadas' => 0,
                    'con_contratacion' => 0, 'finalizadas' => 0, 'aceptadas_sin_contratacion' => 0];
        $res     = ['hasta' => $hastaEf, 'en_maduracion' => $hastaEf < $hasta, 'total' => $vacio, 'por_categoria' => []];

        if ($hastaEf < $desde) {
            $res['hasta'] = null;

            return $res;
        }

        $filas = $this->postulacionesBase($alcance)
            ->leftJoin('contrataciones as co', 'co.id_postulacion', '=', 'po.id_postulacion')
            ->whereIn('po.estado', self::ESTADOS_EVALUABLES)
            ->whereBetween('po.fecha_postulacion', [$desde, $hastaEf])
            ->groupBy('pu.id_categoria', 'po.estado')
            ->selectRaw(
                'pu.id_categoria as categoria, po.estado as estado, '
                . 'COUNT(DISTINCT po.id_postulacion) as n, '
                . 'COUNT(DISTINCT co.id_contratacion) as con_contratacion, '
                . "COUNT(DISTINCT CASE WHEN co.estado = 'finalizada' THEN co.id_contratacion END) as finalizadas"
            )
            ->get();

        foreach ($filas as $f) {
            $cat = (int) $f->categoria;
            $res['por_categoria'][$cat] ??= $vacio;
            foreach ([&$res['total'], &$res['por_categoria'][$cat]] as &$acc) {
                $n = (int) $f->n;
                $acc['recibidas'] += $n;
                if ($f->estado === 'pendiente') {
                    $acc['pendientes'] += $n;
                } elseif ($f->estado === 'rechazada') {
                    $acc['rechazadas'] += $n;
                    $acc['evaluadas']  += $n;
                } else { // aceptada: solo aquí una contratación es válida.
                    $acc['aceptadas']        += $n;
                    $acc['evaluadas']        += $n;
                    $acc['con_contratacion'] += (int) $f->con_contratacion;
                    $acc['finalizadas']      += (int) $f->finalizadas;
                    $acc['aceptadas_sin_contratacion'] += max(0, $n - (int) $f->con_contratacion);
                }
            }
            unset($acc);
        }

        return $res;
    }

    /** Objetivo D. Contrataciones cerradas (finalizada/cancelada) con fecha_fin en el período. */
    private function resultadoContrataciones(array $alcance, string $desde, string $hasta): array
    {
        $vacio = ['finalizadas' => 0, 'canceladas' => 0, 'cerradas' => 0];
        $res   = ['total' => $vacio, 'por_categoria' => []];

        $filas = $this->contratacionesBase($alcance)
            ->whereIn('co.estado', ['finalizada', 'cancelada'])
            ->whereBetween('co.fecha_fin', [$desde, $hasta])
            ->groupBy('pu.id_categoria', 'co.estado')
            ->selectRaw('pu.id_categoria as categoria, co.estado as estado, COUNT(DISTINCT co.id_contratacion) as n')
            ->get();

        foreach ($filas as $f) {
            $cat = (int) $f->categoria;
            $res['por_categoria'][$cat] ??= $vacio;
            $clave = $f->estado === 'finalizada' ? 'finalizadas' : 'canceladas';
            foreach ([&$res['total'], &$res['por_categoria'][$cat]] as &$acc) {
                $acc[$clave]    += (int) $f->n;
                $acc['cerradas'] += (int) $f->n;
            }
            unset($acc);
        }

        return $res;
    }

    private function estadoActualContrataciones(array $alcance): array
    {
        $conteos = $this->contratacionesBase($alcance)
            ->groupBy('co.estado')
            ->selectRaw('co.estado as estado, COUNT(DISTINCT co.id_contratacion) as n')
            ->pluck('n', 'estado');

        return [
            'activa'     => (int) ($conteos['activa'] ?? 0),
            'finalizada' => (int) ($conteos['finalizada'] ?? 0),
            'cancelada'  => (int) ($conteos['cancelada'] ?? 0),
        ];
    }

    private function cerradasSinFechaFin(array $alcance): int
    {
        return (int) $this->contratacionesBase($alcance)
            ->whereIn('co.estado', ['finalizada', 'cancelada'])
            ->whereNull('co.fecha_fin')
            ->count(DB::raw('DISTINCT co.id_contratacion'));
    }

    /** Objetivo E. Pagos simulados registrados (fecha_registro) en el período, por estado actual. */
    private function pagos(array $alcance, string $desde, string $hasta): array
    {
        $res = [];
        foreach (Pago::ESTADOS as $estado) {
            $res[$estado] = ['cantidad' => 0, 'centavos' => 0];
        }

        $filas = $this->pagosBase($alcance)
            ->whereBetween('pa.fecha_registro', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->groupBy('pa.estado')
            ->selectRaw('pa.estado as estado, COUNT(*) as n, ' . Pago::sumaCentavosSql('pa.monto') . ' as c')
            ->get();

        foreach ($filas as $f) {
            if (isset($res[$f->estado])) {
                $res[$f->estado] = ['cantidad' => (int) $f->n, 'centavos' => (int) round((float) $f->c)];
            }
        }

        // Tiempo registro → confirmación (horas), con las fechas de auditoría reales.
        $horas = $this->pagosBase($alcance)
            ->whereBetween('pa.fecha_registro', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->whereNotNull('pa.fecha_confirmacion')
            ->get(['pa.fecha_registro', 'pa.fecha_confirmacion'])
            ->map(fn ($r) => max(0, (Carbon::parse($r->fecha_confirmacion)->getTimestamp() - Carbon::parse($r->fecha_registro)->getTimestamp()) / 3600))
            ->all();

        $res['horas_confirmacion'] = ['mediana' => $this->mediana($horas, 1), 'n' => count($horas)];

        return $res;
    }

    private function resumenPagos(array $p, array $a): array
    {
        $registrados  = $p['pendiente']['cantidad'] + $p['confirmado']['cantidad'] + $p['anulado']['cantidad'];
        $registradosA = $a['pendiente']['cantidad'] + $a['confirmado']['cantidad'] + $a['anulado']['cantidad'];
        $estado = fn ($x) => ['cantidad' => $x['cantidad'], 'monto_bs' => Pago::formatear($x['centavos'])];

        return [
            'simulado'          => true,
            'registrados'       => $registrados,
            'confirmado'        => $estado($p['confirmado']),
            'pendiente'         => $estado($p['pendiente']),
            'anulado'           => $estado($p['anulado']),
            'tasa_anulacion'    => $this->porcentaje($p['anulado']['cantidad'], $registrados),
            'tiempo_confirmacion_horas' => [
                'mediana'  => $p['horas_confirmacion']['mediana'],
                'n'        => $p['horas_confirmacion']['n'],
                'anterior' => $a['horas_confirmacion']['mediana'],
            ],
            'anterior'          => [
                'registrados'    => $registradosA,
                'confirmado'     => $estado($a['confirmado']),
                'tasa_anulacion' => $this->porcentaje($a['anulado']['cantidad'], $registradosA),
            ],
        ];
    }

    /** Objetivo E (estado actual). Pagos simulados pendientes de confirmación y su antigüedad. */
    private function pagosPendientes(array $alcance): array
    {
        $filas = $this->pagosBase($alcance)
            ->where('pa.estado', Pago::ESTADO_PENDIENTE)
            ->get(['pa.monto', 'pa.fecha_registro']);

        $hoy      = Carbon::today()->toDateString();
        $centavos = 0;
        $edades   = [];
        foreach ($filas as $f) {
            $centavos += Pago::aCentavos($f->monto);
            $edades[]  = $this->diasEntre(substr((string) $f->fecha_registro, 0, 10), $hoy);
        }

        return [
            'simulado'      => true,
            'cantidad'      => count($edades),
            'monto_bs'      => Pago::formatear($centavos),
            'mediana_dias'  => $this->mediana($edades, 0),
            'max_dias'      => $edades ? max($edades) : null,
            'sobre_umbral'  => count(array_filter($edades, fn ($d) => $d > self::UMBRAL_ANTIGUEDAD_DIAS)),
        ];
    }

    /** Objetivo B (estado actual). Postulaciones pendientes y su antigüedad real. */
    private function postulacionesPendientes(array $alcance): array
    {
        $hoy    = Carbon::today()->toDateString();
        $fechas = $this->postulacionesBase($alcance)
            ->where('po.estado', 'pendiente')
            ->pluck('po.fecha_postulacion');

        $edades = $fechas->map(fn ($f) => $this->diasEntre(substr((string) $f, 0, 10), $hoy))->all();

        $res = [
            'cantidad'     => count($edades),
            'mediana_dias' => $this->mediana($edades, 0),
            'max_dias'     => $edades ? max($edades) : null,
            'sobre_umbral' => count(array_filter($edades, fn ($d) => $d > self::UMBRAL_ANTIGUEDAD_DIAS)),
            'mas_antiguas' => null,
        ];

        // El contratador ve sus propias solicitudes más antiguas (sin datos personales del trabajador).
        if ($alcance['tipo'] === 'contratador') {
            $res['mas_antiguas'] = $this->postulacionesBase($alcance)
                ->where('po.estado', 'pendiente')
                ->orderBy('po.fecha_postulacion')
                ->orderBy('po.id_postulacion')
                ->limit(5)
                ->get(['po.id_postulacion', 'pu.titulo', 'po.fecha_postulacion'])
                ->map(fn ($r) => [
                    'id_postulacion' => (int) $r->id_postulacion,
                    'titulo'         => $r->titulo,
                    'fecha'          => substr((string) $r->fecha_postulacion, 0, 10),
                    'dias'           => $this->diasEntre(substr((string) $r->fecha_postulacion, 0, 10), $hoy),
                ])->all();
        }

        return $res;
    }

    /** Objetivo F. Calificaciones con fecha en el período: promedio, n, distribución 1–5. */
    private function calificaciones(array $alcance, string $desde, string $hasta): array
    {
        $rolExpr = "CASE WHEN ca.cedula_calificado = po.cedula THEN 'trabajador' ELSE 'contratador' END";

        $filas = $this->calificacionesBase($alcance)
            ->whereBetween('ca.fecha', [$desde, $hasta])
            ->whereBetween('ca.puntuacion', [1, 5])
            ->groupByRaw($rolExpr . ', ca.puntuacion')
            ->selectRaw($rolExpr . ' as rol, ca.puntuacion as puntuacion, COUNT(*) as n')
            ->get();

        $dist  = array_fill_keys(['1', '2', '3', '4', '5'], 0);
        $porRol = ['trabajador' => $dist, 'contratador' => $dist];
        foreach ($filas as $f) {
            $k = (string) (int) $f->puntuacion;
            $dist[$k] += (int) $f->n;
            $porRol[$f->rol][$k] += (int) $f->n;
        }

        $stats = $this->estadisticas($dist);
        $rol = fn ($d) => ['promedio' => $this->estadisticas($d)['promedio'], 'n' => array_sum($d)];

        return $stats + [
            'distribucion' => $dist,
            'por_rol'      => ['trabajador' => $rol($porRol['trabajador']), 'contratador' => $rol($porRol['contratador'])],
        ];
    }

    /** Contrataciones finalizadas en el período que recibieron al menos una calificación. */
    private function coberturaCalificacion(array $alcance, string $desde, string $hasta): array
    {
        $fila = $this->contratacionesBase($alcance)
            ->leftJoin('calificaciones as ca', 'ca.id_contratacion', '=', 'co.id_contratacion')
            ->where('co.estado', 'finalizada')
            ->whereBetween('co.fecha_fin', [$desde, $hasta])
            ->selectRaw('COUNT(DISTINCT co.id_contratacion) as total, '
                . 'COUNT(DISTINCT CASE WHEN ca.id_calificacion IS NOT NULL THEN co.id_contratacion END) as calificadas')
            ->first();

        $total = (int) ($fila->total ?? 0);
        $calif = (int) ($fila->calificadas ?? 0);

        return ['finalizadas' => $total, 'calificadas' => $calif, 'porcentaje' => $this->porcentaje($calif, $total)];
    }

    /** Embudo de una misma cohorte de postulaciones (denominador común: recibidas). */
    private function embudo(array $post): array
    {
        $t = $post['total'];
        $base = $t['recibidas'];

        $etapas = [
            ['clave' => 'recibidas',        'etiqueta' => 'Postulaciones evaluables', 'valor' => $t['recibidas']],
            ['clave' => 'evaluadas',        'etiqueta' => 'Con decisión',             'valor' => $t['evaluadas']],
            ['clave' => 'con_contratacion', 'etiqueta' => 'Con contratación',         'valor' => $t['con_contratacion']],
            ['clave' => 'finalizadas',      'etiqueta' => 'Contratación finalizada',  'valor' => $t['finalizadas']],
        ];
        foreach ($etapas as &$e) {
            $e['porcentaje'] = $this->porcentaje($e['valor'], $base);
        }
        unset($e);

        return ['cohorte_hasta' => $post['hasta'], 'en_maduracion' => $post['en_maduracion'], 'etapas' => $etapas];
    }

    /**
     * Serie mensual (12 meses hasta el mes de `hasta`). Los conteos en cero son ceros reales;
     * las tasas con denominador cero se devuelven como null (sin datos suficientes).
     */
    private function evolucion(array $alcance, string $hasta, int $maduracion, bool $hayPagos): array
    {
        $fin    = Carbon::parse($hasta);
        $inicio = $fin->copy()->startOfMonth()->subMonthsNoOverflow(self::MESES_EVOLUCION - 1);
        $rango  = [$inicio->toDateString(), $fin->toDateString()];
        $hoy    = Carbon::today();
        $corte  = $hoy->copy()->subDays($maduracion);

        $mes = fn (string $col) => "SUBSTR({$col}, 1, 7)";

        $post = $this->postulacionesBase($alcance)
            ->leftJoin('contrataciones as co', 'co.id_postulacion', '=', 'po.id_postulacion')
            ->whereIn('po.estado', self::ESTADOS_EVALUABLES)
            ->whereBetween('po.fecha_postulacion', $rango)
            ->groupByRaw($mes('po.fecha_postulacion'))
            ->selectRaw($mes('po.fecha_postulacion') . ' as mes, '
                . 'COUNT(DISTINCT po.id_postulacion) as recibidas, '
                . "COUNT(DISTINCT CASE WHEN po.estado IN ('aceptada','rechazada') THEN po.id_postulacion END) as evaluadas, "
                . "COUNT(DISTINCT CASE WHEN po.estado = 'aceptada' THEN co.id_contratacion END) as contratadas")
            ->get()->keyBy('mes');

        $contr = $this->contratacionesBase($alcance)
            ->whereIn('co.estado', ['finalizada', 'cancelada'])
            ->whereBetween('co.fecha_fin', $rango)
            ->groupByRaw($mes('co.fecha_fin'))
            ->selectRaw($mes('co.fecha_fin') . ' as mes, '
                . "COUNT(DISTINCT CASE WHEN co.estado = 'finalizada' THEN co.id_contratacion END) as finalizadas, "
                . "COUNT(DISTINCT CASE WHEN co.estado = 'cancelada' THEN co.id_contratacion END) as canceladas")
            ->get()->keyBy('mes');

        $iniciadas = $this->contratacionesBase($alcance)
            ->whereBetween('co.fecha_inicio', $rango)
            ->groupByRaw($mes('co.fecha_inicio'))
            ->selectRaw($mes('co.fecha_inicio') . ' as mes, COUNT(DISTINCT co.id_contratacion) as n')
            ->pluck('n', 'mes');

        $pagos = collect();
        if ($hayPagos) {
            $pagos = $this->pagosBase($alcance)
                ->whereBetween('pa.fecha_registro', [$rango[0] . ' 00:00:00', $rango[1] . ' 23:59:59'])
                ->groupByRaw($mes('pa.fecha_registro'))
                ->selectRaw($mes('pa.fecha_registro') . ' as mes, COUNT(*) as registrados, '
                    . "SUM(CASE WHEN pa.estado = 'confirmado' THEN 1 ELSE 0 END) as confirmados")
                ->get()->keyBy('mes');
        }

        $cal = $this->calificacionesBase($alcance)
            ->whereBetween('ca.fecha', $rango)
            ->whereBetween('ca.puntuacion', [1, 5])
            ->groupByRaw($mes('ca.fecha'))
            ->selectRaw($mes('ca.fecha') . ' as mes, COUNT(*) as n, SUM(ca.puntuacion) as suma')
            ->get()->keyBy('mes');

        $serie = [];
        for ($m = $inicio->copy(); $m->lte($fin); $m->addMonthNoOverflow()) {
            $k   = $m->format('Y-m');
            $fdm = $m->copy()->endOfMonth();
            $p   = $post->get($k);
            $c   = $contr->get($k);
            $pg  = $pagos->get($k);
            $cl  = $cal->get($k);

            $recibidas   = (int) ($p->recibidas ?? 0);
            $evaluadas   = (int) ($p->evaluadas ?? 0);
            $contratadas = (int) ($p->contratadas ?? 0);
            $finalizadas = (int) ($c->finalizadas ?? 0);
            $canceladas  = (int) ($c->canceladas ?? 0);
            $nCal        = (int) ($cl->n ?? 0);

            $serie[] = [
                'mes'                => $k,
                'parcial'            => $fdm->gt($hoy),
                'en_maduracion'      => $fdm->gt($corte),
                'postulaciones'      => $recibidas,
                'evaluadas'          => $evaluadas,
                'contratadas'        => $contratadas,
                'contrataciones_iniciadas' => (int) ($iniciadas[$k] ?? 0),
                'finalizadas'        => $finalizadas,
                'canceladas'         => $canceladas,
                'pagos_registrados'  => $hayPagos ? (int) ($pg->registrados ?? 0) : null,
                'pagos_confirmados'  => $hayPagos ? (int) ($pg->confirmados ?? 0) : null,
                'calificaciones'     => $nCal,
                'tasa_gestion'       => $this->porcentaje($evaluadas, $recibidas),
                'tasa_conversion'    => $this->porcentaje($contratadas, $evaluadas),
                'tasa_finalizacion'  => $this->porcentaje($finalizadas, $finalizadas + $canceladas),
                'promedio_calificacion' => $nCal > 0 ? round(((int) $cl->suma) / $nCal, 2) : null,
            ];
        }

        return $serie;
    }

    /** Resultados por categoría laboral (solo administrador). */
    private function porCategoria(array $pub, array $post, array $contr): array
    {
        $ids = array_unique(array_merge(array_keys($pub), array_keys($post), array_keys($contr)));
        if (! $ids) {
            return [];
        }

        $nombres = DB::table('categorias')->whereIn('id_categoria', $ids)->pluck('nombre_categoria', 'id_categoria');

        $filas = [];
        foreach ($ids as $id) {
            $pu = $pub[$id] ?? ['publicaciones' => 0, 'con_postulacion' => 0];
            $po = $post[$id] ?? ['recibidas' => 0, 'evaluadas' => 0, 'con_contratacion' => 0];
            $co = $contr[$id] ?? ['finalizadas' => 0, 'cerradas' => 0];
            $filas[] = [
                'id_categoria'      => (int) $id,
                'nombre'            => $nombres[$id] ?? 'Categoría #' . $id . ' (no registrada)',
                'publicaciones'     => $pu['publicaciones'],
                'efectividad'       => $this->porcentaje($pu['con_postulacion'], $pu['publicaciones']),
                'postulaciones'     => $po['recibidas'],
                'evaluadas'         => $po['evaluadas'],
                'conversion'        => $this->porcentaje($po['con_contratacion'], $po['evaluadas']),
                'cerradas'          => $co['cerradas'],
                'finalizacion'      => $this->porcentaje($co['finalizadas'], $co['cerradas']),
            ];
        }

        usort($filas, fn ($a, $b) => [$b['postulaciones'], $b['publicaciones']] <=> [$a['postulaciones'], $a['publicaciones']]);

        return $filas;
    }

    /** Alertas basadas únicamente en desviaciones demostradas o en registros concretos. */
    private function alertas(array $r): array
    {
        $alertas = [];

        foreach ($r['indicadores'] as $i) {
            if (($i['variacion']['evaluacion'] ?? null) === 'deterioro') {
                $alertas[] = [
                    'nivel'      => 'alta',
                    'indicador'  => $i['clave'],
                    'mensaje'    => "{$i['nombre']}: deterioro estadísticamente significativo frente al período anterior ("
                        . $this->texto($i['anterior']['valor']) . ' → ' . $this->texto($i['valor']) . ').',
                ];
            }
        }

        $u  = self::UMBRAL_ANTIGUEDAD_DIAS;
        $pp = $r['pendientes']['postulaciones'];
        if ($pp && $pp['sobre_umbral'] > 0) {
            $alertas[] = [
                'nivel'     => 'media',
                'indicador' => 'gestion_postulaciones',
                'mensaje'   => "{$pp['sobre_umbral']} postulación(es) llevan más de {$u} días sin decisión (la más antigua: {$pp['max_dias']} días).",
            ];
        }

        $pg = $r['pendientes']['pagos'];
        if ($pg && $pg['sobre_umbral'] > 0 && $r['alcance'] !== 'trabajador') {
            $alertas[] = [
                'nivel'     => 'media',
                'indicador' => 'confirmacion_pagos',
                'mensaje'   => "{$pg['sobre_umbral']} pago(s) simulado(s) llevan más de {$u} días pendientes de confirmación.",
            ];
        }

        $cd = $r['calidad_datos'];
        if ($r['alcance'] === 'global' && ($cd['aceptadas_sin_contratacion'] > 0 || $cd['cerradas_sin_fecha_fin'] > 0)) {
            $alertas[] = [
                'nivel'     => 'info',
                'indicador' => 'calidad_datos',
                'mensaje'   => "Calidad de datos: {$cd['aceptadas_sin_contratacion']} postulación(es) aceptada(s) sin contratación en la cohorte y "
                    . "{$cd['cerradas_sin_fecha_fin']} contratación(es) cerrada(s) sin fecha de fin.",
            ];
        }

        return $alertas;
    }

    // ════════════════════════════════════════════════════════════
    //  Construcción de indicadores y estadística
    // ════════════════════════════════════════════════════════════

    private function indicadorProporcion(
        string $clave, string $nombre, string $objetivo, string $pregunta, string $sentido,
        array $actual, array $anterior, array $cohorte, callable $explicar, array $extra = []
    ): array {
        [$n1, $d1] = $actual;
        [$n0, $d0] = $anterior;
        $v1 = $this->porcentaje($n1, $d1);
        $v0 = $this->porcentaje($n0, $d0);

        return [
            'clave'       => $clave,
            'nombre'      => $nombre,
            'objetivo'    => $objetivo,
            'pregunta'    => $pregunta,
            'unidad'      => '%',
            'sentido'     => $sentido,
            'valor'       => $v1,
            'numerador'   => $n1,
            'denominador' => $d1,
            'cohorte'     => $cohorte,
            'anterior'    => ['valor' => $v0, 'numerador' => $n0, 'denominador' => $d0],
            'variacion'   => [
                'puntos'     => $v1 !== null && $v0 !== null ? round($v1 - $v0, 1) : null,
                'evaluacion' => $this->evaluarProporciones($n1, $d1, $n0, $d0, $sentido),
            ],
            'interpretacion' => $d1 > 0 ? $explicar($n1, $d1) : 'Sin datos suficientes en el período evaluado.',
        ] + $extra;
    }

    private function indicadorPromedio(string $tipo, array $cal, array $calA, array $p): array
    {
        return [
            'clave'       => 'calidad_calificaciones',
            'nombre'      => $tipo === 'global' ? 'Calidad percibida (calificaciones)' : 'Calificación recibida',
            'objetivo'    => 'F',
            'pregunta'    => $tipo === 'global'
                ? '¿Cómo evalúan contratadores y trabajadores sus experiencias (escala 1–5)?'
                : '¿Cómo me califican mis contrapartes (escala 1–5)?',
            'unidad'      => 'puntos (1–5)',
            'sentido'     => 'mayor_mejor',
            'valor'       => $cal['promedio'],
            'numerador'   => null,
            'denominador' => $cal['n'],
            'cohorte'     => ['desde' => $p['desde'], 'hasta' => $p['hasta'], 'en_maduracion' => false],
            'anterior'    => ['valor' => $calA['promedio'], 'numerador' => null, 'denominador' => $calA['n']],
            'variacion'   => [
                'puntos'     => $cal['promedio'] !== null && $calA['promedio'] !== null ? round($cal['promedio'] - $calA['promedio'], 2) : null,
                'evaluacion' => $this->evaluarPromedios($cal, $calA),
            ],
            'interpretacion' => $cal['n'] > 0
                ? 'Promedio sustentado en ' . $cal['n'] . ' calificación(es)' . ($cal['n'] < self::MUESTRA_MINIMA ? ' (muestra reducida, interpretar con cautela).' : '.')
                : 'Sin calificaciones en el período evaluado.',
        ];
    }

    /**
     * Prueba z de dos proporciones (95 %). Devuelve:
     * mejora | deterioro | sin_cambio_significativo | insuficiente.
     */
    private function evaluarProporciones(int $n1, int $d1, int $n0, int $d0, string $sentido): string
    {
        if ($d1 < self::MUESTRA_MINIMA || $d0 < self::MUESTRA_MINIMA) {
            return 'insuficiente';
        }

        $p1 = $n1 / $d1;
        $p0 = $n0 / $d0;
        $pp = ($n1 + $n0) / ($d1 + $d0);
        $se = sqrt($pp * (1 - $pp) * (1 / $d1 + 1 / $d0));

        if ($se == 0.0) {
            return 'sin_cambio_significativo';
        }

        return $this->clasificar(($p1 - $p0) / $se, $sentido);
    }

    /** Prueba z de diferencia de medias (varianzas por período, 95 %). */
    private function evaluarPromedios(array $a, array $b): string
    {
        if ($a['n'] < self::MUESTRA_MINIMA || $b['n'] < self::MUESTRA_MINIMA) {
            return 'insuficiente';
        }

        $se = sqrt($a['varianza'] / $a['n'] + $b['varianza'] / $b['n']);
        if ($se == 0.0) {
            return $a['promedio'] == $b['promedio'] ? 'sin_cambio_significativo' : $this->clasificar($a['promedio'] > $b['promedio'] ? INF : -INF, 'mayor_mejor');
        }

        return $this->clasificar(($a['promedio'] - $b['promedio']) / $se, 'mayor_mejor');
    }

    private function clasificar(float $z, string $sentido): string
    {
        if (abs($z) < self::Z_CRITICO) {
            return 'sin_cambio_significativo';
        }
        $sube = $z > 0;

        return ($sube === ($sentido === 'mayor_mejor')) ? 'mejora' : 'deterioro';
    }

    /** Promedio y varianza muestral a partir de la distribución 1–5. */
    private function estadisticas(array $dist): array
    {
        $n = array_sum($dist);
        if ($n === 0) {
            return ['promedio' => null, 'n' => 0, 'varianza' => 0.0];
        }

        $suma = 0;
        foreach ($dist as $k => $c) {
            $suma += (int) $k * $c;
        }
        $media = $suma / $n;

        $sc = 0.0;
        foreach ($dist as $k => $c) {
            $sc += $c * (((int) $k - $media) ** 2);
        }

        return ['promedio' => round($media, 2), 'n' => $n, 'varianza' => $n > 1 ? $sc / ($n - 1) : 0.0];
    }

    // ── Utilidades ──

    /** Porcentaje con 1 decimal; null si el denominador es 0 («Sin datos suficientes»). */
    private function porcentaje(int $num, int $den): ?float
    {
        return $den > 0 ? round($num * 100 / $den, 1) : null;
    }

    private function mediana(array $valores, int $decimales): ?float
    {
        $n = count($valores);
        if ($n === 0) {
            return null;
        }
        sort($valores);
        $m = intdiv($n, 2);
        $v = $n % 2 ? $valores[$m] : ($valores[$m - 1] + $valores[$m]) / 2;

        return round($v, $decimales);
    }

    /** Días naturales entre dos fechas Y-m-d (b − a), sin efecto de zona horaria. */
    private function diasEntre(string $a, string $b): int
    {
        $ta = Carbon::createFromFormat('!Y-m-d', substr($a, 0, 10), 'UTC')->getTimestamp();
        $tb = Carbon::createFromFormat('!Y-m-d', substr($b, 0, 10), 'UTC')->getTimestamp();

        return intdiv($tb - $ta, 86400);
    }

    private function texto(?float $v): string
    {
        return $v === null ? 's/d' : rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.') . ' %';
    }
}
