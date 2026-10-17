<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calificacion;
use App\Models\Contratacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalificacionApiController extends Controller
{
    // ── Admin ──
    public function index()
    {
        $calificaciones = Calificacion::with(['contratacion', 'calificador', 'calificado'])
            ->orderByDesc('id_calificacion')
            ->get();

        // Alias que usa la tabla del dashboard admin (emisor / receptor)
        $calificaciones->each(function ($c) {
            $c->setRelation('emisor', $c->calificador);
            $c->setRelation('receptor', $c->calificado);
        });

        return response()->json($calificaciones);
    }

    public function destroy($id)
    {
        $calificacion = Calificacion::findOrFail($id);
        $calificacion->delete();

        return response()->json(['message' => 'Calificación eliminada.']);
    }

    // ── Contratador / Trabajador ──
    public function store(Request $request)
    {
        $request->validate([
            'id_contratacion' => 'required|exists:contrataciones,id_contratacion',
            'puntuacion'      => 'required|integer|min:1|max:5',
            'comentario'      => 'nullable|string',
        ]);

        $contratacion = Contratacion::with('postulacion.publicacion')->findOrFail($request->id_contratacion);

        if ($contratacion->estado !== 'finalizada') {
            return response()->json(['message' => 'Solo se puede calificar una contratación finalizada.'], 422);
        }

        $cedulaContratador = (int) $contratacion->postulacion->publicacion->cedula;
        $cedulaTrabajador  = (int) $contratacion->postulacion->cedula;
        $cedulaActual      = (int) auth()->id();

        if (!in_array($cedulaActual, [$cedulaContratador, $cedulaTrabajador])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $cedulaCalificado = $cedulaActual === $cedulaContratador ? $cedulaTrabajador : $cedulaContratador;

        $yaCalifico = Calificacion::where('id_contratacion', $contratacion->id_contratacion)
            ->where('cedula_calificador', $cedulaActual)
            ->exists();

        if ($yaCalifico) {
            return response()->json(['message' => 'Ya calificaste esta contratación.'], 422);
        }

        $calificacion = DB::transaction(function () use ($contratacion, $cedulaActual, $cedulaCalificado, $request) {
            return Calificacion::create([
                'id_contratacion'    => $contratacion->id_contratacion,
                'cedula_calificador' => $cedulaActual,
                'cedula_calificado'  => $cedulaCalificado,
                'puntuacion'         => $request->puntuacion,
                'comentario'         => $request->comentario,
                'fecha'              => now()->toDateString(),
            ]);
        });

        return response()->json([
            'message' => 'Calificación registrada.',
            'data'    => $calificacion,
        ], 201);
    }
}