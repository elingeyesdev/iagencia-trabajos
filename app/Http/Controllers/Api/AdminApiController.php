<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RolAsignacionHistorial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
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
        $validated = $request->validate([
            'nombre'   => 'required|string|max:100',
            'cedula'   => 'required|integer|unique:usuarios,cedula',
            'email'    => 'required|email|unique:usuarios,email',
            'password' => ['required', Password::defaults()],
            'ciudad'   => 'nullable|string|max:100',
            'fechanac' => User::reglasFechaNacimiento(),
        ], array_merge(
            User::mensajesContrasena(),
            User::mensajesFechaNacimiento('fechanac', User::MENSAJE_EDAD_ADMIN)
        ));

        $user = User::create([
            'nombre'     => $request->nombre,
            'cedula'     => $request->cedula,
            'email'      => $request->email,
            'contrasena' => Hash::make($request->password),
            'ciudad'     => $request->ciudad,
            'fechanac'   => $validated['fechanac'],
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
        $user = User::with('roles:id,name')->findOrFail($id);

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

        // Auditoría de roles: solo hay movimiento si el conjunto de roles cambia (un rol vacío = retirada).
        $rolesAntes   = $user->roles->pluck('name')->sort()->values()->all();
        $rolesDespues = $request->filled('rol') ? [$request->rol] : [];
        $cambiaRol    = $rolesAntes !== $rolesDespues;

        // Misma protección que Asignar Rol: el último administrador activo no puede perder ese rol.
        if ($cambiaRol && !in_array('administrador', $rolesDespues, true) && $this->esUltimoAdministradorActivo($user)) {
            return $this->errorAsignacion('rol', $this->mensajeUltimoAdmin($user));
        }

        \DB::transaction(function () use ($user, $request, $cedulaAnterior, $cedulaNueva, $cambiaRol, $rolesAntes, $rolesDespues) {
            $user->update([
                'nombre' => $request->nombre,
                'email'  => $request->email,
                'cedula' => $cedulaNueva,
                'ciudad' => $request->ciudad,
                'estado' => $request->estado,
            ]);

            
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

            // Cambio de rol + historial dentro de la MISMA transacción que los datos: todo o nada.
            // Si el rol no cambia no se toca model_has_roles ni se registra nada.
            if ($cambiaRol) {
                $user->unsetRelation('roles');
                $user->syncRoles($rolesDespues);

                $anterior = $rolesAntes ? implode(', ', $rolesAntes) : null;
                $nuevo    = $rolesDespues[0] ?? null;
                $accion   = $nuevo === null
                    ? RolAsignacionHistorial::ACCION_RETIRADA
                    : ($anterior === null ? RolAsignacionHistorial::ACCION_ASIGNACION : RolAsignacionHistorial::ACCION_CAMBIO);

                $this->registrarCambioRol($request, $user, $accion, $anterior, $nuevo);
            }
        });

        $user->unsetRelation('roles');
        $user->forgetCachedPermissions();

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
        ], self::MENSAJES_ASIGNACION);

        $user   = User::with('roles:id,name')->findOrFail($request->usuario_id);
        $actual = $user->roles->pluck('name')->all();

        // Un solo rol por usuario: si ya tiene exactamente ese rol no hay nada que cambiar.
        if ($actual === [$request->rol]) {
            return $this->errorAsignacion('rol', "El usuario ya tiene el rol {$request->rol}.");
        }

        if ($request->rol !== 'administrador' && $this->esUltimoAdministradorActivo($user)) {
            return $this->errorAsignacion('usuario_id', $this->mensajeUltimoAdmin($user));
        }

        // Cambio de rol + historial en la MISMA transacción: ambos o ninguno.
        $anterior = $actual ? implode(', ', $actual) : null;
        $accion   = $anterior === null ? RolAsignacionHistorial::ACCION_ASIGNACION : RolAsignacionHistorial::ACCION_CAMBIO;
        $fallo = $this->cambiarRolConHistorial($user, function () use ($user, $request, $accion, $anterior) {
            $user->syncRoles([$request->rol]);
            $this->registrarCambioRol($request, $user, $accion, $anterior, $request->rol);
        });
        if ($fallo) {
            return $fallo;
        }

        return response()->json([
            'message' => "Rol '{$request->rol}' asignado a {$user->nombre}."
        ]);
    }

    public function quitarRol(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,cedula',
        ], self::MENSAJES_ASIGNACION);

        $user   = User::with('roles:id,name')->findOrFail($request->usuario_id);
        $actual = $user->roles->pluck('name')->all();

        if (empty($actual)) {
            return $this->errorAsignacion('usuario_id', "{$user->nombre} no tiene ningún rol asignado.");
        }

        if ($this->esUltimoAdministradorActivo($user)) {
            return $this->errorAsignacion('usuario_id', $this->mensajeUltimoAdmin($user));
        }

        $fallo = $this->cambiarRolConHistorial($user, function () use ($user, $request, $actual) {
            $user->roles()->detach();
            $this->registrarCambioRol($request, $user, RolAsignacionHistorial::ACCION_RETIRADA, implode(', ', $actual), null);
        });
        if ($fallo) {
            return $fallo;
        }

        return response()->json([
            'message' => "Rol removido de {$user->nombre}."
        ]);
    }

    // ── Asignación de roles: resumen, listado paginado y capacidades derivadas de las rutas ──

    /** Mensajes en español para la validación del módulo Asignar Rol (la app no tiene archivos lang/es). */
    private const MENSAJES_ASIGNACION = [
        'usuario_id.required' => 'Selecciona un usuario.',
        'usuario_id.exists'   => 'El usuario seleccionado no existe.',
        'rol.required'        => 'Selecciona un rol.',
        'rol.exists'          => 'El rol seleccionado no existe.',
        'rol.string'          => 'El rol debe ser un texto.',
        'buscar.string'       => 'La búsqueda debe ser un texto.',
        'buscar.max'          => 'La búsqueda no puede superar los 100 caracteres.',
        'estado.in'           => 'El estado debe ser 0 (inactivo) o 1 (activo).',
        'con_rol.in'          => 'El filtro «con rol» debe ser «si» o «no».',
        'page.integer'        => 'La página debe ser un número entero.',
        'page.min'            => 'La página debe ser como mínimo 1.',
        'per_page.integer'    => 'La cantidad por página debe ser un número entero.',
        'per_page.min'        => 'La cantidad por página debe ser como mínimo 1.',
        'per_page.max'        => 'La cantidad por página no puede superar 50.',
    ];

    /**
     * Etiquetas e iconos SOLO de presentación para los grupos de rutas (clave = segmentos de la URI tras api/).
     * Las capacidades se derivan de las rutas registradas; un grupo sin etiqueta se muestra con su nombre.
     */
    private const ETIQUETAS_MODULO = [
        'usuarios'                     => ['Usuarios (API general)', '👥'],
        'admin/dashboard'              => ['Panel de administración', '🏠'],
        'admin/usuarios'               => ['Usuarios', '👥'],
        'admin/roles'                  => ['Roles', '🛡️'],
        'admin/asignar-rol'            => ['Asignar rol', '🔗'],
        'admin/quitar-rol'             => ['Quitar rol', '🔗'],
        'admin/asignaciones'           => ['Asignaciones de roles', '🔗'],
        'admin/categorias'             => ['Categorías', '🏢'],
        'admin/publicaciones'          => ['Publicaciones', '💼'],
        'admin/postulantes'            => ['Postulantes', '🧰'],
        'admin/postulaciones'          => ['Postulaciones', '📝'],
        'admin/contrataciones'         => ['Contrataciones', '🤝'],
        'admin/calificaciones'         => ['Calificaciones', '⭐'],
        'admin/kpis'                   => ['Indicadores (KPIs)', '📈'],
        'admin/reportes'               => ['Reportes', '🖨️'],
        'admin/pagos'                  => ['Pagos simulados', '💳'],
        'contratador/publicaciones'    => ['Publicaciones propias', '💼'],
        'contratador/postulaciones'    => ['Postulaciones recibidas', '📝'],
        'contratador/contrataciones'   => ['Contrataciones y pagos simulados', '🤝'],
        'contratador/kpis'             => ['Indicadores (KPIs)', '📈'],
        'contratador/calificaciones'   => ['Calificaciones', '⭐'],
        'trabajador/dashboard'         => ['Panel del trabajador', '🏠'],
        'trabajador/postulaciones'     => ['Mis postulaciones', '📝'],
        'trabajador/perfil-postulante' => ['Perfil de postulante', '🧰'],
        'trabajador/pagos'             => ['Mis pagos simulados', '💳'],
        'trabajador/kpis'              => ['Indicadores (KPIs)', '📈'],
        'trabajador/calificaciones'    => ['Calificaciones', '⭐'],
    ];

    /** Prefijos de URI propios de un rol: el módulo se agrupa por prefijo + siguiente segmento. */
    private const PREFIJOS_ROL = ['admin', 'contratador', 'trabajador'];

    public function resumenAsignaciones()
    {
        $total  = User::count();
        $conRol = User::has('roles')->count();

        return response()->json([
            'total_usuarios'    => $total,
            'con_rol'           => $conRol,
            'sin_rol'           => $total - $conRol,
            'roles_disponibles' => Role::count(),
        ]);
    }

    public function asignaciones(Request $request)
    {
        $filtros = $request->validate([
            'buscar'   => 'nullable|string|max:100',
            'rol'      => 'nullable|string|exists:roles,name',
            'estado'   => 'nullable|in:0,1',
            'con_rol'  => 'nullable|in:si,no',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ], self::MENSAJES_ASIGNACION);

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $estado = $filtros['estado'] ?? null;

        $query = User::query()
            ->select('cedula', 'nombre', 'email', 'estado')
            ->with(['roles' => fn ($q) => $q->select('roles.id', 'roles.name')])
            ->when($buscar !== '', function ($q) use ($buscar) {
                $like = '%' . $buscar . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('nombre', 'like', $like)
                      ->orWhere('email', 'like', $like)
                      ->orWhereHas('roles', fn ($r) => $r->where('name', 'like', $like));
                });
            })
            ->when(!empty($filtros['rol']), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $filtros['rol'])))
            ->when($estado !== null && $estado !== '', fn ($q) => $q->where('estado', (int) $estado))
            ->when(($filtros['con_rol'] ?? null) === 'si', fn ($q) => $q->has('roles'))
            ->when(($filtros['con_rol'] ?? null) === 'no', fn ($q) => $q->doesntHave('roles'))
            ->orderBy('nombre')
            ->orderBy('cedula');

        $pagina = $query->paginate((int) ($filtros['per_page'] ?? 10));

        return response()->json([
            'data' => collect($pagina->items())->map(fn (User $u) => [
                'cedula'           => $u->cedula,
                'nombre'           => $u->nombre,
                'email'            => $u->email,
                'estado'           => (int) $u->estado,
                'roles'            => $u->roles->pluck('name')->values(),
                'fecha_asignacion' => null,
            ])->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page'    => $pagina->lastPage(),
                'per_page'     => $pagina->perPage(),
                'total'        => $pagina->total(),
                'from'         => $pagina->firstItem(),
                'to'           => $pagina->lastItem(),
            ],
            'fecha_asignacion_disponible' => false,
            'nota' => 'La tabla model_has_roles no registra la fecha de asignación; los cambios de rol se consultan en el historial de asignaciones.',
        ]);
    }

    public function capacidadesRol($id)
    {
        $rol = Role::find($id);
        if (!$rol) {
            return response()->json(['message' => 'El rol solicitado no existe.'], 404);
        }

        $modulos = [];
        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            $uri = $ruta->uri();
            if (!str_starts_with($uri, 'api/') || !in_array($rol->name, $this->rolesDeRuta($ruta), true)) {
                continue;
            }
            $segmentos = explode('/', substr($uri, 4));
            $clave = in_array($segmentos[0], self::PREFIJOS_ROL, true) && isset($segmentos[1])
                ? $segmentos[0] . '/' . $segmentos[1]
                : $segmentos[0];

            $modulos[$clave]['rutas']   = ($modulos[$clave]['rutas'] ?? 0) + 1;
            $modulos[$clave]['metodos'] = array_merge($modulos[$clave]['metodos'] ?? [], array_diff($ruta->methods(), ['HEAD']));
        }

        $capacidades = collect($modulos)->map(function ($m, $clave) {
            [$etiqueta, $icono] = self::ETIQUETAS_MODULO[$clave] ?? [$clave, '🔹'];
            $metodos = array_values(array_unique($m['metodos']));
            sort($metodos);
            return ['modulo' => $clave, 'etiqueta' => $etiqueta, 'icono' => $icono, 'rutas' => $m['rutas'], 'metodos' => $metodos];
        })->sortBy('etiqueta', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return response()->json([
            'rol'             => ['id' => $rol->id, 'name' => $rol->name, 'users_count' => \DB::table('model_has_roles')->where('role_id', $rol->id)->count()],
            'descripcion'     => null,
            'permisos_spatie' => $rol->getAllPermissions()->pluck('name')->values(),
            'capacidades'     => $capacidades,
            'nota_limitacion' => 'RedWork autoriza por rol (middleware «role:» en las rutas), no por permisos individuales. '
                . 'Estas capacidades se derivan de las rutas de la API protegidas con este rol.',
        ]);
    }

    /** Roles exigidos por el middleware «role:» de una ruta (admite «role:a|b» y guard «role:a,web»). */
    private function rolesDeRuta($ruta): array
    {
        $roles = [];
        foreach ($ruta->gatherMiddleware() as $mw) {
            if (is_string($mw) && str_starts_with($mw, 'role:')) {
                $lista = explode(',', substr($mw, 5))[0];
                $roles = array_merge($roles, array_map('trim', explode('|', $lista)));
            }
        }
        return $roles;
    }

    /** true si el usuario es administrador ACTIVO (estado = 1) y no hay otro administrador activo. */
    private function esUltimoAdministradorActivo(User $user): bool
    {
        if ((int) $user->estado !== 1 || !$user->roles->contains('name', 'administrador')) {
            return false;
        }
        return User::role('administrador')->where('estado', 1)->count() <= 1;
    }

    private function mensajeUltimoAdmin(User $user): string
    {
        return "No se puede quitar el rol administrador a {$user->nombre}: es el último administrador activo de RedWork.";
    }

    private function errorAsignacion(string $campo, string $mensaje)
    {
        return response()->json(['message' => $mensaje, 'errors' => [$campo => [$mensaje]]], 422);
    }

    /**
     * Ejecuta el cambio de rol y su registro de historial en una sola DB::transaction.
     * Si algo falla (p. ej. la inserción del historial) se revierte todo y se devuelve un 500 controlado.
     * La caché de Spatie y la relación en memoria se limpian DESPUÉS del commit o del rollback.
     */
    private function cambiarRolConHistorial(User $user, \Closure $operacion)
    {
        try {
            DB::transaction($operacion);
        } catch (\Throwable $e) {
            Log::error('RedWork: no se pudo cambiar el rol (se revirtió la operación)', [
                'usuario_cedula' => $user->cedula,
                'error'          => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'No se pudo registrar el cambio de rol. No se realizó ningún cambio; inténtalo de nuevo.',
            ], 500);
        } finally {
            $user->unsetRelation('roles');
            $user->forgetCachedPermissions();
        }
        return null;
    }

    /** Inserta el historial (tabla rol_asignaciones_historial) y deja una traza en el log (sin datos sensibles). */
    private function registrarCambioRol(Request $request, User $user, string $accion, ?string $anterior, ?string $nuevo): void
    {
        $admin = $request->user();

        RolAsignacionHistorial::create([
            'usuario_cedula' => $user->cedula,
            'usuario_nombre' => (string) $user->nombre,
            'rol_anterior'   => $anterior,
            'rol_nuevo'      => $nuevo,
            'accion'         => $accion,
            'admin_cedula'   => $admin?->cedula,
            'admin_nombre'   => $admin?->nombre,
            'ip'             => $request->ip(),
            'created_at'     => now(),
        ]);

        Log::info("RedWork: rol {$accion}", [
            'admin_cedula'   => $admin?->cedula,
            'usuario_cedula' => $user->cedula,
            'rol_anterior'   => $anterior,
            'rol_nuevo'      => $nuevo,
        ]);
    }

    /** Mensajes en español para los filtros del historial (se combinan con MENSAJES_ASIGNACION). */
    private const MENSAJES_HISTORIAL = [
        'accion.in'                 => 'El tipo de acción debe ser asignación, cambio o retirada.',
        'desde.date_format'         => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
        'hasta.date_format'         => 'La fecha final debe tener el formato AAAA-MM-DD.',
        'hasta.after_or_equal'      => 'La fecha final no puede ser anterior a la fecha inicial.',
    ];

    /** Historial de asignaciones de roles (solo lectura): búsqueda, filtros y paginación en servidor. */
    public function historialAsignaciones(Request $request)
    {
        $filtros = $request->validate([
            'buscar'   => 'nullable|string|max:100',
            'accion'   => 'nullable|in:' . implode(',', RolAsignacionHistorial::ACCIONES),
            'desde'    => 'nullable|date_format:Y-m-d',
            'hasta'    => 'nullable|date_format:Y-m-d|after_or_equal:desde',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ], array_merge(self::MENSAJES_ASIGNACION, self::MENSAJES_HISTORIAL));

        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $query = RolAsignacionHistorial::query()
            ->when($buscar !== '', function ($q) use ($buscar) {
                // LIKE parametrizado con «!» como carácter de escape (portable MySQL/SQLite): %, _ y ! son literales.
                $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar) . '%';
                $q->where(function ($w) use ($like) {
                    $w->whereRaw("usuario_nombre LIKE ? ESCAPE '!'", [$like])
                      ->orWhereRaw("CAST(usuario_cedula AS CHAR) LIKE ? ESCAPE '!'", [$like])
                      ->orWhereRaw("admin_nombre LIKE ? ESCAPE '!'", [$like]);
                });
            })
            ->when(!empty($filtros['accion']), fn ($q) => $q->where('accion', $filtros['accion']))
            ->when(!empty($filtros['desde']), fn ($q) => $q->where('created_at', '>=', $filtros['desde'] . ' 00:00:00'))
            ->when(!empty($filtros['hasta']), fn ($q) => $q->where(
                'created_at', '<', \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $filtros['hasta'])->addDay()->format('Y-m-d') . ' 00:00:00'
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $pagina = $query->paginate((int) ($filtros['per_page'] ?? 10));

        return response()->json([
            'data' => collect($pagina->items())->map(fn (RolAsignacionHistorial $h) => [
                'id'              => $h->id,
                'usuario'         => ['cedula' => $h->usuario_cedula, 'nombre' => $h->usuario_nombre],
                'rol_anterior'    => $h->rol_anterior,
                'rol_nuevo'       => $h->rol_nuevo,
                'accion'          => $h->accion,
                'accion_etiqueta' => $h->etiquetaAccion(),
                'admin'           => $h->admin_cedula === null && $h->admin_nombre === null
                    ? null
                    : ['cedula' => $h->admin_cedula, 'nombre' => $h->admin_nombre],
                'ip'              => $h->ip,
                'fecha'           => $h->created_at?->toIso8601String(),
                'fecha_texto'     => $h->created_at?->format('d/m/Y H:i:s'),
            ])->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page'    => $pagina->lastPage(),
                'per_page'     => $pagina->perPage(),
                'total'        => $pagina->total(),
                'from'         => $pagina->firstItem(),
                'to'           => $pagina->lastItem(),
            ],
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
