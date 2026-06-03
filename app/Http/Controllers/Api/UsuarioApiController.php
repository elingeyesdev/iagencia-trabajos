<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioApiController extends Controller
{
    public function index()
    {
        return response()->json(
            User::with('roles')->get()
        );
    }

   public function store(Request $request)
{
    $usuario = User::create([
        'nombre'     => $request->nombre,
        'email'      => $request->email,
        'contrasena' => Hash::make($request->password),
        'cedula'     => $request->cedula,
        'telefono'   => $request->telefono,
        'direccion'  => $request->direccion,
        'ciudad'     => $request->ciudad,
        'fechanac'   => $request->fechanac,
        'estado'     => $request->estado ?? 1,
    ]);

    if ($request->rol) {
        $usuario->assignRole($request->rol);
    }

    return response()->json([
        'message' => 'Usuario creado',
        'data'    => $usuario->load('roles')
    ]);
}

    public function show(string $id)
    {
        return response()->json(
            User::findOrFail($id)
        );
    }

    public function update(Request $request, string $id)
    {
        $usuario = User::findOrFail($id);

        $usuario->update([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'cedula' => $request->cedula,
            'ciudad' => $request->ciudad,
            'estado' => $request->estado
        ]);

        return response()->json([
            'message' => 'Usuario actualizado'
        ]);
    }

    public function destroy(string $id)
    {
        User::destroy($id);

        return response()->json([
            'message' => 'Usuario eliminado'
        ]);
    }
}