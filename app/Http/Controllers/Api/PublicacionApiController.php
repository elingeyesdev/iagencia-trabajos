<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Publicacion;
use Illuminate\Http\Request;

class PublicacionApiController extends Controller
{
    public function index()
    {
        return response()->json(
            Publicacion::with(['categoria', 'usuario'])->where('estado', 1)->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo'       => 'required|string|max:255',
            'tipo'         => 'required|string',
            'descripcion'  => 'required|string',
            'pago'         => 'required|numeric',
            'lugar'        => 'required|string',
            'id_categoria' => 'required|exists:categorias,id_categoria',
        ]);

        $user = $request->user();

        $publicacion = Publicacion::create([
            'cedula'            => $user->cedula,
            'titulo'            => $request->titulo,
            'tipo'              => $request->tipo,
            'descripcion'       => $request->descripcion,
            'pago'              => $request->pago,
            'lugar'             => $request->lugar,
            'id_categoria'      => $request->id_categoria,
            'fecha_publicacion' => now()->toDateString(),
            'estado'            => 1,
        ]);

        return response()->json([
            'message' => 'Publicación creada exitosamente',
            'data'    => $publicacion
        ], 201);
    }

    public function update(Request $request, $id)
    {
        // Buscar directamente en la columna de la llave primaria id_labor
        $publicacion = Publicacion::where('id_labor', $id)->first();

        if (!$publicacion) {
            return response()->json(['message' => 'Publicación no encontrada.'], 404);
        }

        $request->validate([
            'tipo'         => 'required|string|max:30',
            'titulo'       => 'required|string|max:200',
            'descripcion'  => 'required|string',
            'pago'         => 'required|numeric',
            'lugar'        => 'required|string|max:200',
            'id_categoria' => 'required|exists:categorias,id_categoria',
            'estado'       => 'nullable|integer',
        ]);

        $publicacion->update([
            'tipo'         => $request->tipo,
            'titulo'       => $request->titulo,
            'descripcion'  => $request->descripcion,
            'pago'         => $request->pago,
            'lugar'        => $request->lugar,
            'id_categoria' => $request->id_categoria,
            'estado'       => $request->estado ?? $publicacion->estado,
        ]);

        return response()->json([
            'message' => 'Publicación actualizada.',
            'data'    => $publicacion->load('categoria')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $publicacion = Publicacion::where('id_labor', $id)->first();

        if (!$publicacion) {
            return response()->json(['message' => 'Publicación no encontrada.'], 404);
        }

        $publicacion->update(['estado' => 0]);

        return response()->json([
            'message' => 'Publicación desactivada exitosamente.'
        ]);
    }
}