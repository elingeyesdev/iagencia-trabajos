<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calificacion;
use App\Models\Contratacion;
use App\Models\Postulacion;
use App\Models\Postulante;
use App\Models\Publicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostulacionApiController extends Controller
{
    // ════════════════════════════════════════════════════════════
    //  TRABAJADOR
    // ════════════════════════════════════════════════════════════

    /**
     * Datos del dashboard del trabajador.
     */
    public function dashboardWorker(Request $request)
    {
        $cedula = $request->user()->cedula;

        $publicaciones = Publicacion::with(['categoria', 'usuario:cedula,nombre'])
            ->where('estado', 1)
            ->orderByDesc('fecha_publicacion')
            ->orderByDesc('id_labor')
            ->get();

        // id_labor => estado de MI postulación (cualquier estado)
        $misPostulaciones = Postulacion::where('cedula', $cedula)
            ->pluck('estado', 'id_labor')
            ->toArray();

        $postulanteInfo = Postulante::where('cedula', $cedula)->first();

        $calificaciones = Calificacion::with('calificador:cedula,nombre')
            ->where('cedula_calificado', $cedula)
            ->orderByDesc('fecha')
            ->orderByDesc('id_calificacion')
            ->take(20)
            ->get();

        $totalCalificaciones = Calificacion::where('cedula_calificado', $cedula)->count();
        $promedio = $totalCalificaciones
            ? round(Calificacion::where('cedula_calificado', $cedula)->avg('puntuacion'), 1)
            : null;

        $completados = Contratacion::where('estado', 'finalizada')
            ->whereHas('postulacion', fn ($q) => $q->where('cedula', $cedula))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'publicaciones'          => $publicaciones,
                'mis_postulaciones'      => $misPostulaciones,
                'mis_postulaciones_ids'  => array_keys($misPostulaciones),
                'postulante_info'        => $postulanteInfo,
                'calificaciones'         => $calificaciones,
                'promedio_calificacion'  => $promedio,
                'total_calificaciones'   => $totalCalificaciones,
                'completados_count'      => $completados,
            ],
        ], 200);
    }

    /**
     * Postulaciones del trabajador autenticado (con contratación y calificaciones).
     */
    public function misPostulaciones(Request $request)
    {
        $postulaciones = Postulacion::with([
                'publicacion.categoria',
                'publicacion.usuario:cedula,nombre',
                'contratacion.calificaciones',
            ])
            ->where('cedula', $request->user()->cedula)
            ->orderByDesc('id_postulacion')
            ->get();

        return response()->json(['data' => $postulaciones], 200);
    }

    /**
     * Postularse a una oferta.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_labor' => 'required|exists:publicaciones,id_labor',
            'mensaje'  => 'nullable|string|max:500',
        ]);

        $cedula = $request->user()->cedula;

        $activa = Publicacion::where('id_labor', $request->id_labor)
            ->where('estado', 1)
            ->exists();

        if (!$activa) {
            return response()->json([
                'success' => false,
                'message' => 'Esta oferta ya no está disponible.',
            ], 422);
        }

        $existe = Postulacion::where('id_labor', $request->id_labor)
            ->where('cedula', $cedula)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya te has postulado a este trabajo.',
            ], 422);
        }

        $postulacion = Postulacion::create([
            'id_labor'          => $request->id_labor,
            'cedula'            => $cedula,
            'estado'            => 'pendiente',
            'mensaje'           => $request->mensaje,
            'fecha_postulacion' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Postulación registrada exitosamente.',
            'data'    => $postulacion,
        ], 201);
    }

    /**
     * Cancelar una postulación propia (solo si sigue pendiente).
     */
    public function cancelar(Request $request, $id)
    {
        $postulacion = Postulacion::where('id_postulacion', $id)
            ->where('cedula', $request->user()->cedula)
            ->first();

        if (!$postulacion) {
            return response()->json([
                'success' => false,
                'message' => 'Postulación no encontrada o sin permisos.',
            ], 404);
        }

        if ($postulacion->estado !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'Solo puedes cancelar postulaciones pendientes.',
            ], 422);
        }

        $postulacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Postulación cancelada correctamente.',
        ], 200);
    }

    /**
     * Crear o actualizar el perfil profesional (tabla postulantes) del trabajador.
     */
    public function guardarPerfilPostulante(Request $request)
    {
        $cedula     = $request->user()->cedula;
        $postulante = Postulante::where('cedula', $cedula)->first();

        $data = $request->validate([
            'habilidades'    => ($postulante ? 'sometimes' : 'required') . '|string|max:1000',
            'experiencia'    => 'nullable|string|max:2000',
            'disponibilidad' => 'nullable|string|max:100',
            'tarifa'         => 'nullable|numeric|min:0',
            'estado'         => 'nullable|integer|in:0,1',
        ]);

        if ($postulante) {
            $postulante->update($data);
        } else {
            $postulante = Postulante::create(array_merge(['estado' => 1], $data, ['cedula' => $cedula]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil profesional guardado.',
            'data'    => $postulante->fresh(),
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  CONTRATADOR  (las rutas ya existían en api.php, faltaban los métodos)
    // ════════════════════════════════════════════════════════════

    public function postulacionesRecibidas(Request $request)
    {
        $cedula = $request->user()->cedula;

        $postulaciones = Postulacion::with([
                'trabajador:cedula,nombre,email,telefono',
                'publicacion',
                'contratacion',
            ])
            ->whereHas('publicacion', fn ($q) => $q->where('cedula', $cedula))
            ->orderByDesc('id_postulacion')
            ->get();

        return response()->json($postulaciones);
    }

    /**
     * Aceptar / rechazar. Al aceptar se crea la contratación activa
     * (fecha de hoy y monto = pago de la publicación) para que el trabajador la vea.
     */
    public function actualizarEstado(Request $request, $id)
    {
        $request->validate(['estado' => 'required|in:aceptada,rechazada']);

        $postulacion = Postulacion::with('publicacion')->findOrFail($id);

        if ($postulacion->publicacion->cedula != $request->user()->cedula) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($postulacion->estado !== 'pendiente') {
            return response()->json(['message' => 'Esta postulación ya fue procesada.'], 422);
        }

        DB::transaction(function () use ($postulacion, $request) {
            $postulacion->update(['estado' => $request->estado]);

            if ($request->estado === 'aceptada') {
                Contratacion::firstOrCreate(
                    ['id_postulacion' => $postulacion->id_postulacion],
                    [
                        'fecha_inicio'   => now()->toDateString(),
                        'monto_acordado' => $postulacion->publicacion->pago,
                        'estado'         => 'activa',
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Postulación ' . $request->estado . '.',
            'data'    => $postulacion->fresh('contratacion'),
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  ADMIN
    // ════════════════════════════════════════════════════════════

    public function index()
    {
        return response()->json(
            Postulacion::with(['publicacion', 'trabajador'])->orderByDesc('id_postulacion')->get()
        );
    }

    public function destroy($id)
    {
        Postulacion::findOrFail($id)->delete();

        return response()->json(['message' => 'Postulación eliminada.']);
    }
}