<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminApiController extends Controller
{
   public function dashboard()
{
    return response()->json([
        'total_usuarios'      => User::count(),
        'total_publicaciones' => \DB::table('publicaciones')->count(),
        'total_roles'         => Role::count(),
        'usuarios_recientes'  => User::with('roles')->orderBy('fecha_registro', 'desc')->take(5)->get(),
    ]);
}

    public function usuarios()
    {
        return response()->json(User::with('roles')->get());
    }

    public function storeUsuario(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:100',
            'cedula'   => 'required|integer|unique:usuarios,cedula',
            'email'    => 'required|email|unique:usuarios,email',
            'password' => 'required|min:8',
            'ciudad'   => 'nullable|string|max:100',
        ]);

        $user = User::create([
            'nombre'     => $request->nombre,
            'cedula'     => $request->cedula,
            'email'      => $request->email,
            'contrasena' => Hash::make($request->password),
            'ciudad'     => $request->ciudad,
            'estado'     => 1,
        ]);

        if ($request->rol) {
            $user->assignRole($request->rol);
        }

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data'    => $user->load('roles')
        ], 201);
    }

    public function updateUsuario(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'email'  => 'required|email|unique:usuarios,email,' . $id . ',cedula',
            'cedula' => 'required|integer|unique:usuarios,cedula,' . $id . ',cedula',
            'ciudad' => 'nullable|string|max:100',
            'estado' => 'required|in:0,1',
            'rol'    => 'nullable|string|exists:roles,name',
        ]);

        $cedulaAnterior = $user->cedula;
        $cedulaNueva    = (int) $request->cedula;

        \DB::transaction(function () use ($user, $request, $cedulaAnterior, $cedulaNueva) {
            $user->update([
                'nombre' => $request->nombre,
                'email'  => $request->email,
                'cedula' => $cedulaNueva,
                'ciudad' => $request->ciudad,
                'estado' => $request->estado,
            ]);

            // La cédula es la PK y no tiene FK real en BD: si cambió,
            // hay que propagar el nuevo valor a las tablas relacionadas
            // para no dejar registros huérfanos.
            if ($cedulaNueva !== $cedulaAnterior) {
                \DB::table('publicaciones')
                    ->where('cedula', $cedulaAnterior)
                    ->update(['cedula' => $cedulaNueva]);

                \DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('model_id', $cedulaAnterior)
                    ->update(['model_id' => $cedulaNueva]);

                \DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $cedulaAnterior)
                    ->update(['tokenable_id' => $cedulaNueva]);
            }
        });

        if ($request->filled('rol')) {
            $user->syncRoles([$request->rol]);
        } else {
            $user->syncRoles([]);
        }

        return response()->json([
            'message' => 'Usuario actualizado.',
            'data'    => $user->fresh()->load('roles')
        ]);
    }

    public function destroyUsuario($id)
{
    $user = User::findOrFail($id);
    $user->update(['estado' => 0]);

    return response()->json([
        'message' => 'Usuario desactivado correctamente.'
    ]);
}

    public function roles()
{
    $roles = Role::all()->map(function ($role) {
        $role->users_count = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();
        return $role;
    });
    return response()->json($roles);
}

    public function asignarRol(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,cedula',
            'rol'        => 'required|exists:roles,name',
        ]);

        $user = User::findOrFail($request->usuario_id);
        $user->syncRoles([$request->rol]);

        return response()->json([
            'message' => "Rol '{$request->rol}' asignado a {$user->nombre}."
        ]);
    }

    public function quitarRol(Request $request)
    {
        $user = User::findOrFail($request->usuario_id);
        $user->roles()->detach();

        return response()->json([
            'message' => "Rol removido de {$user->nombre}."
        ]);
    }
    public function storeRol(Request $request)
{
    $request->validate(['name' => 'required|string|unique:roles,name|max:50']);
    $rol = Role::create(['name' => strtolower($request->name), 'guard_name' => 'web']);
    return response()->json(['message' => 'Rol creado.', 'data' => $rol], 201);
}

public function updateRol(Request $request, $id)
{
    $rol = Role::findOrFail($id);
    $request->validate(['name' => 'required|string|unique:roles,name,'.$id.'|max:50']);
    $rol->update(['name' => strtolower($request->name)]);
    return response()->json(['message' => 'Rol actualizado.', 'data' => $rol]);
}

public function destroyRol($id)
{
    $rol = Role::findOrFail($id);
    if (in_array($rol->name, ['administrador', 'trabajador', 'contratador'])) {
        return response()->json(['message' => 'No se puede eliminar un rol del sistema.'], 403);
    }
    $rol->delete();
    return response()->json(['message' => 'Rol eliminado.']);
}
}