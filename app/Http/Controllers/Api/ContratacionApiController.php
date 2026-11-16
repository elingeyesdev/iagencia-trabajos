<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contratacion;
use App\Models\Postulacion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratacionApiController extends Controller
{
    // ── Admin ──
    /**
     * Agrega contratador / trabajador / publicacion al nivel raíz del JSON
     * (así lo esperan las tablas del dashboard admin y contratador).
     */
    private function aplanar($contrataciones)
    {
        $contrataciones->each(function ($c) {
            $pub = $c->postulacion?->publicacion;
            $c->setRelation('trabajador', $c->postulacion?->trabajador);
            $c->setRelation('publicacion', $pub);
            $c->setRelation('contratador', $pub?->usuario);
        });

        return $contrataciones;
    }

    public function index()
    {
        $contrataciones = Contratacion::with([
                'postulacion.publicacion.usuario:cedula,nombre',
                'postulacion.trabajador:cedula,nombre',
            ])
            ->orderByDesc('id_contratacion')
            ->get();

        return response()->json($this->aplanar($contrataciones));
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $contratacion = Contratacion::lockForUpdate()->findOrFail($id);

            // Protección del historial financiero (pagos: Tarea 3.1).
            if ($contratacion->tieneHistorialFinanciero()) {
                return response()->json([
                    'message' => 'No se puede eliminar una contratación con pagos registrados.',
                ], 422);
            }

            $contratacion->delete();

            return response()->json(['message' => 'Contratación eliminada.']);
        });
    }

    // ── Contratador ──
    public function misContrataciones(Request $request)
    {
        $cedula = $request->user()->cedula;

        $contrataciones = Contratacion::with([
                'postulacion.publicacion',
                'postulacion.trabajador:cedula,nombre,email,telefono',
                'calificaciones',
            ])
            ->whereHas('postulacion.publicacion', fn ($q) => $q->where('cedula', $cedula))
            ->orderByDesc('id_contratacion')
            ->get();

        return response()->json($this->aplanar($contrataciones));
    }

    public function store(Request $request)
    {
        // monto_acordado: decimal(10,2) en BD → máximo 99999999.99 y hasta 2 decimales.
        $request->validate([
            'id_postulacion' => 'required|exists:postulaciones,id_postulacion',
            'fecha_inicio'   => 'required|date',
            'monto_acordado' => 'required|numeric|gt:0|max:99999999.99|decimal:0,2',
        ], [
            'monto_acordado.required' => 'El monto acordado es obligatorio.',
            'monto_acordado.numeric'  => 'El monto acordado debe ser un número.',
            'monto_acordado.gt'       => 'El monto acordado debe ser mayor que cero.',
            'monto_acordado.max'      => 'El monto acordado no puede superar 99.999.999,99.',
            'monto_acordado.decimal'  => 'El monto acordado admite como máximo 2 decimales.',
        ]);

        $cedula = auth()->id();

        try {
            $contratacion = DB::transaction(function () use ($request, $cedula) {
                // Bloqueo de la postulación: las comprobaciones y la creación son atómicas.
                $postulacion = Postulacion::lockForUpdate()->findOrFail($request->id_postulacion);
                $postulacion->load('publicacion');

                if ($postulacion->publicacion?->cedula != $cedula) {
                    return response()->json(['message' => 'No autorizado.'], 403);
                }

                if ($postulacion->estado !== 'aceptada') {
                    return response()->json(['message' => 'La postulación debe estar aceptada antes de contratar.'], 422);
                }

                if ($postulacion->tieneContratacion()) {
                    return response()->json(['message' => 'Esta postulación ya tiene una contratación registrada.'], 422);
                }

                return Contratacion::create([
                    'id_postulacion' => $postulacion->id_postulacion,
                    'fecha_inicio'   => $request->fecha_inicio,
                    'monto_acordado' => $request->monto_acordado,
                    'estado'         => 'activa',
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // UNIQUE(id_postulacion): otra petición concurrente creó la contratación.
            return response()->json(['message' => 'Esta postulación ya tiene una contratación registrada.'], 422);
        }

        if ($contratacion instanceof JsonResponse) {
            return $contratacion;
        }

        return response()->json([
            'message' => 'Contratación registrada.',
            'data'    => $contratacion->load('postulacion.trabajador'),
        ], 201);
    }

    public function actualizarEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:finalizada,cancelada',
        ]);

        $cedula = auth()->id();

        // Transiciones válidas: activa → finalizada | activa → cancelada.
        $contratacion = DB::transaction(function () use ($id, $request, $cedula) {
            // Bloqueo de la contratación y re-validación dentro del bloqueo.
            $contratacion = Contratacion::lockForUpdate()->findOrFail($id);
            $contratacion->load('postulacion.publicacion');

            if ($contratacion->postulacion?->publicacion?->cedula != $cedula) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }

            if ($contratacion->estado !== 'activa') {
                return response()->json(['message' => 'Solo una contratación activa puede cambiar de estado.'], 422);
            }

            $contratacion->update([
                'estado'    => $request->estado,
                'fecha_fin' => now()->toDateString(),
            ]);

            return $contratacion;
        });

        if ($contratacion instanceof JsonResponse) {
            return $contratacion;
        }

        return response()->json([
            'message' => 'Contratación actualizada.',
            'data'    => $contratacion->fresh(),
        ]);
    }
}