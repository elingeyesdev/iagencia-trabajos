<?php

namespace Tests\Feature\Api;

use App\Models\RolAsignacionHistorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Historial de asignaciones de roles: migración, registro automático dentro de la transacción
 * del cambio de rol (asignación, cambio, retirada), rollback si falla el historial y endpoint
 * de solo lectura GET /api/admin/asignaciones/historial (búsqueda, filtros, paginación, autorización).
 */
class HistorialAsignacionesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/asignaciones/historial';
    private const MIGRACION = 'database/migrations/2026_11_19_000001_create_rol_asignaciones_historial_table.php';

    private int $siguienteCedula = 7000;

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

    private function historial(array $datos): RolAsignacionHistorial
    {
        return RolAsignacionHistorial::create(array_merge([
            'usuario_cedula' => 1,
            'usuario_nombre' => 'Alguien',
            'rol_anterior'   => null,
            'rol_nuevo'      => 'trabajador',
            'accion'         => RolAsignacionHistorial::ACCION_ASIGNACION,
            'admin_cedula'   => 9,
            'admin_nombre'   => 'Admin X',
            'ip'             => '10.0.0.1',
            'created_at'     => '2026-11-01 10:00:00',
        ], $datos));
    }

    // ── Migración ──

    public function test_migracion_crea_tabla_con_columnas_e_indices_y_down_solo_la_elimina(): void
    {
        $this->assertTrue(Schema::hasTable('rol_asignaciones_historial'));
        $this->assertTrue(Schema::hasColumns('rol_asignaciones_historial', [
            'id', 'usuario_cedula', 'usuario_nombre', 'rol_anterior', 'rol_nuevo', 'accion',
            'admin_cedula', 'admin_nombre', 'ip', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('rol_asignaciones_historial', 'updated_at'));

        $columnas = collect(Schema::getColumns('rol_asignaciones_historial'))->keyBy('name');
        $this->assertSame(
            collect(Schema::getColumns('usuarios'))->firstWhere('name', 'cedula')['type_name'],
            $columnas['usuario_cedula']['type_name'],
            'usuario_cedula debe tener el mismo tipo que usuarios.cedula'
        );
        $this->assertFalse($columnas['usuario_cedula']['nullable']);
        $this->assertTrue($columnas['admin_cedula']['nullable']);
        $this->assertTrue($columnas['rol_anterior']['nullable']);
        $this->assertTrue($columnas['rol_nuevo']['nullable']);
        $this->assertTrue($columnas['ip']['nullable']);

        $indices = collect(Schema::getIndexes('rol_asignaciones_historial'))->mapWithKeys(fn ($i) => [$i['name'] => $i['columns']]);
        $this->assertSame(['usuario_cedula', 'created_at'], $indices['rah_usuario_fecha_index']);
        $this->assertSame(['admin_cedula', 'created_at'], $indices['rah_admin_fecha_index']);
        $this->assertSame(['created_at', 'id'], $indices['rah_fecha_id_index']);
        $this->assertSame(['accion'], $indices['rah_accion_index']);
        $this->assertSame([], Schema::getForeignKeys('rol_asignaciones_historial'), 'sin claves foráneas');
        $this->assertSame(0, RolAsignacionHistorial::count(), 'sin datos de ejemplo');

        $migracion = require base_path(self::MIGRACION);
        $migracion->down();
        $this->assertFalse(Schema::hasTable('rol_asignaciones_historial'));
        foreach (['usuarios', 'roles', 'model_has_roles', 'pagos'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "down() no debe tocar {$tabla}");
        }
        $migracion->up();
        $this->assertTrue(Schema::hasTable('rol_asignaciones_historial'));
    }

    public function test_el_modelo_no_permite_editar_ni_eliminar_registros(): void
    {
        $h = $this->historial([]);
        try {
            $h->update(['rol_nuevo' => 'administrador']);
            $this->fail('debió impedir la edición');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('no se puede modificar', $e->getMessage());
        }
        try {
            $h->delete();
            $this->fail('debió impedir la eliminación');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('no se puede eliminar', $e->getMessage());
        }
        $this->assertSame('trabajador', $h->fresh()->rol_nuevo);
    }

    // ── Registro automático ──

    public function test_asignacion_inicial_registra_asignacion_con_admin_ip_y_fecha(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(14, 30, 5));
        $admin = $this->crearUsuario('administrador', ['nombre' => 'Admin Responsable']);
        $u = $this->crearUsuario(null, ['nombre' => 'Nuevo Sin Rol']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $u->cedula, 'rol' => 'trabajador'])
            ->assertOk()->assertExactJson(['message' => "Rol 'trabajador' asignado a Nuevo Sin Rol."]);

        $h = RolAsignacionHistorial::sole();
        $this->assertSame($u->cedula, $h->usuario_cedula);
        $this->assertSame('Nuevo Sin Rol', $h->usuario_nombre);
        $this->assertNull($h->rol_anterior);
        $this->assertSame('trabajador', $h->rol_nuevo);
        $this->assertSame('asignacion', $h->accion);
        $this->assertSame($admin->cedula, $h->admin_cedula);
        $this->assertSame('Admin Responsable', $h->admin_nombre);
        $this->assertSame('127.0.0.1', $h->ip);
        $this->assertSame('2026-11-19 14:30:05', $h->created_at->format('Y-m-d H:i:s'));

        $fila = $this->getJson(self::URL)->assertOk()->json('data.0');
        $this->assertSame(['cedula' => $u->cedula, 'nombre' => 'Nuevo Sin Rol'], $fila['usuario']);
        $this->assertSame(['cedula' => $admin->cedula, 'nombre' => 'Admin Responsable'], $fila['admin']);
        $this->assertSame('Asignación', $fila['accion_etiqueta']);
        $this->assertSame('19/11/2026 14:30:05', $fila['fecha_texto']);
        $this->assertStringStartsWith('2026-11-19T14:30:05', $fila['fecha']);
        $this->assertSame('127.0.0.1', $fila['ip']);
    }

    public function test_cambio_registra_cambio_con_ambos_roles(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $t = $this->crearUsuario('trabajador');

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $t->cedula, 'rol' => 'contratador'])->assertOk();

        $h = RolAsignacionHistorial::sole();
        $this->assertSame('cambio', $h->accion);
        $this->assertSame('trabajador', $h->rol_anterior);
        $this->assertSame('contratador', $h->rol_nuevo);
    }

    public function test_retirada_registra_retirada_con_rol_nuevo_null(): void
    {
        $admin = $this->crearUsuario('administrador', ['nombre' => 'Quien Quita']);
        Sanctum::actingAs($admin);
        $c = $this->crearUsuario('contratador', ['nombre' => 'Pierde Rol']);

        $this->postJson('/api/admin/quitar-rol', ['usuario_id' => $c->cedula])
            ->assertOk()->assertExactJson(['message' => 'Rol removido de Pierde Rol.']);

        $h = RolAsignacionHistorial::sole();
        $this->assertSame('retirada', $h->accion);
        $this->assertSame('contratador', $h->rol_anterior);
        $this->assertNull($h->rol_nuevo);
        $this->assertSame($admin->cedula, $h->admin_cedula);
        $this->assertSame('Quien Quita', $h->admin_nombre);
        $this->assertSame([], $c->fresh()->getRoleNames()->all());
    }

    public function test_duplicado_y_validaciones_no_registran_historial(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $t = $this->crearUsuario('trabajador');
        $sinRol = $this->crearUsuario();

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $t->cedula, 'rol' => 'trabajador'])->assertStatus(422);
        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $t->cedula, 'rol' => 'superusuario'])->assertStatus(422);
        $this->postJson('/api/admin/quitar-rol', ['usuario_id' => $sinRol->cedula])->assertStatus(422);

        $this->assertSame(0, RolAsignacionHistorial::count());
    }

    public function test_ultimo_admin_no_registra_historial(): void
    {
        $admin = $this->crearUsuario('administrador');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $admin->cedula, 'rol' => 'trabajador'])->assertStatus(422);
        $this->postJson('/api/admin/quitar-rol', ['usuario_id' => $admin->cedula])->assertStatus(422);

        $this->assertSame(0, RolAsignacionHistorial::count());
        $this->assertTrue($admin->fresh()->hasRole('administrador'));
    }

    public function test_fallo_al_insertar_historial_revierte_la_asignacion_y_la_retirada(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $t = $this->crearUsuario('trabajador');
        Event::listen('eloquent.creating: ' . RolAsignacionHistorial::class, function () {
            throw new \RuntimeException('Fallo simulado del historial');
        });

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $t->cedula, 'rol' => 'contratador'])
            ->assertStatus(500)
            ->assertExactJson(['message' => 'No se pudo registrar el cambio de rol. No se realizó ningún cambio; inténtalo de nuevo.']);
        $this->assertSame(['trabajador'], $t->fresh()->getRoleNames()->all(), 'el rol no cambia (rollback)');

        $this->postJson('/api/admin/quitar-rol', ['usuario_id' => $t->cedula])->assertStatus(500);
        $this->assertSame(['trabajador'], $t->fresh()->getRoleNames()->all(), 'el rol no se retira (rollback)');
        $this->assertSame(0, RolAsignacionHistorial::count());
    }

    public function test_si_la_tabla_de_historial_no_existe_el_rol_no_cambia(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $u = $this->crearUsuario();
        Schema::drop('rol_asignaciones_historial');

        $this->postJson('/api/admin/asignar-rol', ['usuario_id' => $u->cedula, 'rol' => 'trabajador'])
            ->assertStatus(500)->assertJsonStructure(['message']);
        $this->assertSame([], $u->fresh()->getRoleNames()->all());
    }

    // ── Endpoint: búsqueda, filtros, orden y paginación ──

    public function test_busqueda_por_nombre_cedula_y_admin_con_comodines_escapados(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->historial(['usuario_cedula' => 1234567, 'usuario_nombre' => 'Lucía Torrez', 'admin_nombre' => 'Admin Uno']);
        $this->historial(['usuario_cedula' => 7654321, 'usuario_nombre' => 'Mario Quispe', 'admin_nombre' => 'Admin Dos']);
        $this->historial(['usuario_cedula' => 5555, 'usuario_nombre' => 'Rosa_Mar', 'admin_nombre' => null, 'admin_cedula' => null]);

        $nombres = fn ($q) => collect($this->getJson(self::URL . '?buscar=' . urlencode($q))->assertOk()->json('data'))->pluck('usuario.nombre')->all();

        $this->assertSame(['Lucía Torrez'], $nombres('Torrez'));
        $this->assertSame(['Mario Quispe'], $nombres('765432'));
        $this->assertSame(['Mario Quispe'], $nombres('Admin Dos'));
        $this->assertSame([], $nombres('%'), '% es literal, no comodín');
        $this->assertSame(['Rosa_Mar'], $nombres('_'), '_ es literal, no comodín');
        $this->assertSame([], $nombres("' OR 1=1 --"));
        $this->assertNull(collect($this->getJson(self::URL . '?buscar=Rosa')->json('data'))->first()['admin']);
    }

    public function test_filtros_accion_y_fechas(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->historial(['usuario_nombre' => 'A', 'accion' => 'asignacion', 'created_at' => '2026-10-01 08:00:00']);
        $this->historial(['usuario_nombre' => 'B', 'accion' => 'cambio', 'rol_anterior' => 'trabajador', 'rol_nuevo' => 'contratador', 'created_at' => '2026-10-15 23:59:59']);
        $this->historial(['usuario_nombre' => 'C', 'accion' => 'retirada', 'rol_anterior' => 'trabajador', 'rol_nuevo' => null, 'created_at' => '2026-10-16 00:00:00']);

        $nombres = fn ($qs) => collect($this->getJson(self::URL . '?' . $qs)->assertOk()->json('data'))->pluck('usuario.nombre')->all();

        $this->assertSame(['B'], $nombres('accion=cambio'));
        $this->assertSame(['C'], $nombres('accion=retirada'));
        $this->assertSame(['C', 'B'], $nombres('desde=2026-10-15'));
        $this->assertSame(['B', 'A'], $nombres('hasta=2026-10-15'), 'hasta incluye todo el día');
        $this->assertSame(['B'], $nombres('desde=2026-10-15&hasta=2026-10-15'));
        $this->assertSame(['C'], $nombres('desde=2026-10-01&accion=retirada'));
        $this->assertSame('Retirada', $this->getJson(self::URL . '?accion=retirada')->json('data.0.accion_etiqueta'));
    }

    public function test_filtros_invalidos_devuelven_422_en_espanol(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));

        $casos = [
            'accion=borrado'                    => ['accion', 'El tipo de acción debe ser asignación, cambio o retirada.'],
            'desde=15/10/2026'                  => ['desde', 'La fecha inicial debe tener el formato AAAA-MM-DD.'],
            'hasta=2026-13-01'                  => ['hasta', 'La fecha final debe tener el formato AAAA-MM-DD.'],
            'desde=2026-10-10&hasta=2026-10-09' => ['hasta', 'La fecha final no puede ser anterior a la fecha inicial.'],
            'per_page=51'                       => ['per_page', 'La cantidad por página no puede superar 50.'],
            'per_page=0'                        => ['per_page', 'La cantidad por página debe ser como mínimo 1.'],
            'page=abc'                          => ['page', 'La página debe ser un número entero.'],
            'buscar=' . str_repeat('x', 101)    => ['buscar', 'La búsqueda no puede superar los 100 caracteres.'],
        ];
        foreach ($casos as $qs => [$campo, $mensaje]) {
            $this->getJson(self::URL . '?' . $qs)
                ->assertStatus(422)
                ->assertJsonPath("errors.{$campo}.0", $mensaje);
        }
    }

    public function test_orden_mas_reciente_primero_estable_por_id_y_paginacion(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $viejo = $this->historial(['usuario_nombre' => 'Viejo', 'created_at' => '2026-01-01 00:00:00']);
        $ids = [];
        for ($i = 1; $i <= 4; $i++) {
            $ids[] = $this->historial(['usuario_nombre' => "Mismo {$i}", 'created_at' => '2026-06-01 12:00:00'])->id;
        }
        $nuevo = $this->historial(['usuario_nombre' => 'Nuevo', 'created_at' => '2026-12-01 00:00:00']);

        $todo = $this->getJson(self::URL . '?per_page=50')->assertOk();
        $this->assertSame(
            array_merge([$nuevo->id], array_reverse($ids), [$viejo->id]),
            array_column($todo->json('data'), 'id')
        );

        $p1 = $this->getJson(self::URL . '?per_page=2&page=1')->assertOk();
        $p3 = $this->getJson(self::URL . '?per_page=2&page=3')->assertOk();
        $p1->assertJsonPath('meta.total', 6)->assertJsonPath('meta.last_page', 3)->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.from', 1)->assertJsonPath('meta.to', 2)->assertJsonPath('meta.current_page', 1);
        $this->assertSame([$nuevo->id, $ids[3]], array_column($p1->json('data'), 'id'));
        $this->assertSame([$ids[0], $viejo->id], array_column($p3->json('data'), 'id'));
        $this->assertSame(10, $this->getJson(self::URL)->json('meta.per_page'));
    }

    public function test_estructura_de_la_respuesta(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->historial([]);

        $this->getJson(self::URL)->assertOk()->assertJsonStructure([
            'data' => [['id', 'usuario' => ['cedula', 'nombre'], 'rol_anterior', 'rol_nuevo', 'accion', 'accion_etiqueta', 'admin' => ['cedula', 'nombre'], 'ip', 'fecha', 'fecha_texto']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ]);
    }

    // ── Autorización y solo lectura ──

    public function test_sin_token_401_y_trabajador_contratador_403(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();

        foreach (['trabajador', 'contratador'] as $rol) {
            Sanctum::actingAs($this->crearUsuario($rol));
            $this->getJson(self::URL)->assertForbidden();
        }
    }

    public function test_no_existen_rutas_de_edicion_ni_eliminacion_del_historial(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'asignaciones/historial'));
        $this->assertCount(1, $rutas);
        foreach ($rutas as $r) {
            $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $r->methods());
        }

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $h = $this->historial([]);
        foreach (['PUT', 'PATCH', 'DELETE', 'POST'] as $m) {
            $this->assertContains($this->json($m, self::URL)->status(), [404, 405]);
            $this->assertContains($this->json($m, self::URL . '/' . $h->id)->status(), [404, 405]);
        }
        $this->assertSame(1, RolAsignacionHistorial::count());
    }
}
