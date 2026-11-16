<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 0.1 — Solo el rol administrador puede operar sobre /api/usuarios.
 */
class UsuarioApiAutorizacionTest extends TestCase
{
    use RefreshDatabase;

    private int $siguienteCedula = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    private function crearUsuario(?string $rol = null): User
    {
        $cedula = $this->siguienteCedula++;

        $user = User::create([
            'cedula'     => $cedula,
            'nombre'     => "Usuario {$cedula}",
            'email'      => "usuario{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);

        if ($rol) {
            $user->assignRole($rol);
        }

        return $user;
    }

    /** Las 5 operaciones de /api/usuarios con un payload válido. */
    private function operaciones(User $objetivo): array
    {
        $nuevaCedula = $this->siguienteCedula++;

        return [
            'index'   => ['GET',    '/api/usuarios', []],
            'show'    => ['GET',    "/api/usuarios/{$objetivo->cedula}", []],
            'store'   => ['POST',   '/api/usuarios', [
                'cedula'   => $nuevaCedula,
                'nombre'   => 'Nuevo Admin',
                'email'    => "nuevo{$nuevaCedula}@redwork.test",
                'password' => 'Clave-Prueba-123',
                'fechanac' => '1990-01-01',
                'rol'      => 'administrador',
            ]],
            'update'  => ['PUT',    "/api/usuarios/{$objetivo->cedula}", [
                'nombre' => 'Nombre Editado',
                'email'  => $objetivo->email,
                'cedula' => $objetivo->cedula,
                'ciudad' => 'La Paz',
                'estado' => 1,
            ]],
            'destroy' => ['DELETE', "/api/usuarios/{$objetivo->cedula}", []],
        ];
    }

    public function test_administrador_puede_usar_todas_las_operaciones(): void
    {
        $admin = $this->crearUsuario('administrador');

        foreach (['index', 'show', 'store', 'update', 'destroy'] as $op) {
            $objetivo = $this->crearUsuario('trabajador');
            Sanctum::actingAs($admin);

            [$metodo, $uri, $datos] = $this->operaciones($objetivo)[$op];
            $this->json($metodo, $uri, $datos)->assertOk();
        }

        $this->assertDatabaseHas('usuarios', ['nombre' => 'Nuevo Admin']);
    }

    public function test_trabajador_recibe_403_en_todas_las_operaciones(): void
    {
        $this->assertRolBloqueado('trabajador');
    }

    public function test_contratador_recibe_403_en_todas_las_operaciones(): void
    {
        $this->assertRolBloqueado('contratador');
    }

    public function test_usuario_sin_rol_recibe_403(): void
    {
        $this->assertRolBloqueado(null);
    }

    private function assertRolBloqueado(?string $rol): void
    {
        $actor    = $this->crearUsuario($rol);
        $objetivo = $this->crearUsuario('trabajador');
        $usuariosAntes = User::count();

        foreach ($this->operaciones($objetivo) as $op => [$metodo, $uri, $datos]) {
            Sanctum::actingAs($actor);
            $this->json($metodo, $uri, $datos)->assertForbidden();
        }

        // Nada se creó, modificó ni eliminó.
        $this->assertSame($usuariosAntes, User::count());
        $this->assertDatabaseMissing('usuarios', ['nombre' => 'Nuevo Admin']);
        $this->assertDatabaseHas('usuarios', ['cedula' => $objetivo->cedula, 'nombre' => $objetivo->nombre]);
        $this->assertFalse(User::role('administrador')->exists());
    }

    public function test_sin_autenticar_recibe_401_en_todas_las_operaciones(): void
    {
        $objetivo = $this->crearUsuario('trabajador');

        foreach ($this->operaciones($objetivo) as $op => [$metodo, $uri, $datos]) {
            $this->json($metodo, $uri, $datos)->assertUnauthorized();
        }
    }

    /** Ninguna ruta API que gestione usuarios o roles queda sin el rol administrador. */
    public function test_no_existen_rutas_api_de_usuarios_o_roles_sin_proteccion_de_administrador(): void
    {
        $rutasSensibles = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/'))
            ->filter(fn ($r) => preg_match('#(^|/)(usuarios|roles|asignar-rol|quitar-rol)(/|$)#', $r->uri()));

        $this->assertNotEmpty($rutasSensibles);

        foreach ($rutasSensibles as $ruta) {
            $middleware = $ruta->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, "Sin auth: {$ruta->uri()}");
            $this->assertContains('role:administrador', $middleware, "Sin rol admin: {$ruta->uri()}");
        }
    }

    public function test_no_admin_no_puede_crear_administradores_por_rutas_alternativas(): void
    {
        $trabajador = $this->crearUsuario('trabajador');
        Sanctum::actingAs($trabajador);

        $this->postJson('/api/admin/usuarios', [
            'cedula' => 9999, 'nombre' => 'X', 'email' => 'x@redwork.test',
            'password' => 'Clave-Prueba-123', 'rol' => 'administrador',
        ])->assertForbidden();

        $this->postJson('/api/admin/asignar-rol', [
            'usuario_id' => $trabajador->cedula, 'rol' => 'administrador',
        ])->assertForbidden();

        // El perfil propio no permite escalar rol.
        $this->putJson('/api/perfil', [
            'nombre' => 'Yo', 'email' => $trabajador->email, 'rol' => 'administrador',
        ])->assertOk();

        $this->assertFalse($trabajador->fresh()->hasRole('administrador'));
        $this->assertFalse(User::role('administrador')->exists());
    }

    public function test_registro_web_no_permite_elegir_rol_administrador(): void
    {
        $this->post('/register', [
            'cedula' => 7777, 'nombre' => 'Intruso', 'email' => 'intruso@redwork.test',
            'password' => 'Clave-Prueba-123', 'password_confirmation' => 'Clave-Prueba-123',
            'rol' => 'administrador',
        ])->assertSessionHasErrors('rol');

        $this->assertDatabaseMissing('usuarios', ['cedula' => 7777]);
    }

    public function test_endpoints_del_dashboard_admin_siguen_funcionando(): void
    {
        $admin    = $this->crearUsuario('administrador');
        $objetivo = $this->crearUsuario('trabajador');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonStructure(['total_usuarios']);
        $this->getJson('/api/admin/usuarios')->assertOk();
        $this->getJson('/api/admin/roles')->assertOk();
        $this->postJson('/api/admin/asignar-rol', [
            'usuario_id' => $objetivo->cedula, 'rol' => 'contratador',
        ])->assertOk();

        $this->assertTrue($objetivo->fresh()->hasRole('contratador'));
    }

    public function test_rutas_de_otros_roles_no_se_ven_afectadas(): void
    {
        $trabajador = $this->crearUsuario('trabajador');
        Sanctum::actingAs($trabajador);

        $this->getJson('/api/me')->assertOk();
        $this->getJson('/api/categorias')->assertOk();
        $this->getJson('/api/trabajador/postulaciones')->assertOk();
        $this->putJson('/api/perfil', ['nombre' => 'Editado', 'email' => $trabajador->email])->assertOk();

        $contratador = $this->crearUsuario('contratador');
        Sanctum::actingAs($contratador);

        $this->getJson('/api/contratador/postulaciones')->assertOk();
        $this->getJson('/api/contratador/contrataciones')->assertOk();
    }
}
