<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Incremento 4 — API de reportes (solo lectura, solo administrador).
 * Arquitectura: API (validación de filtros + consultas de este controlador) → datos del reporte →
 * JSON (vistas reportes/*.blade.php + resources/js/reportes.js) o PDF descargable (TCPDF).
 *
 * Consultas (sección «Consultas»):
 * - Agregados en BD (COUNT/SUM/GROUP BY); los listados se limitan a LIMITE_FILAS.
 * - Importes sumados en centavos con Pago::sumaCentavosSql (SUM(ROUND(col * 100)), exacto y
 *   portable SQLite/MySQL) y formateados con Pago::centavosATexto (sin recalcular comisiones:
 *   se usan las columnas monto_comision/monto_neto copiadas en cada pago).
 * - Filtros de fecha con whereDate (portable: date() en SQLite, DATE() en MySQL).
 *
 * Datos del reporte (sección «Datos»): datosUsuarios/datosPublicaciones/datosCategorias/
 * datosActividad/datosPagos transforman los resultados a una estructura estable (listas en lugar de
 * mapas con claves numéricas, importes como texto con 2 decimales). La usan SIN cambios tanto la acción
 * JSON (responder) como la acción PDF (descargarPdf): no hay consultas, filtros ni cálculos duplicados.
 *
 * Filtros validados con filtros(): un fallo responde SIEMPRE 422 JSON estándar ({message, errors}),
 * aunque falte «Accept: application/json» (también en las acciones PDF).
 * La respuesta JSON se codifica con JSON_HEX_* (defensa en profundidad: '<', '>', '&', comillas como \u00XX);
 * el escapado de presentación lo hace esc() en reportes.js. El PDF dibuja texto plano (sin HTML).
 */
class ReporteApiController extends Controller
{
    public const LIMITE_FILAS = 1000;
    public const LIMITE_RECIENTES = 10;

    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    /** Token Sanctum de las vistas imprimibles: nombre y duración (minutos). */
    public const TOKEN_NOMBRE  = 'reportes';
    public const TOKEN_MINUTOS = 60;

    /** Vistas imprimibles: tipo de reporte => vista Blade (la URI web es la clave de VISTAS_URI). */
    public const VISTAS_URI = [
        'usuarios'   => 'usuarios',
        'labores'    => 'publicaciones', // URI «labores»: no altera la guarda de PublicacionPropiedadTest.
        'categorias' => 'categorias',
        'actividad'  => 'actividad',
        'pagos'      => 'pagos',
    ];

    /** Texto de la banda de operaciones simuladas (mismo texto que la vista de pagos). */
    public const BANDA_SIMULADO = 'OPERACIONES SIMULADAS — NO REPRESENTAN DINERO REAL';

    // ── Geometría del PDF (mm, tamaño LETTER 215.9 × 279.4) ──
    private const PDF_MARGEN       = 12.0;  // márgenes laterales
    private const PDF_MARGEN_SUP   = 36.0;  // deja sitio a la banda del encabezado (26 mm + franja de acento)
    private const PDF_MARGEN_INF   = 20.0;  // margen de salto automático: sitio para el pie
    private const PDF_ALTO_BANDA   = 26.0;
    private const PDF_PAD          = 1.3;   // relleno horizontal de celdas
    private const PDF_MAX_LINEAS   = 3;     // líneas por celda antes de truncar con «…»
    private const PDF_MAX_CARACTERES = 300; // recorte previo de textos muy largos

    /**
     * Orientación y título de cada PDF. Categorías y actividad caben en vertical (tablas de
     * 4–6 columnas cortas); usuarios, publicaciones y pagos tienen 7–12 columnas → horizontal.
     */
    private const PDF_TIPOS = [
        'usuarios'      => ['L', 'Reporte de usuarios'],
        'publicaciones' => ['L', 'Reporte de publicaciones'],
        'categorias'    => ['P', 'Reporte de categorías'],
        'actividad'     => ['P', 'Reporte de actividad del sistema'],
        'pagos'         => ['L', 'Reporte de pagos simulados, ingresos y comisiones'],
    ];

    /**
     * Token para que una vista imprimible (resources/views/reportes/*) consuma esta API vía
     * <meta name="api-token">, como el dashboard admin, pero con expiración corta para no acumular
     * tokens permanentes (Sanctum rechaza el token al vencer `expires_at`; las filas vencidas se
     * purgan con `php artisan sanctum:prune-expired`). Lo usan las rutas web de routes/web.php.
     */
    public static function tokenParaVista(User $user): string
    {
        return $user
            ->createToken(self::TOKEN_NOMBRE, ['*'], now()->addMinutes(self::TOKEN_MINUTOS))
            ->plainTextToken;
    }

    // ── Acciones JSON ──

    public function usuarios(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);

        return $this->responder($request, 'usuarios', $filtros, $this->datosUsuarios($filtros));
    }

    public function publicaciones(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);

        return $this->responder($request, 'publicaciones', $filtros, $this->datosPublicaciones($filtros));
    }

    public function categorias(Request $request): JsonResponse
    {
        return $this->responder($request, 'categorias', [], $this->datosCategorias());
    }

    public function actividad(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);

        return $this->responder($request, 'actividad', $filtros, $this->datosActividad($filtros));
    }

    public function pagos(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);

        return $this->responder($request, 'pagos', $filtros, $this->datosPagos($filtros));
    }

    // ── Acciones PDF (GET /api/admin/reportes/{tipo}/pdf) ──

    public function usuariosPdf(Request $request): Response
    {
        return $this->descargarPdf($request, 'usuarios');
    }

    public function publicacionesPdf(Request $request): Response
    {
        return $this->descargarPdf($request, 'publicaciones');
    }

    public function categoriasPdf(Request $request): Response
    {
        return $this->descargarPdf($request, 'categorias');
    }

    public function actividadPdf(Request $request): Response
    {
        return $this->descargarPdf($request, 'actividad');
    }

    public function pagosPdf(Request $request): Response
    {
        return $this->descargarPdf($request, 'pagos');
    }

    // ── Datos del reporte (compartidos por JSON y PDF) ──

    /** Reporte 1: usuarios. */
    private function datosUsuarios(array $filtros): array
    {
        $d = $this->consultarUsuarios($filtros);

        return [
            'total'      => $d['total'],
            'por_estado' => $this->activosInactivos($d['porEstado']),
            'por_rol'    => collect($d['porRol'])->map(fn ($n, $rol) => ['rol' => (string) $rol, 'total' => $n])->values()->all(),
            'sin_rol'    => $d['sinRol'],
            'mostrados'  => $d['filas']->count(),
            'filas'      => $d['filas']->map(fn ($u) => [
                'cedula'         => $u->cedula,
                'nombre'         => $u->nombre,
                'email'          => $u->email,
                'ciudad'         => $u->ciudad,
                'roles'          => $u->roles->pluck('name')->values()->all(),
                'estado'         => (int) $u->estado,
                'fecha_registro' => $u->fecha_registro !== null ? (string) $u->fecha_registro : null,
            ])->values()->all(),
            'opciones'   => [
                'roles' => Role::where('guard_name', 'web')->orderBy('name')->pluck('name')->values()->all(),
            ],
        ];
    }

    /** Reporte 2: publicaciones. */
    private function datosPublicaciones(array $filtros): array
    {
        $d = $this->consultarPublicaciones($filtros);

        return [
            'total'      => $d['total'],
            'por_estado' => $this->activosInactivos($d['porEstado']),
            'suma_pago'  => $d['sumaPago'],
            'mostrados'  => $d['filas']->count(),
            'filas'      => $d['filas']->map(fn ($p) => [
                'id_labor'          => $p->id_labor,
                'titulo'            => $p->titulo,
                'tipo'              => $p->tipo,
                'categoria'         => $p->categoria?->nombre_categoria,
                'cedula'            => $p->cedula,
                'contratador'       => $p->usuario?->nombre,
                'fecha_publicacion' => $p->fecha_publicacion,
                'pago'              => number_format((float) $p->pago, 2, '.', ''),
                'estado'            => (int) $p->estado,
            ])->values()->all(),
            'opciones'   => [
                'categorias' => Categoria::orderBy('nombre_categoria')->get(['id_categoria', 'nombre_categoria'])
                    ->map(fn ($c) => ['id_categoria' => $c->id_categoria, 'nombre_categoria' => $c->nombre_categoria])
                    ->values()->all(),
            ],
        ];
    }

    /** Reporte 3: categorías (sin filtros). */
    private function datosCategorias(): array
    {
        $d = $this->consultarCategorias();

        return [
            'total'               => $d['total'],
            'por_estado'          => $this->activosInactivos($d['porEstado']),
            'total_publicaciones' => $d['totalPublicaciones'],
            'filas'               => $d['filas']->map(fn ($c) => [
                'id_categoria'        => $c->id_categoria,
                'nombre_categoria'    => $c->nombre_categoria,
                'estado'              => (int) $c->estado,
                'publicaciones_count' => (int) $c->publicaciones_count,
            ])->values()->all(),
        ];
    }

    /** Reporte 4: actividad. */
    private function datosActividad(array $filtros): array
    {
        $d = $this->consultarActividad($filtros);

        $seccion = fn (array $s, callable $fila) => [
            'total'      => $s['total'],
            'por_estado' => collect($s['porEstado'])->map(fn ($n, $estado) => ['estado' => (string) $estado, 'total' => $n])->values()->all(),
            'recientes'  => collect($s['recientes'])->map($fila)->values()->all(),
        ];

        return [
            'usuarios' => $seccion($d['usuarios'], fn ($u) => [
                'cedula' => $u->cedula, 'nombre' => $u->nombre,
                'fecha_registro' => $u->fecha_registro !== null ? (string) $u->fecha_registro : null,
            ]),
            'publicaciones' => $seccion($d['publicaciones'], fn ($p) => [
                'id_labor' => $p->id_labor, 'titulo' => $p->titulo, 'fecha_publicacion' => $p->fecha_publicacion, 'estado' => (int) $p->estado,
            ]),
            'postulaciones' => $seccion($d['postulaciones'], fn ($p) => [
                'id_postulacion' => $p->id_postulacion, 'id_labor' => $p->id_labor, 'cedula' => $p->cedula,
                'fecha_postulacion' => $p->fecha_postulacion, 'estado' => $p->estado,
            ]),
            'contrataciones' => $seccion($d['contrataciones'], fn ($c) => [
                'id_contratacion' => $c->id_contratacion, 'id_postulacion' => $c->id_postulacion, 'fecha_inicio' => $c->fecha_inicio,
                'monto_acordado' => number_format((float) $c->monto_acordado, 2, '.', ''), 'estado' => $c->estado,
            ]),
            'pagos' => $seccion($d['pagos'], fn ($p) => [
                'id_pago' => $p->id_pago, 'id_contratacion' => $p->id_contratacion, 'fecha_pago' => $p->fecha_pago?->format('Y-m-d'),
                'monto' => $p->monto, 'estado' => $p->estado, 'es_simulado' => (bool) $p->es_simulado,
            ]) + ['simulado' => true],
        ];
    }

    /** Reporte 5: pagos simulados, ingresos y comisiones. */
    private function datosPagos(array $filtros): array
    {
        $d = $this->consultarPagos($filtros);

        return [
            'simulado'    => true,
            'aviso'       => PagoApiController::AVISO,
            'total'       => $d['total'],
            'por_estado'  => collect($d['porEstado'])->map(fn ($t, $estado) => ['estado' => (string) $estado] + $t)->values()->all(),
            'confirmados' => $d['confirmados'],
            'mostrados'   => $d['filas']->count(),
            'filas'       => $d['filas']->map(function ($p) {
                $postulacion = $p->contratacion?->postulacion;
                $publicacion = $postulacion?->publicacion;

                return [
                    'id_pago'             => $p->id_pago,
                    'fecha_pago'          => $p->fecha_pago?->format('Y-m-d'),
                    'id_contratacion'     => $p->id_contratacion,
                    'titulo'              => $publicacion?->titulo,
                    // null = sin publicación/postulación (se muestra «—»); nombre null = usuario inexistente.
                    'contratador'         => $publicacion ? ['cedula' => $publicacion->cedula, 'nombre' => $publicacion->usuario?->nombre] : null,
                    'trabajador'          => $postulacion ? ['cedula' => $postulacion->cedula, 'nombre' => $postulacion->trabajador?->nombre] : null,
                    'metodo'              => $p->metodo,
                    'estado'              => $p->estado,
                    'monto'               => $p->monto,
                    'porcentaje_comision' => $p->porcentaje_comision,
                    'monto_comision'      => $p->monto_comision,
                    'monto_neto'          => $p->monto_neto,
                    'es_simulado'         => (bool) $p->es_simulado,
                ];
            })->values()->all(),
            'estados'     => Pago::ESTADOS,
        ];
    }

    // ── Validación de filtros (query string) ──

    /**
     * Filtros comunes de reportes. La autorización la hace la ruta (auth:sanctum +
     * role:administrador). El significado de `estado` depende del reporte; aquí solo se
     * admiten los valores reales del esquema.
     *
     * Un fallo responde SIEMPRE 422 JSON con el mismo formato que el render estándar de
     * Laravel ({message, errors}), aunque el cliente no envíe «Accept: application/json»
     * (nunca redirige). Si es válido, devuelve solo los filtros no vacíos.
     *
     * @return array<string,string>
     *
     * @throws HttpResponseException 422 si algún filtro no es válido.
     */
    private function filtros(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'desde'     => ['nullable', 'date_format:Y-m-d'],
            'hasta'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'estado'    => ['nullable', 'string', Rule::in(array_merge(['0', '1'], Pago::ESTADOS))],
            'rol'       => ['nullable', 'string', 'max:50', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'categoria' => ['nullable', 'integer', 'min:1'],
        ], [
            'desde.date_format'    => 'La fecha «desde» debe tener el formato AAAA-MM-DD.',
            'hasta.date_format'    => 'La fecha «hasta» debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha «hasta» no puede ser anterior a «desde».',
            'estado.in'            => 'El estado indicado no es válido.',
            'rol.exists'           => 'El rol indicado no existe.',
            'categoria.integer'    => 'La categoría indicada no es válida.',
            'categoria.min'        => 'La categoría indicada no es válida.',
        ]);

        if ($validator->fails()) {
            $e = new ValidationException($validator);

            throw new HttpResponseException(response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422));
        }

        return array_filter($validator->validated(), fn ($v) => $v !== null && $v !== '');
    }

    // ── Consultas (solo lectura) ──

    private function rangoFechas($query, string $columna, array $f): void
    {
        if (! empty($f['desde'])) {
            $query->whereDate($columna, '>=', $f['desde']);
        }
        if (! empty($f['hasta'])) {
            $query->whereDate($columna, '<=', $f['hasta']);
        }
    }

    /** @return array<string,int> */
    private function conteoPor($query, string $columna): array
    {
        return $query->select($columna)
            ->selectRaw('COUNT(*) as total')
            ->groupBy($columna)
            ->pluck('total', $columna)
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** Reporte 1: usuarios. */
    private function consultarUsuarios(array $f): array
    {
        $base = User::query();
        if (! empty($f['rol'])) {
            $base->role($f['rol']);
        }
        if (in_array($f['estado'] ?? null, ['0', '1'], true)) {
            $base->where('estado', (int) $f['estado']);
        }

        $total = (clone $base)->count();

        $porEstado = $this->conteoPor((clone $base)->toBase(), 'estado');

        $porRol = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.model_id', (clone $base)->toBase()->select('cedula'))
            ->select('roles.name')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('roles.name')
            ->orderBy('roles.name')
            ->pluck('total', 'roles.name')
            ->map(fn ($v) => (int) $v)
            ->all();

        $sinRol = (clone $base)->whereDoesntHave('roles')->count();

        $filas = (clone $base)->with('roles:id,name')
            ->orderBy('cedula')
            ->limit(self::LIMITE_FILAS)
            ->get(['cedula', 'nombre', 'email', 'ciudad', 'estado', 'fecha_registro']);

        return compact('total', 'porEstado', 'porRol', 'sinRol', 'filas');
    }

    /** Reporte 2: publicaciones. */
    private function consultarPublicaciones(array $f): array
    {
        $base = Publicacion::query();
        if (in_array($f['estado'] ?? null, ['0', '1'], true)) {
            $base->where('estado', (int) $f['estado']);
        }
        if (! empty($f['categoria'])) {
            $base->where('id_categoria', (int) $f['categoria']);
        }
        $this->rangoFechas($base, 'fecha_publicacion', $f);

        $total = (clone $base)->count();
        $porEstado = $this->conteoPor((clone $base)->toBase(), 'estado');
        $sumaPago = Pago::centavosATexto((clone $base)->toBase()->selectRaw(Pago::sumaCentavosSql('pago') . ' as c')->value('c'));

        $filas = (clone $base)
            ->with(['categoria:id_categoria,nombre_categoria', 'usuario:cedula,nombre'])
            ->orderBy('id_labor')
            ->limit(self::LIMITE_FILAS)
            ->get(['id_labor', 'titulo', 'tipo', 'id_categoria', 'cedula', 'pago', 'fecha_publicacion', 'estado']);

        return compact('total', 'porEstado', 'sumaPago', 'filas');
    }

    /** Reporte 3: categorías. */
    private function consultarCategorias(): array
    {
        $filas = Categoria::query()
            ->withCount('publicaciones')
            ->orderBy('id_categoria')
            ->limit(self::LIMITE_FILAS)
            ->get(['id_categoria', 'nombre_categoria', 'estado']);

        $total = Categoria::count();
        $porEstado = $this->conteoPor(Categoria::query()->toBase(), 'estado');
        $totalPublicaciones = (int) $filas->sum('publicaciones_count');

        return compact('total', 'porEstado', 'totalPublicaciones', 'filas');
    }

    /** Reporte 4: actividad. */
    private function consultarActividad(array $f): array
    {
        $usuarios = User::query();
        $this->rangoFechas($usuarios, 'fecha_registro', $f);

        $publicaciones = Publicacion::query();
        $this->rangoFechas($publicaciones, 'fecha_publicacion', $f);

        $postulaciones = Postulacion::query();
        $this->rangoFechas($postulaciones, 'fecha_postulacion', $f);

        $contrataciones = Contratacion::query();
        $this->rangoFechas($contrataciones, 'fecha_inicio', $f);

        $hayPagos = Schema::hasTable('pagos');
        $pagos = $hayPagos ? Pago::query() : null;
        if ($pagos) {
            $this->rangoFechas($pagos, 'fecha_pago', $f);
        }

        return [
            'usuarios' => [
                'total'     => (clone $usuarios)->count(),
                'porEstado' => $this->conteoPor((clone $usuarios)->toBase(), 'estado'),
                'recientes' => (clone $usuarios)->orderByDesc('fecha_registro')->orderByDesc('cedula')
                    ->limit(self::LIMITE_RECIENTES)->get(['cedula', 'nombre', 'fecha_registro']),
            ],
            'publicaciones' => [
                'total'     => (clone $publicaciones)->count(),
                'porEstado' => $this->conteoPor((clone $publicaciones)->toBase(), 'estado'),
                'recientes' => (clone $publicaciones)->orderByDesc('fecha_publicacion')->orderByDesc('id_labor')
                    ->limit(self::LIMITE_RECIENTES)->get(['id_labor', 'titulo', 'fecha_publicacion', 'estado']),
            ],
            'postulaciones' => [
                'total'     => (clone $postulaciones)->count(),
                'porEstado' => $this->conteoPor((clone $postulaciones)->toBase(), 'estado'),
                'recientes' => (clone $postulaciones)->orderByDesc('fecha_postulacion')->orderByDesc('id_postulacion')
                    ->limit(self::LIMITE_RECIENTES)->get(['id_postulacion', 'id_labor', 'cedula', 'fecha_postulacion', 'estado']),
            ],
            'contrataciones' => [
                'total'     => (clone $contrataciones)->count(),
                'porEstado' => $this->conteoPor((clone $contrataciones)->toBase(), 'estado'),
                'recientes' => (clone $contrataciones)->orderByDesc('fecha_inicio')->orderByDesc('id_contratacion')
                    ->limit(self::LIMITE_RECIENTES)->get(['id_contratacion', 'id_postulacion', 'fecha_inicio', 'monto_acordado', 'estado']),
            ],
            'pagos' => $pagos ? [
                'total'     => (clone $pagos)->count(),
                'porEstado' => $this->conteoPor((clone $pagos)->toBase(), 'estado'),
                'recientes' => (clone $pagos)->orderByDesc('fecha_pago')->orderByDesc('id_pago')
                    ->limit(self::LIMITE_RECIENTES)->get(['id_pago', 'id_contratacion', 'fecha_pago', 'monto', 'estado', 'es_simulado']),
            ] : ['total' => 0, 'porEstado' => [], 'recientes' => collect()],
        ];
    }

    /** Reporte 5: pagos simulados, ingresos y comisiones. */
    private function consultarPagos(array $f): array
    {
        if (! Schema::hasTable('pagos')) {
            return ['total' => 0, 'porEstado' => [], 'confirmados' => $this->totalesVacios(), 'filas' => collect()];
        }

        $base = Pago::query();
        if (in_array($f['estado'] ?? null, Pago::ESTADOS, true)) {
            $base->where('estado', $f['estado']);
        }
        $this->rangoFechas($base, 'fecha_pago', $f);

        $total = (clone $base)->count();

        $porEstado = (clone $base)->toBase()
            ->select('estado')
            ->selectRaw('COUNT(*) as cantidad')
            ->selectRaw(Pago::sumaCentavosSql('monto') . ' as monto')
            ->selectRaw(Pago::sumaCentavosSql('monto_comision') . ' as comision')
            ->selectRaw(Pago::sumaCentavosSql('monto_neto') . ' as neto')
            ->groupBy('estado')
            ->orderBy('estado')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->estado => [
                'cantidad' => (int) $r->cantidad,
                'monto'    => Pago::centavosATexto($r->monto),
                'comision' => Pago::centavosATexto($r->comision),
                'neto'     => Pago::centavosATexto($r->neto),
            ]])
            ->all();

        $confirmados = $porEstado[Pago::ESTADO_CONFIRMADO] ?? $this->totalesVacios();

        $filas = (clone $base)
            ->with([
                'contratacion:id_contratacion,id_postulacion',
                'contratacion.postulacion:id_postulacion,id_labor,cedula',
                'contratacion.postulacion.trabajador:cedula,nombre',
                'contratacion.postulacion.publicacion:id_labor,cedula,titulo',
                'contratacion.postulacion.publicacion.usuario:cedula,nombre',
            ])
            ->orderBy('fecha_pago')
            ->orderBy('id_pago')
            ->limit(self::LIMITE_FILAS)
            ->get();

        return compact('total', 'porEstado', 'confirmados', 'filas');
    }

    private function totalesVacios(): array
    {
        return ['cantidad' => 0, 'monto' => '0.00', 'comision' => '0.00', 'neto' => '0.00'];
    }

    // ── Utilidades de presentación JSON (sin consultas) ──

    /** @param array<int|string,int> $porEstado */
    private function activosInactivos(array $porEstado): array
    {
        return ['activos' => (int) ($porEstado[1] ?? 0), 'inactivos' => (int) ($porEstado[0] ?? 0)];
    }

    private function responder(Request $request, string $reporte, array $filtros, array $datos): JsonResponse
    {
        $ahora = now();
        $user = $request->user();

        return response()->json([
            'reporte'           => $reporte,
            'generado_en'       => $ahora->toIso8601String(),
            'generado_en_texto' => $ahora->format('d/m/Y H:i'),
            'generado_por'      => ['cedula' => $user?->cedula, 'nombre' => $user?->nombre],
            'filtros'           => (object) $filtros,
            'limite_filas'      => self::LIMITE_FILAS,
        ] + $datos, 200, [], self::JSON_FLAGS);
    }

    // ── PDF: definición de cada reporte (solo presentación de los datos ya construidos) ──

    /**
     * Valida los filtros (422 JSON si no son válidos), construye los datos con el MISMO método que la
     * acción JSON y devuelve el PDF generado en memoria (Output('', 'S'): sin archivos temporales).
     */
    private function descargarPdf(Request $request, string $tipo): Response
    {
        // Categorías no admite filtros (la acción JSON tampoco los valida).
        $filtros = $tipo === 'categorias' ? [] : $this->filtros($request);

        $definicion = match ($tipo) {
            'usuarios'      => $this->pdfUsuarios($this->datosUsuarios($filtros), $filtros),
            'publicaciones' => $this->pdfPublicaciones($this->datosPublicaciones($filtros), $filtros),
            'categorias'    => $this->pdfCategorias($this->datosCategorias()),
            'actividad'     => $this->pdfActividad($this->datosActividad($filtros), $filtros),
            'pagos'         => $this->pdfPagos($this->datosPagos($filtros), $filtros),
        };

        $ahora = now();
        [$orientacion, $titulo] = self::PDF_TIPOS[$tipo];

        $contenido = $this->generarPdf($orientacion, $titulo, $definicion, $ahora, (string) ($request->user()?->nombre ?? ''));

        return response($contenido, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="RedWork_Reporte_' . ucfirst($tipo) . '_' . $ahora->format('Ymd') . '.pdf"',
            'Content-Length'      => (string) strlen($contenido),
            'Cache-Control'       => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Nota de truncado (mismo texto que la vista) + límite. */
    private function pdfNotaLimite(array $d): ?string
    {
        return $d['total'] > $d['mostrados']
            ? 'Se muestran los primeros ' . $d['mostrados'] . ' de ' . $d['total'] . ' registros (límite ' . self::LIMITE_FILAS
                . ' filas); los totales consideran todos.'
            : null;
    }

    private function pdfUsuarios(array $d, array $f): array
    {
        $roles = array_map(fn ($r) => [$r['rol'], (string) $r['total']], $d['por_rol']);
        if ($d['sin_rol'] > 0) {
            $roles[] = ['(sin rol)', (string) $d['sin_rol']];
        }

        return [
            'filtros' => 'Filtros: rol = ' . ($f['rol'] ?? 'todos') . ' · estado = '
                . (isset($f['estado']) ? ($f['estado'] === '1' ? 'activo' : 'inactivo') : 'todos'),
            'resumen' => [['Usuarios', $d['total']], ['Activos', $d['por_estado']['activos']], ['Inactivos', $d['por_estado']['inactivos']]],
            'secciones' => [
                [
                    'titulo'   => 'Totales por rol',
                    'columnas' => [['Rol', 200.0, 'L'], ['Usuarios', 55.4, 'R']],
                    'filas'    => $roles,
                    'notas'    => ['Un usuario con varios roles se cuenta en cada uno de ellos.'],
                ],
                [
                    'titulo'   => 'Listado',
                    'columnas' => [['Cédula', 22.0, 'L'], ['Nombre', 50.0, 'L'], ['Email', 60.0, 'L'], ['Ciudad', 30.0, 'L'],
                        ['Rol(es)', 38.0, 'L'], ['Estado', 20.0, 'L'], ['Fecha de registro', 35.4, 'L']],
                    'filas'    => array_map(fn ($u) => [
                        (string) $u['cedula'], (string) $u['nombre'], (string) $u['email'], (string) ($u['ciudad'] ?? '—'),
                        $u['roles'] ? implode(', ', $u['roles']) : '—', $u['estado'] === 1 ? 'Activo' : 'Inactivo', (string) $u['fecha_registro'],
                    ], $d['filas']),
                    'total'    => [['Total de usuarios', 6, 'L'], [(string) $d['total'], 1, 'R']],
                    'notas'    => array_filter([$this->pdfNotaLimite($d)]),
                ],
            ],
        ];
    }

    private function pdfPublicaciones(array $d, array $f): array
    {
        $cat = null;
        if (isset($f['categoria'])) {
            $cat = collect($d['opciones']['categorias'])->firstWhere('id_categoria', (int) $f['categoria']);
        }

        return [
            'filtros' => 'Filtros: estado = ' . (isset($f['estado']) ? ($f['estado'] === '1' ? 'activa' : 'inactiva') : 'todos')
                . ' · categoría = ' . (isset($f['categoria']) ? ($cat ? $cat['nombre_categoria'] : '#' . $f['categoria']) : 'todas')
                . ' · fecha de publicación: ' . ($f['desde'] ?? 'inicio') . ' a ' . ($f['hasta'] ?? 'hoy'),
            'resumen' => [['Publicaciones', $d['total']], ['Activas', $d['por_estado']['activos']], ['Inactivas', $d['por_estado']['inactivos']],
                ['Suma de pagos ofertados', 'Bs ' . $d['suma_pago']]],
            'notas_resumen' => ['El pago de una publicación es el monto ofertado; no es dinero comprometido ni cobrado.'],
            'secciones' => [
                [
                    'titulo'   => 'Listado',
                    'columnas' => [['ID', 14.0, 'L'], ['Título', 60.0, 'L'], ['Tipo', 22.0, 'L'], ['Categoría', 38.0, 'L'],
                        ['Contratador', 45.0, 'L'], ['Fecha', 26.0, 'L'], ['Pago (Bs)', 28.0, 'R'], ['Estado', 22.4, 'L']],
                    'filas'    => array_map(fn ($p) => [
                        (string) $p['id_labor'], (string) $p['titulo'], (string) $p['tipo'], (string) ($p['categoria'] ?? '(sin categoría)'),
                        (string) ($p['contratador'] ?? '(usuario ' . $p['cedula'] . ')'), (string) $p['fecha_publicacion'], $p['pago'],
                        $p['estado'] === 1 ? 'Activa' : 'Inactiva',
                    ], $d['filas']),
                    'total'    => [['Total (' . $d['total'] . ' publicaciones)', 6, 'L'], [$d['suma_pago'], 1, 'R'], ['', 1, 'L']],
                    'notas'    => array_filter([$this->pdfNotaLimite($d)]),
                ],
            ],
        ];
    }

    private function pdfCategorias(array $d): array
    {
        return [
            'filtros' => 'Filtros: sin filtros (todas las categorías)',
            'resumen' => [['Categorías', $d['total']], ['Activas', $d['por_estado']['activos']], ['Inactivas', $d['por_estado']['inactivos']]],
            'secciones' => [
                [
                    'titulo'   => 'Listado',
                    'columnas' => [['ID', 20.0, 'L'], ['Nombre', 101.9, 'L'], ['Estado', 30.0, 'L'], ['Nº de publicaciones', 40.0, 'R']],
                    'filas'    => array_map(fn ($c) => [
                        (string) $c['id_categoria'], (string) $c['nombre_categoria'], $c['estado'] === 1 ? 'Activa' : 'Inactiva',
                        (string) $c['publicaciones_count'],
                    ], $d['filas']),
                    'total'    => [['Total de publicaciones en categorías existentes', 3, 'L'], [(string) $d['total_publicaciones'], 1, 'R']],
                    'notas'    => ['El número de publicaciones incluye activas e inactivas. Las publicaciones con una categoría inexistente no se cuentan.'],
                ],
            ],
        ];
    }

    private function pdfActividad(array $d, array $f): array
    {
        $entidades = [
            'usuarios'       => ['Usuarios registrados', 'fecha_registro'],
            'publicaciones'  => ['Publicaciones', 'fecha_publicacion'],
            'postulaciones'  => ['Postulaciones', 'fecha_postulacion'],
            'contrataciones' => ['Contrataciones', 'fecha_inicio'],
            'pagos'          => ['Pagos (simulados)', 'fecha_pago'],
        ];
        $etiqueta = fn (string $clave, string $estado) => in_array($clave, ['usuarios', 'publicaciones'], true)
            ? ((int) $estado === 1 ? 'activo' : 'inactivo') : $estado;

        $resumen = [];
        foreach ($entidades as $clave => [$titulo, $columna]) {
            $s = $d[$clave];
            $resumen[] = [$titulo, $columna, (string) $s['total'], $s['por_estado']
                ? implode(' · ', array_map(fn ($e) => $etiqueta($clave, $e['estado']) . ': ' . $e['total'], $s['por_estado']))
                : 'Sin registros'];
        }
        $activa = fn ($e) => (int) $e === 1 ? 'Activa' : 'Inactiva';

        return [
            'filtros' => 'Periodo: ' . ($f['desde'] ?? 'inicio') . ' a ' . ($f['hasta'] ?? 'hoy'),
            'resumen' => array_map(fn ($c) => [$entidades[$c][0], $d[$c]['total']], array_keys($entidades)),
            'secciones' => [
                [
                    'titulo'   => 'Resumen',
                    'columnas' => [['Entidad', 45.0, 'L'], ['Fecha usada', 40.0, 'L'], ['Total', 22.0, 'R'], ['Desglose por estado', 84.9, 'L']],
                    'filas'    => $resumen,
                    'notas'    => ['Limitaciones: las postulaciones canceladas se eliminan físicamente (no aparecen); no existe registro de inicios de sesión; '
                        . 'las fechas de publicaciones, postulaciones, contrataciones y pagos no tienen hora. Las contrataciones se fechan por fecha de inicio.'],
                ],
                [
                    'titulo'   => 'Usuarios registrados recientes',
                    'columnas' => [['Cédula', 40.0, 'L'], ['Nombre', 111.9, 'L'], ['Fecha de registro', 40.0, 'L']],
                    'filas'    => array_map(fn ($u) => [(string) $u['cedula'], (string) $u['nombre'], (string) $u['fecha_registro']], $d['usuarios']['recientes']),
                ],
                [
                    'titulo'   => 'Publicaciones recientes',
                    'columnas' => [['ID', 20.0, 'L'], ['Título', 111.9, 'L'], ['Fecha', 35.0, 'L'], ['Estado', 25.0, 'L']],
                    'filas'    => array_map(fn ($p) => [(string) $p['id_labor'], (string) $p['titulo'], (string) $p['fecha_publicacion'], $activa($p['estado'])],
                        $d['publicaciones']['recientes']),
                ],
                [
                    'titulo'   => 'Postulaciones recientes',
                    'columnas' => [['ID', 30.0, 'L'], ['Publicación', 30.0, 'L'], ['Cédula trabajador', 40.0, 'L'], ['Fecha', 46.9, 'L'], ['Estado', 45.0, 'L']],
                    'filas'    => array_map(fn ($p) => [(string) $p['id_postulacion'], (string) $p['id_labor'], (string) $p['cedula'],
                        (string) $p['fecha_postulacion'], (string) $p['estado']], $d['postulaciones']['recientes']),
                ],
                [
                    'titulo'   => 'Contrataciones recientes',
                    'columnas' => [['ID', 30.0, 'L'], ['Postulación', 35.0, 'L'], ['Fecha de inicio', 40.0, 'L'], ['Monto acordado (Bs)', 46.9, 'R'], ['Estado', 40.0, 'L']],
                    'filas'    => array_map(fn ($c) => [(string) $c['id_contratacion'], (string) $c['id_postulacion'], (string) $c['fecha_inicio'],
                        $c['monto_acordado'], (string) $c['estado']], $d['contrataciones']['recientes']),
                ],
                [
                    'titulo'   => 'Pagos recientes (simulados)',
                    'simulado' => true,
                    'columnas' => [['ID', 22.0, 'L'], ['Contratación', 32.0, 'L'], ['Fecha de pago', 35.0, 'L'], ['Monto (Bs)', 35.0, 'R'],
                        ['Estado', 37.9, 'L'], ['Simulado', 30.0, 'L']],
                    'filas'    => array_map(fn ($p) => [(string) $p['id_pago'], (string) $p['id_contratacion'], (string) $p['fecha_pago'],
                        (string) $p['monto'], (string) $p['estado'], $p['es_simulado'] ? 'Sí' : 'No'], $d['pagos']['recientes']),
                ],
            ],
        ];
    }

    private function pdfPagos(array $d, array $f): array
    {
        $c = $d['confirmados'];
        $persona = fn ($x) => $x ? ($x['nombre'] ?? '(usuario ' . $x['cedula'] . ')') : '—';

        return [
            'simulado' => true,
            'filtros'  => 'Filtros: estado = ' . ($f['estado'] ?? 'todos') . ' · fecha de pago: ' . ($f['desde'] ?? 'inicio') . ' a ' . ($f['hasta'] ?? 'hoy'),
            'resumen'  => [['Pagos', $d['total']], ['Cobrado (confirmados)', 'Bs ' . $c['monto']],
                ['Comisión RedWork (confirmados)', 'Bs ' . $c['comision']], ['Neto trabajadores (confirmados)', 'Bs ' . $c['neto']]],
            'notas_resumen' => ['Solo los pagos confirmados se consideran cobrados. Los pendientes no están confirmados y los anulados no cuentan como ingreso. '
                . $d['aviso']],
            'secciones' => [
                [
                    'titulo'   => 'Totales por estado',
                    'columnas' => [['Estado', 55.4, 'L'], ['Cantidad', 40.0, 'R'], ['Monto (Bs)', 55.0, 'R'], ['Comisión (Bs)', 50.0, 'R'], ['Neto (Bs)', 55.0, 'R']],
                    'filas'    => array_map(fn ($t) => [$t['estado'], (string) $t['cantidad'], $t['monto'], $t['comision'], $t['neto']], $d['por_estado']),
                ],
                [
                    'titulo'   => 'Listado',
                    'fuente'   => 7.0,
                    'columnas' => [['ID', 11.0, 'L'], ['Fecha de pago', 19.0, 'L'], ['Contratación', 40.4, 'L'], ['Contratador', 30.0, 'L'],
                        ['Trabajador', 30.0, 'L'], ['Método', 21.0, 'L'], ['Estado', 19.0, 'L'], ['Monto (Bs)', 20.0, 'R'], ['% comisión', 14.0, 'R'],
                        ['Comisión (Bs)', 18.0, 'R'], ['Neto (Bs)', 18.0, 'R'], ['Simulado', 15.0, 'L']],
                    'filas'    => array_map(fn ($p) => [
                        (string) $p['id_pago'], (string) $p['fecha_pago'],
                        '#' . $p['id_contratacion'] . ($p['titulo'] !== null ? ' — ' . $p['titulo'] : ''),
                        (string) $persona($p['contratador']), (string) $persona($p['trabajador']), (string) $p['metodo'], (string) $p['estado'],
                        (string) $p['monto'], (string) $p['porcentaje_comision'], (string) $p['monto_comision'], (string) $p['monto_neto'],
                        $p['es_simulado'] ? 'Sí' : 'No',
                    ], $d['filas']),
                    'total'    => [['Confirmados (' . $c['cantidad'] . ')', 7, 'L'], [$c['monto'], 1, 'R'], ['', 1, 'L'], [$c['comision'], 1, 'R'],
                        [$c['neto'], 1, 'R'], ['', 1, 'L']],
                    'notas'    => array_filter([$this->pdfNotaLimite($d)]),
                ],
            ],
        ];
    }

    // ── PDF: motor de dibujo (TCPDF 7, en memoria) ──

    /**
     * Documento TCPDF con encabezado y pie propios (subclase anónima; TCPDF 7 dibuja encabezados y
     * pies al cerrar el documento, cuando ya conoce el total de páginas). Error() lanza excepción
     * en lugar de imprimir y terminar el proceso.
     */
    private function crearPdf(string $orientacion, array $cabecera): \TCPDF
    {
        $pdf = new class($orientacion, 'mm', 'LETTER', true, 'UTF-8', false) extends \TCPDF {
            /** @var array{logo:string,fecha:string,hora:string,numero:string,por:string,anio:string,margen:float,banda:float} */
            public array $cab = [];

            public function Error($_msg)
            {
                throw new \RuntimeException('Error al generar el PDF: ' . $_msg);
            }

            public function Header()
            {
                $c = $this->cab;
                $w = $this->getPageWidth();
                $m = $c['margen'];

                $this->setFillColor(6, 13, 20);
                $this->Rect(0, 0, $w, $c['banda'], 'F');
                $this->setFillColor(255, 90, 95);
                $this->Rect(0, $c['banda'], $w, 1.4, 'F');

                // Logo sobre fondo blanco (el texto «Work» del logo es oscuro).
                $this->setFillColor(255, 255, 255);
                $this->RoundedRect($m, 5, 58, 16, 2, '1111', 'F');
                if ($c['logo'] !== '') {
                    $this->Image('@' . $c['logo'], $m + 2, 7, 54, 54 * 214 / 960);
                }

                $x = $m + 64;
                $this->setTextColor(255, 255, 255);
                $this->setFont('helvetica', 'B', 17);
                $this->setXY($x, 6.5);
                $this->Cell(60, 8, 'RED-WORK', 0, 0, 'L');
                $this->setFont('helvetica', '', 9);
                $this->setTextColor(207, 214, 223);
                $this->setXY($x, 14.5);
                $this->Cell(70, 5, 'Sistema de gestión de empleos', 0, 0, 'L');

                $ancho = 92;
                $xd = $w - $m - $ancho;
                $this->setFont('helvetica', '', 8);
                $this->setXY($xd, 5.5);
                $this->Cell($ancho, 4.5, 'Fecha: ' . $c['fecha'] . '   Hora: ' . $c['hora'], 0, 0, 'R');
                $this->setFont('helvetica', 'B', 8);
                $this->setTextColor(255, 90, 95);
                $this->setXY($xd, 10.5);
                $this->Cell($ancho, 4.5, 'Reporte N.º ' . $c['numero'], 0, 0, 'R');
                $this->setFont('helvetica', '', 8);
                $this->setTextColor(207, 214, 223);
                $this->setXY($xd, 15.5);
                $this->Cell($ancho, 4.5, 'Generado por: ' . $c['por'], 0, 0, 'R');
            }

            public function Footer()
            {
                $c = $this->cab;
                $w = $this->getPageWidth();
                $m = $c['margen'];
                $y = $this->getPageHeight() - 14;

                $this->setDrawColor(255, 90, 95);
                $this->setLineWidth(0.4);
                $this->Line($m, $y, $w - $m, $y);
                $this->setFont('helvetica', '', 7.5);
                $this->setTextColor(91, 102, 117);
                $this->setXY($m, $y + 1.5);
                $this->Cell(70, 4.5, 'Sistema de gestión de empleos', 0, 0, 'L');
                $this->setXY($m, $y + 1.5);
                $this->Cell($w - 2 * $m, 4.5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
                $this->setXY($m, $y + 1.5);
                $this->Cell($w - 2 * $m, 4.5, 'RedWork © ' . $c['anio'], 0, 0, 'R');
            }
        };

        $pdf->cab = $cabecera + ['margen' => self::PDF_MARGEN, 'banda' => self::PDF_ALTO_BANDA];
        $pdf->setCreator('RedWork');
        $pdf->setAuthor('RedWork');
        $pdf->setMargins(self::PDF_MARGEN, self::PDF_MARGEN_SUP, self::PDF_MARGEN);
        $pdf->setHeaderMargin(0);
        $pdf->setFooterMargin(14);
        $pdf->setAutoPageBreak(true, self::PDF_MARGEN_INF);
        $pdf->setCellPaddings(self::PDF_PAD, 0, self::PDF_PAD, 0);

        return $pdf;
    }

    /** Dibuja el documento completo y lo devuelve como cadena (sin salida previa ni archivos). */
    private function generarPdf(string $orientacion, string $titulo, array $def, Carbon $ahora, string $generadoPor): string
    {
        $logo = public_path('images/redwork-logo.png');
        $pdf = $this->crearPdf($orientacion, [
            'logo'   => is_file($logo) ? (string) file_get_contents($logo) : '',
            'fecha'  => $ahora->format('d/m/Y'),
            'hora'   => $ahora->format('H:i:s'),
            'numero' => $ahora->format('Ymd-His'),
            'por'    => mb_strimwidth($generadoPor !== '' ? $generadoPor : '—', 0, 45, '…'),
            'anio'   => $ahora->format('Y'),
        ]);
        $pdf->setTitle('RedWork — ' . $titulo);
        $pdf->setSubject($titulo);
        $pdf->AddPage();

        $util = $this->pdfAnchoUtil($pdf);
        $m = self::PDF_MARGEN;

        // Título + franja de acento.
        $pdf->setTextColor(6, 13, 20);
        $pdf->setFont('helvetica', 'B', 15);
        $pdf->setXY($m, self::PDF_MARGEN_SUP);
        $pdf->Cell($util, 8, mb_strtoupper($titulo), 0, 1, 'L');
        $pdf->setFillColor(255, 90, 95);
        $pdf->Rect($m, $pdf->GetY() + 0.5, 40, 0.9, 'F');
        $pdf->Ln(3);

        // Filtros aplicados.
        $pdf->setFont('helvetica', '', 8.5);
        $pdf->setTextColor(91, 102, 117);
        $this->pdfParrafo($pdf, $def['filtros'], 4);
        $pdf->Ln(2);

        if (! empty($def['simulado'])) {
            $this->pdfBandaSimulado($pdf);
        }

        // Resumen estadístico en celdas de colores.
        $this->pdfTituloSeccion($pdf, 'RESUMEN ESTADÍSTICO');
        $this->pdfResumen($pdf, $def['resumen']);
        foreach ($def['notas_resumen'] ?? [] as $nota) {
            $this->pdfNota($pdf, $nota);
        }

        foreach ($def['secciones'] as $seccion) {
            $this->pdfSeccion($pdf, $seccion);
        }

        // Nota final.
        $pdf->Ln(4);
        $this->pdfEspacio($pdf, 14);
        $pdf->setDrawColor(217, 222, 229);
        $pdf->setLineWidth(0.2);
        $pdf->Line($m, $pdf->GetY(), $m + $util, $pdf->GetY());
        $pdf->Ln(1.5);
        $pdf->setFont('helvetica', 'I', 7.5);
        $pdf->setTextColor(91, 102, 117);
        $this->pdfParrafo($pdf, 'Generado automáticamente por el Sistema RedWork con datos reales de la base. '
            . 'Las fechas del dominio no registran hora. Información confidencial: uso exclusivo de la administración de RedWork.', 3.6);

        return $pdf->Output('', 'S');
    }

    private function pdfAnchoUtil(\TCPDF $pdf): float
    {
        return round($pdf->getPageWidth() - 2 * self::PDF_MARGEN, 4);
    }

    /** Límite inferior del área de contenido (antes del pie). */
    private function pdfLimite(\TCPDF $pdf): float
    {
        return $pdf->getPageHeight() - $pdf->getBreakMargin();
    }

    /** Salto de página controlado: añade página si no caben $alto mm. Devuelve true si saltó. */
    private function pdfEspacio(\TCPDF $pdf, float $alto): bool
    {
        if ($pdf->GetY() + $alto <= $this->pdfLimite($pdf)) {
            return false;
        }
        $pdf->AddPage();
        $pdf->setXY(self::PDF_MARGEN, self::PDF_MARGEN_SUP);

        return true;
    }

    private function pdfTituloSeccion(\TCPDF $pdf, string $texto): void
    {
        $pdf->setFont('helvetica', 'B', 10.5);
        $pdf->setTextColor(6, 13, 20);
        $pdf->setX(self::PDF_MARGEN);
        $pdf->Cell($this->pdfAnchoUtil($pdf), 6.5, $texto, 0, 1, 'L');
        $pdf->Ln(0.5);
    }

    /** Párrafo de ancho completo con ajuste de línea propio (sin depender del ajuste del motor). */
    private function pdfParrafo(\TCPDF $pdf, string $texto, float $altoLinea, string $align = 'L'): void
    {
        $util = $this->pdfAnchoUtil($pdf);
        foreach ($this->pdfLineas($pdf, $texto, $util, 50) as $linea) {
            $this->pdfEspacio($pdf, $altoLinea);
            $pdf->setX(self::PDF_MARGEN);
            $pdf->Cell($util, $altoLinea, $linea, 0, 1, $align, false, '', 0, true);
        }
    }

    private function pdfNota(\TCPDF $pdf, string $texto): void
    {
        $pdf->Ln(1);
        $pdf->setFont('helvetica', 'I', 7.5);
        $pdf->setTextColor(91, 102, 117);
        $this->pdfParrafo($pdf, $texto, 3.6);
    }

    private function pdfBandaSimulado(\TCPDF $pdf): void
    {
        $alto = 9.0;
        $this->pdfEspacio($pdf, $alto + 3);
        $util = $this->pdfAnchoUtil($pdf);
        $y = $pdf->GetY();
        $pdf->setFillColor(255, 244, 214);
        $pdf->setDrawColor(217, 154, 0);
        $pdf->setLineWidth(0.6);
        $pdf->Rect(self::PDF_MARGEN, $y, $util, $alto, 'DF');
        $pdf->setFont('helvetica', 'B', 10);
        $pdf->setTextColor(122, 82, 0);
        $pdf->setXY(self::PDF_MARGEN, $y);
        $pdf->Cell($util, $alto, self::BANDA_SIMULADO, 0, 1, 'C', false, '', 0, true);
        $pdf->Ln(3);
    }

    /** @param list<array{0:string,1:int|string}> $celdas [etiqueta, valor] */
    private function pdfResumen(\TCPDF $pdf, array $celdas): void
    {
        $colores = [[6, 13, 20], [21, 128, 61], [220, 60, 66], [29, 78, 216], [180, 83, 9]];
        $alto = 17.0;
        $sep = 3.0;
        $this->pdfEspacio($pdf, $alto + 2);
        $util = $this->pdfAnchoUtil($pdf);
        $n = count($celdas);
        $ancho = ($util - $sep * ($n - 1)) / $n;
        $y = $pdf->GetY();

        foreach (array_values($celdas) as $i => [$etiqueta, $valor]) {
            $x = self::PDF_MARGEN + $i * ($ancho + $sep);
            [$r, $g, $b] = $colores[$i % count($colores)];
            $pdf->setFillColor($r, $g, $b);
            $pdf->RoundedRect($x, $y, $ancho, $alto, 1.5, '1111', 'F');
            $pdf->setTextColor(255, 255, 255);
            $pdf->setFont('helvetica', 'B', 14);
            $pdf->setXY($x, $y + 2);
            $pdf->Cell($ancho, 7, (string) $valor, 0, 0, 'C', false, '', 0, true);
            $pdf->setFont('helvetica', '', 7.5);
            $lineas = $this->pdfLineas($pdf, $etiqueta, $ancho, 2);
            foreach ($lineas as $k => $linea) {
                $pdf->setXY($x, $y + 9.5 + $k * 3.3);
                $pdf->Cell($ancho, 3.3, $linea, 0, 0, 'C', false, '', 0, true);
            }
        }
        $pdf->setXY(self::PDF_MARGEN, $y + $alto + 2);
    }

    /**
     * Tabla con cabecera oscura, filas alternadas, cabecera repetida en cada página nueva,
     * fila de totales y notas. Los anchos de columna deben sumar EXACTAMENTE el ancho útil.
     */
    private function pdfSeccion(\TCPDF $pdf, array $s): void
    {
        $util = $this->pdfAnchoUtil($pdf);
        $anchos = array_map(fn ($c) => (float) $c[1], $s['columnas']);
        if (abs(array_sum($anchos) - $util) > 0.001) {
            throw new \LogicException('Los anchos de «' . $s['titulo'] . '» suman ' . array_sum($anchos) . ' mm; el ancho útil es ' . $util . ' mm.');
        }
        $aligns = array_map(fn ($c) => $c[2], $s['columnas']);
        $fuente = (float) ($s['fuente'] ?? 8.0);

        $cabecera = $this->pdfPrepararFila($pdf, array_map(fn ($c) => $c[0], $s['columnas']), $anchos, 'B', $fuente);
        $filas = $s['filas'];
        $primera = $filas ? $this->pdfPrepararFila($pdf, array_values($filas)[0], $anchos, '', $fuente) : ['alto' => 7.0];

        // Mantener juntos: (banda) + título + cabecera + primera fila.
        $pdf->Ln(3);
        $this->pdfEspacio($pdf, (! empty($s['simulado']) ? 12 : 0) + 7 + $cabecera['alto'] + $primera['alto']);
        if (! empty($s['simulado'])) {
            $this->pdfBandaSimulado($pdf);
        }
        $this->pdfTituloSeccion($pdf, $s['titulo']);
        $this->pdfDibujarFila($pdf, $cabecera, $anchos, $aligns, [6, 13, 20], [255, 255, 255], 'B', $fuente);

        if (! $filas) {
            $pdf->setFont('helvetica', 'I', $fuente);
            $vacia = $this->pdfPrepararFila($pdf, ['Sin registros'], [$util], 'I', $fuente);
            $this->pdfDibujarFila($pdf, $vacia, [$util], ['C'], [255, 255, 255], [91, 102, 117], 'I', $fuente);
        }

        foreach (array_values($filas) as $i => $celdas) {
            $fila = $this->pdfPrepararFila($pdf, $celdas, $anchos, '', $fuente);
            if ($this->pdfEspacio($pdf, $fila['alto'])) {
                $this->pdfDibujarFila($pdf, $cabecera, $anchos, $aligns, [6, 13, 20], [255, 255, 255], 'B', $fuente);
            }
            $fondo = $i % 2 === 1 ? [244, 246, 249] : [255, 255, 255];
            $this->pdfDibujarFila($pdf, $fila, $anchos, $aligns, $fondo, [27, 36, 48], '', $fuente);
        }

        if (! empty($s['total'])) {
            $tAnchos = [];
            $col = 0;
            foreach ($s['total'] as [$texto, $span, $align]) {
                $tAnchos[] = array_sum(array_slice($anchos, $col, $span));
                $col += $span;
            }
            $total = $this->pdfPrepararFila($pdf, array_map(fn ($t) => $t[0], $s['total']), $tAnchos, 'B', $fuente);
            if ($this->pdfEspacio($pdf, $total['alto'])) {
                $this->pdfDibujarFila($pdf, $cabecera, $anchos, $aligns, [6, 13, 20], [255, 255, 255], 'B', $fuente);
            }
            $this->pdfDibujarFila($pdf, $total, $tAnchos, array_map(fn ($t) => $t[2], $s['total']), [238, 241, 245], [6, 13, 20], 'B', $fuente);
        }

        foreach ($s['notas'] ?? [] as $nota) {
            $this->pdfNota($pdf, $nota);
        }
    }

    /** Ajusta cada celda a su ancho (máx. PDF_MAX_LINEAS líneas) y calcula el alto de la fila. */
    private function pdfPrepararFila(\TCPDF $pdf, array $celdas, array $anchos, string $estilo, float $fuente): array
    {
        $pdf->setFont('helvetica', $estilo, $fuente);
        $lineas = [];
        $max = 1;
        foreach (array_values($anchos) as $i => $ancho) {
            $lineas[$i] = $this->pdfLineas($pdf, (string) ($celdas[$i] ?? ''), $ancho, self::PDF_MAX_LINEAS);
            $max = max($max, count($lineas[$i]));
        }
        $altoLinea = round($fuente * 0.3528 * 1.25, 3);

        return ['lineas' => $lineas, 'altoLinea' => $altoLinea, 'alto' => $max * $altoLinea + 2.2];
    }

    private function pdfDibujarFila(\TCPDF $pdf, array $fila, array $anchos, array $aligns, array $fondo, array $color, string $estilo, float $fuente): void
    {
        $y = $pdf->GetY();
        $x = self::PDF_MARGEN;
        $pdf->setFont('helvetica', $estilo, $fuente);
        $pdf->setDrawColor(217, 222, 229);
        $pdf->setLineWidth(0.2);
        foreach (array_values($anchos) as $i => $ancho) {
            $pdf->setFillColor(...$fondo);
            $pdf->Rect($x, $y, $ancho, $fila['alto'], 'DF');
            $pdf->setTextColor(...$color);
            foreach ($fila['lineas'][$i] as $k => $linea) {
                $pdf->setXY($x, $y + 1.1 + $k * $fila['altoLinea']);
                $pdf->Cell($ancho, $fila['altoLinea'], $linea, 0, 0, $aligns[$i] ?? 'L', false, '', 0, true);
            }
            $x += $ancho;
        }
        $pdf->setXY(self::PDF_MARGEN, $y + $fila['alto']);
    }

    /**
     * Ajuste de línea por palabras con la métrica de la fuente actual: corta palabras más largas que
     * la celda y trunca con «…» al superar $max líneas. Así ninguna línea desborda su celda.
     *
     * @return list<string>
     */
    private function pdfLineas(\TCPDF $pdf, string $texto, float $ancho, int $max): array
    {
        $disponible = $ancho - 2 * self::PDF_PAD - 0.4;
        $texto = mb_substr(trim($texto), 0, self::PDF_MAX_CARACTERES);
        $cabe = fn (string $t) => $pdf->GetStringWidth($t) <= $disponible;

        $lineas = [];
        foreach (preg_split('/\R/u', $texto) ?: [''] as $parrafo) {
            $actual = '';
            foreach (preg_split('/\s+/u', trim($parrafo)) ?: [''] as $palabra) {
                while ($palabra !== '' && ! $cabe($palabra)) {
                    $k = 1;
                    $n = mb_strlen($palabra);
                    while ($k < $n && $cabe(mb_substr($palabra, 0, $k + 1))) {
                        $k++;
                    }
                    if ($actual !== '') {
                        $lineas[] = $actual;
                        $actual = '';
                    }
                    $lineas[] = mb_substr($palabra, 0, $k);
                    $palabra = mb_substr($palabra, $k);
                }
                if ($palabra === '') {
                    continue;
                }
                $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
                if ($cabe($prueba)) {
                    $actual = $prueba;
                } else {
                    $lineas[] = $actual;
                    $actual = $palabra;
                }
            }
            $lineas[] = $actual;
        }

        if (count($lineas) > $max) {
            $lineas = array_slice($lineas, 0, $max);
            $ultima = rtrim($lineas[$max - 1]) . '…';
            while (mb_strlen($ultima) > 1 && ! $cabe($ultima)) {
                $ultima = rtrim(mb_substr($ultima, 0, -2)) . '…';
            }
            $lineas[$max - 1] = $ultima;
        }

        return $lineas;
    }
}
