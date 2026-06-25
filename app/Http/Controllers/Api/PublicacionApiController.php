<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Publicacion;
use App\Models\Categoria;
use Illuminate\Http\Request;

class PublicacionApiController extends Controller
{
    public function index()
    {
        return response()->json(
            Publicacion::with(['categoria', 'usuario'])->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo'        => 'required|string|max:30',
            'titulo'      => 'required|string|max:200',
            'descripcion' => 'required|string',
            'pago'        => 'required|numeric',
            'lugar'       => 'required|string|max:200',
            'id_categoria'=> 'required|exists:categorias,id_categoria',
        ]);

        $publicacion = Publicacion::create([
            'tipo'              => $request->tipo,
            'titulo'            => $request->titulo,
            'descripcion'       => $request->descripcion,
            'pago'              => $request->pago,
            'lugar'             => $request->lugar,
            'fecha_publicacion' => now()->toDateString(),
            'id_usuario'        => auth()->id(),
            'id_categoria'      => $request->id_categoria,
            'estado'            => 1,
        ]);

        return response()->json([
            'message' => 'Publicación creada.',
            'data'    => $publicacion->load('categoria')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $publicacion = Publicacion::findOrFail($id);

        $request->validate([
            'tipo'        => 'required|string|max:30',
            'titulo'      => 'required|string|max:200',
            'descripcion' => 'required|string',
            'pago'        => 'required|numeric',
            'lugar'       => 'required|string|max:200',
            'id_categoria'=> 'required|exists:categorias,id_categoria',
            'estado'      => 'nullable|integer',
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

    public function destroy($id)
    {
        $publicacion = Publicacion::findOrFail($id);
        $publicacion->update(['estado' => 0]); // eliminación lógica

        return response()->json([
            'message' => 'Publicación desactivada.'
        ]);
    }
}