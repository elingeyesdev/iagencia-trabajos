<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contratacion;
use App\Models\Postulacion;
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
        $contratacion = Contratacion::findOrFail($id);
        $contratacion->delete();

        return response()->json(['message' => 'Contratación eliminada.']);
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
        $request->validate([
            'id_postulacion' => 'required|exists:postulaciones,id_postulacion',
            'fecha_inicio'   => 'required|date',
            'monto_acordado' => 'required|numeric|min:0',
        ]);

        $postulacion = Postulacion::with('publicacion')->findOrFail($request->id_postulacion);

        if ($postulacion->publicacion->cedula != auth()->id()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($postulacion->estado !== 'aceptada') {
            return response()->json(['message' => 'La postulación debe estar aceptada antes de contratar.'], 422);
        }

        if ($postulacion->contratacion) {
            return response()->json(['message' => 'Esta postulación ya tiene una contratación registrada.'], 422);
        }

        $contratacion = DB::transaction(function () use ($request) {
            return Contratacion::create([
                'id_postulacion' => $request->id_postulacion,
                'fecha_inicio'   => $request->fecha_inicio,
                'monto_acordado' => $request->monto_acordado,
                'estado'         => 'activa',
            ]);
        });

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

        $contratacion = Contratacion::with('postulacion.publicacion')->findOrFail($id);

        if ($contratacion->postulacion->publicacion->cedula != auth()->id()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($contratacion->estado !== 'activa') {
            return response()->json(['message' => 'Solo una contratación activa puede cambiar de estado.'], 422);
        }

        DB::transaction(function () use ($contratacion, $request) {
            $contratacion->update([
                'estado'    => $request->estado,
                'fecha_fin' => now()->toDateString(),
            ]);
        });

        return response()->json([
            'message' => 'Contratación actualizada.',
            'data'    => $contratacion->fresh(),
        ]);
    }
}