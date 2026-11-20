<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Módulo «Asignar Rol»: resumen, listado paginado con filtros, capacidades derivadas de las rutas
 * reales y reglas reforzadas de asignar/quitar rol (duplicado, último administrador activo).
 */
class AsignacionRolesTest extends TestCase
{
    use RefreshDatabase;

    private int $siguienteCedula = 5000;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    private function crearUsuario(?string $rol = null, array $datos = []): User
    {
        $cedula = $this->siguienteCedula++;
        $user = User::create(array_merge([
            'cedula'     => $cedula,
            'nombre'     => "Usuario {$cedula}",
            'email'      => "usuario{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ], $datos));
        if ($rol) {
            $user->assignRole($rol);
        }
        return $user;
    }

    private function rolId(string $nombre): int
    {
        return Role::where('name', $nombre)->value('id');
    }

    // ── Resumen ──

    public function test_resumen_devuelve_conteos_exactos(): void
    {
        $admin = $this->crearUsuario('administrador');
        $this->crearUsuario('trabajador');
        $this->crearUsuario('contratador');
        $this->crearUsuario();
        $this->crearUsuario(null, ['estado' => 0]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/asignaciones/resumen')
            ->assertOk()
            ->assertExactJson(['total_usuarios' => 5, 'con_rol' => 3, 'sin_rol' => 2, 'roles_disponibles' => 3]);
    }

    // ── Listado: búsqueda, filtros, paginación ──

    public function test_listado_estructura_sin_datos_sensibles_y_orden_por_nombre(): void
    {
        $admin = $this->crearUsuario('administrador', ['nombre' => 'Zoe Admin']);
        $this->crearUsuario('trabajador', ['nombre' => 'Ana Pérez']);
        Sanctum::actingAs($admin);

        $r = $this->getJson('/api/admin/asignaciones')->assertOk()
            ->assertJsonPath('fecha_asignacion_disponible', false)
            ->assertJsonStructure(['data' => [['cedula', 'nombre', 'email', 'estado', 'roles', 'fecha_asignacion']], 'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'], 'nota']);

        $this->assertSame(['Ana Pérez', 'Zoe Admin'], array_column($r->json('data'), 'nombre'));
        $this->assertSame(['trabajador'], $r->json('data.0.roles'));
        $this->assertNull($r->json('data.0.fecha_asignacion'));
        $this->assertSame(10, $r->json('meta.per_page'));
        $this->assertStringNotContainsString('contrasena', $r->getContent());
        $this->assertStringNotContainsString('Clave-Prueba', $r->getContent());
    }

    public function test_busqueda_por_nombre_email_y_nombre_de_rol(): void
    {
        $admin = $this->crearUsuario('administrador', ['nombre' => 'Admin Principal', 'email' => 'jefe@redwork.test']);
        $this->crearUsuario('trabajador', ['nombre' => 'Lucía Torrez', 'email' => 'lucia@redwork.test']);
        $this->crearUsuario('contratador', ['nombre' => 'Mario Quispe', 'email' => 'mq@empresa.test']);
        Sanctum::actingAs($admin);

        $nombres = fn ($q) => array_column($this->getJson('/api/admin/asignaciones?buscar=' . urlencode($q))->assertOk()->json('data'), 'nombre');

        $this->assertSame(['Lucía Torrez'], $nombres('Torrez'));
        $this->assertSame(['Mario Quispe'], $nombres('empresa.test'));
        $this->assertSame(['Mario Quispe'], $nombres('contratad'));
        $this->assertSame([], $nombres('inexistente-xyz'));
    }

    public function test_filtros_rol_estado_y_con_rol(): void
    {
        $admin = $this->crearUsuario('administrador', ['nombre' => 'A Admin']);
        $this->crearUsuario('trabajador', ['nombre' => 'B Trab activo']);
        $this->crearUsuario('trabajador', ['nombre' => 'C Trab inactivo', 'estado' => 0]);
        $this->crearUsuario(null, ['nombre' => 'D Sin rol']);
        Sanctum::actingAs($admin);

        $nombres = fn ($qs) => array_column($this->getJson('/api/admin/asignaciones?' . $qs)->assertOk()->json('data'), 'nombre');

        $this->assertSame(['B Trab activo', 'C Trab inactivo'], $nombres('rol=trabajador'));
        $this->assertSame(['C Trab inactivo'], $nombres('estado=0'));
        $this->assertSame(['B Trab activo'], $nombres('rol=trabajador&estado=1'));
        $this->assertSame(['D Sin rol'], $nombres('con_rol=no'));
        $this->assertSame(['A Admin', 'B Trab activo', 'C Trab inactivo'], $nombres('con_rol=si'));
    }

    public function test_paginacion_con_filtros(): void
    {
        $admin = $this->crearUsuario('administrador', ['nombre' => 'Zz Admin']);
        for ($i = 1; $i <= 7; $i++) {
            $this->crearUsuario('trabajador', ['nombre' => sprintf('Trabajador %02d', $i)]);
        }
        Sanctum::actingAs($admin);

        $p1 = $this->getJson('/api/admin/asignaciones?rol=trabajador&per_page=3&page=1')->assertOk();
        $p3 = $this->getJson('/api/admin/asignaciones?rol=trabajador&per_page=3&page=3')->assertOk();

        $p1->assertJsonPath('meta.total', 7)->assertJsonPath('meta.last_page', 3)->assertJsonPath('meta.from', 1)->assertJsonPath('meta.to', 3);
        $this->assertSame(['Trabajador 01', 'Trabajador 02', 'Trabajador 03'], array_column($p1->json('data'), 'nombre'));
        $this->assertSame(['Trabajador 07'], array_column($p3->json('data'), 'nombre'));
        $p3->assertJsonPath('meta.current_page', 3);
    }

    public function test_filtros_invalidos_devuelven_422_en_espanol(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));

        $casos = [
            'estado=2'          => ['estado', 'El estado debe ser 0 (inactivo) o 1 (activo).'],
            'con_rol=quizas'    => ['con_rol', 'El filtro «con rol» debe ser «si» o «no».'],
            'per_page=51'       => ['per_page', 'La cantidad por página no puede superar 50.'],
            'per_page=0'        => ['per_page', 'La cantidad por página debe ser como mínimo 1.'],
            'page=abc'          => ['page', 'La página debe ser un número entero.'],
            'rol=superusuario'  => ['rol', 'El rol seleccionado no existe.'],
            'buscar=' . str_repeat('x', 101) => ['buscar', 'La búsqueda no puede superar los 100 caracteres.'],
        ];
        foreach ($casos as $qs => [$campo, $mensaje]) {
            $this->getJson('/api/admin/asignaciones?' . $qs)
                ->assertStatus(422)
                ->assertJsonPath("errors.{$campo}.0", $mensaje);
        }
    }

    // ── Capacidades derivadas de las rutas reales ──

    public function test_capacidades_del_administrador_derivan_de_sus_rutas(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));

        $r = $this->getJson('/api/admin/roles/' . $this->rolId('administrador') . '/capacidades')->assertOk()
            ->assertJsonPath('rol.name', 'administrador')
            ->assertJsonPath('rol.users_count', 1)
            ->assertJsonPath('descripcion', null)
            ->assertJsonPath('permisos_spatie', []);

        $porModulo = collect($r->json('capacidades'))->keyBy('modulo');
        $this->assertTrue($porModulo->has('admin/pagos'));
        $this->assertSame('Pagos simulados', $porModulo['admin/pagos']['etiqueta']);
        $this->assertContains('PUT', $porModulo['admin/pagos']['metodos']);
        $this->assertNotContains('HEAD', $porModulo['admin/pagos']['metodos']);
        $this->assertSame('Usuarios', $porModulo['admin/usuarios']['etiqueta']);
        $this->assertTrue($porModulo->has('admin/asignaciones'));
        foreach ($porModulo->keys() as $modulo) {
            $this->assertDoesNotMatchRegularExpression('#^(contratador|trabajador)/#', $modulo);
        }
        $this->assertStringContainsString('autoriza por rol', $r->json('nota_limitacion'));
    }

    public function test_capacidades_del_trabajador_y_contratador_solo_incluyen_sus_rutas(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));

        $trab = collect($this->getJson('/api/admin/roles/' . $this->rolId('trabajador') . '/capacidades')->assertOk()->json('capacidades'));
        $this->assertContains('trabajador/pagos', $trab->pluck('modulo')->all());
        $this->assertTrue($trab->every(fn ($c) => str_starts_with($c['modulo'], 'trabajador/')), 'solo rutas del trabajador');

        $contr = collect($this->getJson('/api/admin/roles/' . $this->rolId('contratador') . '/capacidades')->assertOk()->json('capacidades'));
        $this->assertSame('Publicaciones propias', $contr->firstWhere('modulo', 'contratador/publicaciones')['etiqueta']);
        $this->assertTrue($contr->every(fn ($c) => str_starts_with($c['modulo'], 'contratador/')), 'solo rutas del contratador');
    }

    public function test_capacidades_de_rol_sin_rutas_esta_vacio_y_rol_inexistente_404(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $rol = Role::create(['name' => 'auditor', 'guard_name' => 'web']);

        $this->getJson("/api/admin/roles/{$rol->id}/capacidades")->assertOk()->assertJsonPath('capacidades', []);
        $this->getJson('/api/admin/roles/99999/capacidades')->assertNotFound()->assertJsonPath('message', 'El rol solicitado no existe.');
    }

    // ── Asignar rol reforzado ──

    public function test_asignar_mismo_rol_devuelve_422(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $t = $this->crearUsuario('trabajador');

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $t->cedula, 'rol' => 'trabajador'])
            ->assertStatus(422)->assertJsonPath('message', 'El usuario ya tiene el rol trabajador.');
    }

    public function test_asignar_con_usuario_o_rol_inexistente_devuelve_422(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $t = $this->crearUsuario('trabajador');

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => 424242, 'rol' => 'trabajador'])
            ->assertStatus(422)->assertJsonPath('errors.usuario_id.0', 'El usuario seleccionado no existe.');
        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $t->cedula, 'rol' => 'superusuario'])
            ->assertStatus(422)->assertJsonPath('errors.rol.0', 'El rol seleccionado no existe.');
        $this->postJson('/api/admin/asignar-rol', [])
            ->assertStatus(422)->assertJsonValidationErrors(['usuario_id', 'rol']);
    }

    public function test_asignar_exito_mantiene_respuesta_y_un_solo_rol(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $t = $this->crearUsuario('trabajador', ['nombre' => 'Pedro']);

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $t->cedula, 'rol' => 'contratador'])
            ->assertOk()->assertExactJson(['message' => "Rol 'contratador' asignado a Pedro."]);
        $this->assertSame(['contratador'], $t->fresh()->getRoleNames()->all());
    }

    public function test_ultimo_admin_activo_no_puede_perder_el_rol(): void
    {
        $admin = $this->crearUsuario('administrador', ['nombre' => 'Único Admin']);
        $this->crearUsuario('administrador', ['estado' => 0]); // inactivo: no cuenta
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $admin->cedula, 'rol' => 'trabajador'])
            ->assertStatus(422)->assertJsonPath('message', 'No se puede quitar el rol administrador a Único Admin: es el último administrador activo de RedWork.');
        $this->postJson('/api/admin/quitar-rol', ['usuario_id' => $admin->cedula])
            ->assertStatus(422)->assertJsonPath('errors.usuario_id.0', 'No se puede quitar el rol administrador a Único Admin: es el último administrador activo de RedWork.');

        $this->assertTrue($admin->fresh()->hasRole('administrador'));
    }

    public function test_con_dos_admins_activos_si_se_puede_cambiar_o_quitar(): void
    {
        $a1 = $this->crearUsuario('administrador');
        $a2 = $this->crearUsuario('administrador', ['nombre' => 'Segundo']);
        Sanctum::actingAs($a1);

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $a2->cedula, 'rol' => 'contratador'])->assertOk();
        $this->assertSame(['contratador'], $a2->fresh()->getRoleNames()->all());

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $a2->cedula, 'rol' => 'administrador'])->assertOk();
        $this->postJson('/api/admin/quitar-rol', ['usuario_id' => $a2->cedula])
            ->assertOk()->assertExactJson(['message' => 'Rol removido de Segundo.']);
        $this->assertSame([], $a2->fresh()->getRoleNames()->all());
    }

    // ── Quitar rol reforzado ──

    public function test_quitar_valida_usuario_y_rechaza_sin_rol(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $sinRol = $this->crearUsuario(null, ['nombre' => 'Sin Rol']);

        $this->postJson('/api/admin/quitar-rol', [])->assertStatus(422)->assertJsonPath('errors.usuario_id.0', 'Selecciona un usuario.');
        $this->postJson('/api/admin/quitar-rol', ['usuario_id' => 424242])->assertStatus(422)->assertJsonPath('errors.usuario_id.0', 'El usuario seleccionado no existe.');
        $this->postJson('/api/admin/quitar-rol', ['usuario_id' => $sinRol->cedula])
            ->assertStatus(422)->assertJsonPath('message', 'Sin Rol no tiene ningún rol asignado.');
    }

    // ── Autorización ──

    private function endpoints(int $cedula, int $rolId): array
    {
        return [
            ['GET',  '/api/admin/asignaciones/resumen', []],
            ['GET',  '/api/admin/asignaciones', []],
            ['GET',  "/api/admin/roles/{$rolId}/capacidades", []],
            ['POST', '/api/admin/asignar-rol', ['usuario_id' => $cedula, 'rol' => 'administrador']],
            ['POST', '/api/admin/quitar-rol', ['usuario_id' => $cedula]],
        ];
    }

    public function test_sin_token_401_en_todos_los_endpoints(): void
    {
        $u = $this->crearUsuario('trabajador');
        foreach ($this->endpoints($u->cedula, $this->rolId('trabajador')) as [$m, $uri, $d]) {
            $this->json($m, $uri, $d)->assertUnauthorized();
        }
    }

    public function test_contratador_y_trabajador_reciben_403(): void
    {
        foreach (['contratador', 'trabajador'] as $rol) {
            $u = $this->crearUsuario($rol);
            Sanctum::actingAs($u);
            foreach ($this->endpoints($u->cedula, $this->rolId('administrador')) as [$m, $uri, $d]) {
                $this->json($m, $uri, $d)->assertForbidden();
            }
            $this->assertSame([$rol], $u->fresh()->getRoleNames()->all());
        }
    }
}
