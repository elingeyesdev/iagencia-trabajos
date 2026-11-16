<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pagos SIMULADOS (Incremento 3). No se realiza ningún movimiento real de dinero.
 *
 * Orquestación HTTP: valida la petición (reglas y mensajes en español en este controlador),
 * delega las reglas de dominio en los modelos (Pago::registrarPara/confirmar/anular,
 * Contratacion::resumenPagos/resumenPagosDetallado) y presenta el JSON. Los rechazos de negocio
 * llegan como HttpResponseException {message} + código (Pago::rechazar) y los errores de campo
 * como ValidationException (422 errors.*).
 */
class PagoApiController extends Controller
{
    public const MODO_PAGOS = 'simulado';
    public const AVISO      = 'Operaciones simuladas: no representan dinero real.';

    /** Paginación del listado administrativo. */
    public const PER_PAGE_DEFECTO = 15;
    public const PER_PAGE_MAXIMO  = 100;

    /** Relaciones mínimas para presentar un pago con su contexto (sin N+1). */
    private const RELACIONES_CONTEXTO = [
        'contratacion:id_contratacion,id_postulacion,monto_acordado,estado,fecha_inicio,fecha_fin',
        'contratacion.postulacion:id_postulacion,id_labor,cedula',
        'contratacion.postulacion.trabajador:cedula,nombre',
        'contratacion.postulacion.publicacion:id_labor,titulo,cedula',
        'contratacion.postulacion.publicacion.usuario:cedula,nombre',
    ];

    private const RELACIONES_AUDITORIA = [
        'registrador:cedula,nombre',
        'confirmador:cedula,nombre',
        'anulador:cedula,nombre',
    ];

    /**
     * Contratador: registra un pago pendiente sobre una contratación propia.
     * 201 {message, simulado, data, resumen} · 403/422/503 {message} · 422 errors.monto (saldo).
     */
    public function store(Request $request, $id): JsonResponse
    {
        $datos = $this->validarRegistro($request);

        try {
            $pago = Pago::registrarPara((int) $id, $datos, $request->user());
        } catch (ModelNotFoundException) {
            return $this->noEncontrado();
        }

        $simulado = (bool) $pago->es_simulado;

        return response()->json([
            'message'  => $simulado
                ? 'Pago simulado registrado. No se realizó ningún movimiento real de dinero.'
                : 'Pago registrado.',
            'simulado' => $simulado,
            'data'     => [
                'id_pago'             => $pago->id_pago,
                'id_contratacion'     => $pago->id_contratacion,
                'monto'               => $pago->monto,
                'metodo'              => $pago->metodo,
                'fecha_pago'          => $pago->fecha_pago->format('Y-m-d'),
                'estado'              => $pago->estado,
                'porcentaje_comision' => $pago->porcentaje_comision,
                'monto_comision'      => $pago->monto_comision,
                'monto_neto'          => $pago->monto_neto,
                'referencia_externa'  => $pago->referencia_externa,
                'es_simulado'         => $pago->es_simulado,
                'proveedor'           => $pago->proveedor,
            ],
            'resumen'  => $pago->contratacion->resumenPagos(),
        ], 201);
    }

    /** Contratador: pagos de una contratación propia. 200 · 403 ajena · 404 inexistente. */
    public function pagosContratacionContratador(Request $request, $id): JsonResponse
    {
        try {
            $contratacion = $this->contratacionConPagos((int) $id, $request->user());
        } catch (ModelNotFoundException) {
            return $this->noEncontrado();
        }

        return $this->respuestaContratacion($contratacion);
    }

    /** Administrador: pagos de cualquier contratación. 200 · 404 inexistente. */
    public function pagosContratacionAdmin($id): JsonResponse
    {
        try {
            return $this->respuestaContratacion($this->contratacionConPagos((int) $id));
        } catch (ModelNotFoundException) {
            return $this->noEncontrado();
        }
    }

    /** Trabajador (solo lectura): pagos de sus contrataciones (postulacion.cedula = autenticado). */
    public function misPagos(Request $request): JsonResponse
    {
        $cedula = (int) $request->user()->cedula;

        $idsContratacion = Contratacion::query()
            ->select('id_contratacion')
            ->whereIn('id_postulacion', Postulacion::query()->select('id_postulacion')->where('cedula', $cedula));

        $pagos = Pago::query()
            ->with(self::RELACIONES_CONTEXTO)
            ->whereIn('id_contratacion', $idsContratacion)
            ->orderByDesc('id_pago')
            ->get();

        $resumenes = $pagos->groupBy('id_contratacion')
            ->map(fn ($grupo) => $grupo->first()->contratacion->resumenPagosDetallado($grupo))
            ->values();

        return response()->json([
            'success'    => true,
            'modo_pagos' => self::MODO_PAGOS,
            'simulado'   => true,
            'aviso'      => self::AVISO,
            'data'       => $pagos->map(fn (Pago $p) => $this->presentarConContexto($p, false))->values(),
            'resumen'    => $resumenes,
        ]);
    }

    /** Administrador: listado filtrado y paginado con totales del filtro. */
    public function index(Request $request): JsonResponse
    {
        $filtros  = $this->validarFiltros($request);
        $consulta = Pago::query()
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['id_contratacion'] ?? null, fn ($q, $v) => $q->where('id_contratacion', (int) $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_pago', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_pago', '<=', $v));

        $totales = $this->totalesPorEstado($consulta);

        $pagina = (clone $consulta)
            ->with(self::RELACIONES_CONTEXTO)
            ->orderByDesc('id_pago')
            ->paginate((int) ($filtros['per_page'] ?? self::PER_PAGE_DEFECTO))
            ->withQueryString();

        return response()->json([
            'modo_pagos' => self::MODO_PAGOS,
            'simulado'   => true,
            'aviso'      => self::AVISO,
            'data'       => collect($pagina->items())->map(fn (Pago $p) => $this->presentarConContexto($p, true))->values(),
            'meta'       => [
                'current_page' => $pagina->currentPage(),
                'last_page'    => $pagina->lastPage(),
                'per_page'     => $pagina->perPage(),
                'total'        => $pagina->total(),
                'from'         => $pagina->firstItem(),
                'to'           => $pagina->lastItem(),
            ],
            'totales'    => $totales,
        ]);
    }

    /** Administrador: detalle de un pago con auditoría. 200 · 404. */
    public function show($id): JsonResponse
    {
        try {
            $pago = $this->cargarDetalle((int) $id);
        } catch (ModelNotFoundException) {
            return $this->noEncontrado();
        }

        return response()->json([
            'modo_pagos' => self::MODO_PAGOS,
            'simulado'   => true,
            'aviso'      => self::AVISO,
            'data'       => $this->presentarDetalle($pago),
        ]);
    }

    /** Administrador: confirma un pago pendiente. 200 · 404 · 422. */
    public function confirmar(Request $request, $id): JsonResponse
    {
        try {
            Pago::confirmar((int) $id, $request->user());
        } catch (ModelNotFoundException) {
            return $this->noEncontrado();
        }

        return $this->respuestaOperacion((int) $id, 'Pago simulado confirmado.');
    }

    /** Administrador: anula un pago pendiente o confirmado con motivo. 200 · 404 · 422. */
    public function anular(Request $request, $id): JsonResponse
    {
        $motivo = $this->validarAnulacion($request);

        try {
            Pago::anular((int) $id, $request->user(), $motivo);
        } catch (ModelNotFoundException) {
            return $this->noEncontrado();
        }

        return $this->respuestaOperacion((int) $id, 'Pago simulado anulado.');
    }

    // ───────────────────────────── Validación ─────────────────────────────

    /**
     * Registro de un pago simulado (contratador). La propiedad de la contratación se valida en
     * Pago::registrarPara dentro del bloqueo. Solo los datos validados llegan al dominio: los
     * campos de control enviados por el cliente (estado, comisión, referencia...) se ignoran.
     *
     * Normalización de `monto` antes de validar (formato canónico que entiende Pago::aCentavos):
     *  - Número JSON (int/float) → cadena con el cast nativo de PHP (7 → "7", 12.5 → "12.5").
     *    Floats extremos producen notación científica ("1.0E+25") y la regla `regex` los rechaza.
     *  - Espacios exteriores eliminados.
     *  - Ceros a la izquierda de la parte entera: se ADMITEN y se eliminan ("0002.00" → "2.00",
     *    "00000000000005" → "5", "0.50" se conserva). Solo se aplica a cadenas de dígitos
     *    con punto decimal opcional; cualquier otra forma queda intacta para que la valide `regex`.
     */
    private function validarRegistro(Request $request): array
    {
        if ($request->has('monto')) {
            $monto = $request->input('monto');

            if (is_int($monto) || is_float($monto)) {
                $monto = (string) $monto;
            }

            if (is_string($monto)) {
                $monto = trim($monto);
                if (preg_match('/^\d+(\.\d+)?$/', $monto)) {
                    $monto = preg_replace('/^0+(?=\d)/', '', $monto);
                }
                $request->merge(['monto' => $monto]);
            }
        }

        return $request->validate([
            // `regex`: formato estricto coherente con decimal(10,2) y Pago::aCentavos
            // (rechaza '5.', '.5', '+5', '-5', '1e3', '12,5').
            'monto'       => ['required', 'numeric', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'fecha_pago'  => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'metodo'      => ['required', Rule::in(Pago::METODOS)],
            'observacion' => ['nullable', 'string', 'max:255'],
        ], [
            'monto.required'             => 'El monto es obligatorio.',
            'monto.numeric'              => 'El monto debe ser un número.',
            'monto.regex'                => 'El monto debe escribirse solo con dígitos y punto decimal (p. ej. 150 o 150.50), con hasta 8 enteros y 2 decimales.',
            'monto.decimal'              => 'El monto admite como máximo 2 decimales.',
            'monto.gt'                   => 'El monto debe ser mayor que cero.',
            'monto.max'                  => 'El monto no puede superar 99999999.99.',
            'fecha_pago.required'        => 'La fecha de pago es obligatoria.',
            'fecha_pago.date_format'     => 'La fecha de pago debe tener el formato AAAA-MM-DD.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser posterior a hoy.',
            'metodo.required'            => 'El método de pago es obligatorio.',
            'metodo.in'                  => 'El método de pago no es válido. Use: efectivo, transferencia, qr, deposito u otro.',
            'observacion.string'         => 'La observación debe ser texto.',
            'observacion.max'            => 'La observación no puede superar 255 caracteres.',
        ]);
    }

    /** Anulación (administrador): motivo obligatorio, recortado antes de validar. Devuelve el motivo validado. */
    private function validarAnulacion(Request $request): string
    {
        if (is_string($request->input('motivo_anulacion'))) {
            $request->merge(['motivo_anulacion' => trim($request->input('motivo_anulacion'))]);
        }

        return $request->validate([
            'motivo_anulacion' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'motivo_anulacion.required' => 'El motivo de anulación es obligatorio.',
            'motivo_anulacion.string'   => 'El motivo de anulación debe ser texto.',
            'motivo_anulacion.min'      => 'El motivo de anulación debe tener al menos 5 caracteres.',
            'motivo_anulacion.max'      => 'El motivo de anulación no puede superar 255 caracteres.',
        ])['motivo_anulacion'];
    }

    /** Filtros del listado administrativo (GET /api/admin/pagos). */
    private function validarFiltros(Request $request): array
    {
        return $request->validate([
            'estado'          => ['nullable', 'string', Rule::in(Pago::ESTADOS)],
            'id_contratacion' => ['nullable', 'integer', 'min:1'],
            'desde'           => ['nullable', 'date_format:Y-m-d'],
            'hasta'           => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:' . self::PER_PAGE_MAXIMO],
            'page'            => ['nullable', 'integer', 'min:1'],
        ], [
            'estado.in'                => 'El estado no es válido. Use: pendiente, confirmado o anulado.',
            'estado.string'            => 'El estado no es válido. Use: pendiente, confirmado o anulado.',
            'id_contratacion.integer'  => 'La contratación debe ser un número entero.',
            'id_contratacion.min'      => 'La contratación debe ser un número mayor que cero.',
            'desde.date_format'        => 'La fecha "desde" debe tener el formato AAAA-MM-DD.',
            'hasta.date_format'        => 'La fecha "hasta" debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal'     => 'La fecha "hasta" debe ser igual o posterior a la fecha "desde".',
            'per_page.integer'         => 'La cantidad por página debe ser un número entero.',
            'per_page.min'             => 'La cantidad por página debe ser al menos 1.',
            'per_page.max'             => 'La cantidad por página no puede superar ' . self::PER_PAGE_MAXIMO . '.',
            'page.integer'             => 'La página debe ser un número entero.',
            'page.min'                 => 'La página debe ser al menos 1.',
        ]);
    }

    // ───────────────────────────── Consultas ─────────────────────────────

    /**
     * Contratación con sus pagos (orden id_pago desc) y auditoría.
     * Si se indica contratador, se exige que sea el dueño de la publicación.
     *
     * @throws ModelNotFoundException  contratación inexistente (404).
     * @throws \Illuminate\Http\Exceptions\HttpResponseException 403 si es ajena al contratador.
     */
    private function contratacionConPagos(int $idContratacion, ?User $contratador = null): Contratacion
    {
        $contratacion = Contratacion::with([
            'postulacion:id_postulacion,id_labor,cedula',
            'postulacion.publicacion:id_labor,titulo,cedula',
            'pagos' => fn ($q) => $q->orderByDesc('id_pago'),
            'pagos.registrador:cedula,nombre',
            'pagos.confirmador:cedula,nombre',
            'pagos.anulador:cedula,nombre',
        ])->findOrFail($idContratacion);

        if ($contratador !== null) {
            $publicacion = $contratacion->postulacion?->publicacion;
            if ($publicacion === null || (int) $publicacion->cedula !== (int) $contratador->cedula) {
                Pago::rechazar('No autorizado.', 403);
            }
        }

        return $contratacion;
    }

    /**
     * Totales (cantidad y suma) por estado sobre una consulta ya filtrada.
     * Sumas agregadas en SQL (portable SQLite/MySQL) y convertidas a centavos enteros.
     *
     * @return array<string, array{cantidad: int, monto: string}>
     */
    private function totalesPorEstado(Builder $consulta): array
    {
        $filas = (clone $consulta)
            ->reorder()
            ->toBase()
            ->select('estado')
            ->selectRaw('COUNT(*) AS cantidad')
            ->selectRaw('SUM(monto) AS suma')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        $totales     = [];
        $cantidadAll = 0;
        $centavosAll = 0;
        foreach (Pago::ESTADOS as $estado) {
            $fila     = $filas->get($estado);
            $cantidad = (int) ($fila->cantidad ?? 0);
            $centavos = $fila === null || $fila->suma === null ? 0 : Pago::aCentavos($fila->suma);

            $totales[$estado] = ['cantidad' => $cantidad, 'monto' => Pago::formatear($centavos)];
            $cantidadAll += $cantidad;
            $centavosAll += $centavos;
        }
        $totales['total'] = ['cantidad' => $cantidadAll, 'monto' => Pago::formatear($centavosAll)];

        return $totales;
    }

    // ───────────────────────────── Presentación ─────────────────────────────

    /** 404 genérico: no revela la clase del modelo ni el identificador consultado. */
    private function noEncontrado(): JsonResponse
    {
        return response()->json(['message' => 'Recurso no encontrado.'], 404);
    }

    private function respuestaContratacion(Contratacion $contratacion): JsonResponse
    {
        return response()->json([
            'modo_pagos' => self::MODO_PAGOS,
            'simulado'   => true,
            'aviso'      => self::AVISO,
            'data'       => [
                'resumen' => $contratacion->resumenPagosDetallado($contratacion->pagos),
                'pagos'   => $contratacion->pagos
                    ->map(fn (Pago $p) => $this->presentarBase($p) + ['auditoria' => $this->auditoria($p)])
                    ->values(),
            ],
        ]);
    }

    private function respuestaOperacion(int $id, string $mensaje): JsonResponse
    {
        $pago = $this->cargarDetalle($id);

        return response()->json([
            'message'  => $mensaje,
            'simulado' => (bool) $pago->es_simulado,
            'aviso'    => self::AVISO,
            'data'     => $this->presentarDetalle($pago),
        ]);
    }

    private function cargarDetalle(int $id): Pago
    {
        return Pago::query()
            ->with([...self::RELACIONES_CONTEXTO, ...self::RELACIONES_AUDITORIA])
            ->findOrFail($id);
    }

    private function presentarDetalle(Pago $pago): array
    {
        $datos = $this->presentarConContexto($pago, true);
        $datos['contratacion']['fecha_inicio'] = $pago->contratacion?->fecha_inicio;
        $datos['contratacion']['fecha_fin']    = $pago->contratacion?->fecha_fin;
        $datos['auditoria'] = $this->auditoria($pago);
        $datos['resumen']   = $pago->contratacion?->resumenPagosDetallado();

        return $datos;
    }

    private function presentarBase(Pago $pago): array
    {
        return [
            'id_pago'             => (int) $pago->id_pago,
            'id_contratacion'     => (int) $pago->id_contratacion,
            'monto'               => $pago->monto,
            'porcentaje_comision' => $pago->porcentaje_comision,
            'monto_comision'      => $pago->monto_comision,
            'monto_neto'          => $pago->monto_neto,
            'metodo'              => $pago->metodo,
            'fecha_pago'          => $pago->fecha_pago?->format('Y-m-d'),
            'estado'              => $pago->estado,
            'observacion'         => $pago->observacion,
            'es_simulado'         => (bool) $pago->es_simulado,
            'proveedor'           => $pago->proveedor,
            'referencia_externa'  => $pago->referencia_externa,
            'fecha_registro'      => $pago->fecha_registro?->format('Y-m-d H:i:s'),
            'fecha_confirmacion'  => $pago->fecha_confirmacion?->format('Y-m-d H:i:s'),
            'fecha_anulacion'     => $pago->fecha_anulacion?->format('Y-m-d H:i:s'),
            'motivo_anulacion'    => $pago->motivo_anulacion,
        ];
    }

    private function presentarConContexto(Pago $pago, bool $incluirTrabajador): array
    {
        $contratacion = $pago->contratacion;
        $postulacion  = $contratacion?->postulacion;
        $publicacion  = $postulacion?->publicacion;

        $datos = $this->presentarBase($pago) + [
            'contratacion' => [
                'id_contratacion' => (int) $pago->id_contratacion,
                'estado'          => $contratacion?->estado,
                'monto_acordado'  => $this->importe($contratacion?->monto_acordado),
            ],
            'publicacion'  => [
                'id_labor' => $publicacion?->id_labor === null ? null : (int) $publicacion->id_labor,
                'titulo'   => $publicacion?->titulo,
            ],
            'contratador'  => $this->persona($publicacion?->usuario, $publicacion?->cedula),
        ];

        if ($incluirTrabajador) {
            $datos['trabajador'] = $this->persona($postulacion?->trabajador, $postulacion?->cedula);
        }

        return $datos;
    }

    private function auditoria(Pago $pago): array
    {
        return [
            'registrado_por'     => $this->persona($pago->registrador, $pago->cedula_registra),
            'fecha_registro'     => $pago->fecha_registro?->format('Y-m-d H:i:s'),
            'confirmado_por'     => $pago->cedula_confirma === null ? null : $this->persona($pago->confirmador, $pago->cedula_confirma),
            'fecha_confirmacion' => $pago->fecha_confirmacion?->format('Y-m-d H:i:s'),
            'anulado_por'        => $pago->cedula_anula === null ? null : $this->persona($pago->anulador, $pago->cedula_anula),
            'fecha_anulacion'    => $pago->fecha_anulacion?->format('Y-m-d H:i:s'),
            'motivo_anulacion'   => $pago->motivo_anulacion,
        ];
    }

    /** Solo cédula y nombre (nunca email ni contraseña). Conserva la cédula histórica si el usuario ya no existe. */
    private function persona(?User $usuario, $cedula): ?array
    {
        if ($usuario === null && $cedula === null) {
            return null;
        }

        return [
            'cedula' => $cedula === null ? null : (int) $cedula,
            'nombre' => $usuario?->nombre,
        ];
    }

    /** Importe normalizado a 2 decimales ("100" → "100.00"); null si no es válido. */
    private function importe($valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        try {
            return Pago::formatear(Pago::aCentavos($valor));
        } catch (DomainException) {
            return null;
        }
    }
}
