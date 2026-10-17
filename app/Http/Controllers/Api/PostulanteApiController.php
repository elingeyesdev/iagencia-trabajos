<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Postulante;
use Illuminate\Http\Request;

class PostulanteApiController extends Controller
{
    public function index()
    {
        return response()->json(
            Postulante::with('usuario')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'cedula'         => 'required|integer|exists:usuarios,cedula|unique:postulantes,cedula',
            'habilidades'    => 'required|string',
            'experiencia'    => 'nullable|string',
            'disponibilidad' => 'nullable|string|max:100',
            'tarifa'         => 'nullable|numeric|min:0',
            'estado'         => 'nullable|integer',
        ]);

        $postulante = Postulante::create([
            'cedula'         => $request->cedula,
            'habilidades'    => $request->habilidades,
            'experiencia'    => $request->experiencia,
            'disponibilidad' => $request->disponibilidad,
            'tarifa'         => $request->tarifa,
            'estado'         => $request->estado ?? 1,
        ]);

        return response()->json([
            'message' => 'Postulante registrado.',
            'data'    => $postulante->load('usuario')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $postulante = Postulante::findOrFail($id);

        $request->validate([
            'habilidades'    => 'required|string',
            'experiencia'    => 'nullable|string',
            'disponibilidad' => 'nullable|string|max:100',
            'tarifa'         => 'nullable|numeric|min:0',
            'estado'         => 'nullable|integer',
        ]);

        $postulante->update([
            'habilidades'    => $request->habilidades,
            'experiencia'    => $request->experiencia,
            'disponibilidad' => $request->disponibilidad,
            'tarifa'         => $request->tarifa,
            'estado'         => $request->estado ?? $postulante->estado,
        ]);

        return response()->json([
            'message' => 'Postulante actualizado.',
            'data'    => $postulante->load('usuario')
        ]);
    }

    public function destroy($id)
    {
        $postulante = Postulante::findOrFail($id);
        $postulante->delete();

        return response()->json([
            'message' => 'Postulante eliminado.'
        ]);
    }
}