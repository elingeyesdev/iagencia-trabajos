<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaApiController extends Controller
{
    public function index()
    {
        return response()->json(Categoria::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre_categoria' => 'required|string|max:100',
            'descripcion'      => 'nullable|string',
            'estado'           => 'nullable|integer',
        ]);

        $categoria = Categoria::create([
            'nombre_categoria' => $request->nombre_categoria,
            'descripcion'      => $request->descripcion,
            'estado'           => $request->estado ?? 1,
        ]);

        return response()->json([
            'message' => 'Categoría creada.',
            'data'    => $categoria
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $request->validate([
            'nombre_categoria' => 'required|string|max:100',
            'descripcion'      => 'nullable|string',
            'estado'           => 'nullable|integer',
        ]);

        $categoria->update([
            'nombre_categoria' => $request->nombre_categoria,
            'descripcion'      => $request->descripcion,
            'estado'           => $request->estado ?? $categoria->estado,
        ]);

        return response()->json([
            'message' => 'Categoría actualizada.',
            'data'    => $categoria
        ]);
    }

    public function destroy($id)
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return response()->json([
            'message' => 'Categoría eliminada.'
        ]);
    }
}