<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 0.2 — Un contratador solo puede modificar o desactivar sus propias publicaciones.
 * La propiedad la determina la columna publicaciones.cedula.
 */
class PublicacionPropiedadTest extends TestCase
{
    use RefreshDatabase;

    private int $siguienteCedula = 2000;
    private Categoria $categoria;
    private User $propietario;
    private User $otroContratador;
    private Publicacion $publicacion;
    private array $original;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->categoria       = Categoria::create(['nombre_categoria' => 'Limpieza', 'estado' => 1]);
        $this->propietario     = $this->crearUsuario('contratador');
        $this->otroContratador = $this->crearUsuario('contratador');

        $this->publicacion = Publicacion::create([
            'tipo'              => 'temporal',
            'titulo'            => 'Título original',
            'descripcion'       => 'Descripción original',
            'pago'              => 150.00,
            'lugar'             => 'Cochabamba',
            'fecha_publicacion' => '2026-11-01',
            'cedula'            => $this->propietario->cedula,
            'id_categoria'      => $this->categoria->id_categoria,
            'estado'            => 1,
        ]);

        $this->original = $this->publicacion->fresh()->getAttributes();
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

    private function payload(array $extra = []): array
    {
        return array_merge([
            'tipo'         => 'permanente',
            'titulo'       => 'Título editado',
            'descripcion'  => 'Descripción editada',
            'pago'         => 999,
            'lugar'        => 'La Paz',
            'id_categoria' => $this->categoria->id_categoria,
        ], $extra);
    }

    private function urlContratador(): string
    {
        return "/api/contratador/publicaciones/{$this->publicacion->id_labor}";
    }

    private function assertPublicacionIntacta(): void
    {
        $this->assertSame($this->original, $this->publicacion->fresh()->getAttributes());
    }

    // ── 1. Propietario ──

    public function test_propietario_puede_modificar_su_publicacion(): void
    {
        Sanctum::actingAs($this->propietario);

        $this->putJson($this->urlContratador(), $this->payload())
            ->assertOk()
            ->assertJsonPath('message', 'Publicación actualizada.')
            ->assertJsonPath('data.titulo', 'Título editado')
            ->assertJsonStructure(['message', 'data' => ['id_labor', 'titulo', 'categoria']]);

        $this->assertDatabaseHas('publicaciones', [
            'id_labor' => $this->publicacion->id_labor,
            'titulo'   => 'Título editado',
            'cedula'   => $this->propietario->cedula,
        ]);
    }

    public function test_propietario_puede_desactivar_su_publicacion(): void
    {
        Sanctum::actingAs($this->propietario);

        $this->deleteJson($this->urlContratador())
            ->assertOk()
            ->assertExactJson(['message' => 'Publicación desactivada exitosamente.']);

        // Desactivación lógica: el registro sigue existiendo.
        $this->assertDatabaseHas('publicaciones', ['id_labor' => $this->publicacion->id_labor, 'estado' => 0]);
    }

    // ── 2–4. Otro contratador ──

    public function test_otro_contratador_recibe_403_al_modificar_y_no_cambia_nada(): void
    {
        Sanctum::actingAs($this->otroContratador);

        $this->putJson($this->urlContratador(), $this->payload())
            ->assertForbidden()
            ->assertExactJson(['message' => 'No autorizado.']);

        $this->assertPublicacionIntacta();
    }

    public function test_otro_contratador_recibe_403_al_desactivar_via_update_con_estado(): void
    {
        Sanctum::actingAs($this->otroContratador);

        $this->putJson($this->urlContratador(), $this->payload(['estado' => 0]))
            ->assertForbidden();

        $this->assertPublicacionIntacta();
    }

    public function test_otro_contratador_recibe_403_al_eliminar_y_no_cambia_nada(): void
    {
        Sanctum::actingAs($this->otroContratador);

        $this->deleteJson($this->urlContratador())
            ->assertForbidden()
            ->assertExactJson(['message' => 'No autorizado.']);

        $this->assertPublicacionIntacta();
    }

    public function test_otro_contratador_recibe_403_aunque_envie_datos_invalidos(): void
    {
        Sanctum::actingAs($this->otroContratador);

        // La autorización se evalúa antes que la validación: no se filtra información de campos.
        $this->putJson($this->urlContratador(), [])->assertForbidden();

        $this->assertPublicacionIntacta();
    }

    public function test_trabajador_no_puede_usar_rutas_de_contratador(): void
    {
        Sanctum::actingAs($this->crearUsuario('trabajador'));

        $this->putJson($this->urlContratador(), $this->payload())->assertForbidden();
        $this->deleteJson($this->urlContratador())->assertForbidden();

        $this->assertPublicacionIntacta();
    }

    // ── 5. No autenticado ──

    public function test_sin_autenticar_recibe_401(): void
    {
        $adminUrl = "/api/admin/publicaciones/{$this->publicacion->id_labor}";

        $this->putJson($this->urlContratador(), $this->payload())->assertUnauthorized();
        $this->deleteJson($this->urlContratador())->assertUnauthorized();
        $this->putJson($adminUrl, $this->payload())->assertUnauthorized();
        $this->deleteJson($adminUrl)->assertUnauthorized();

        $this->assertPublicacionIntacta();
    }

    // ── 6. Administrador ──

    public function test_administrador_conserva_sus_operaciones_sobre_publicaciones_ajenas(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $adminUrl = "/api/admin/publicaciones/{$this->publicacion->id_labor}";

        $this->getJson('/api/admin/publicaciones')->assertOk()->assertJsonCount(1);

        $this->putJson($adminUrl, $this->payload(['titulo' => 'Editado por admin']))
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Editado por admin');

        $this->deleteJson($adminUrl)->assertOk();

        $this->assertDatabaseHas('publicaciones', [
            'id_labor' => $this->publicacion->id_labor,
            'titulo'   => 'Editado por admin',
            'estado'   => 0,
            'cedula'   => $this->propietario->cedula, // la propiedad no cambia
        ]);
    }

    public function test_administrador_puede_crear_publicaciones(): void
    {
        Sanctum::actingAs($this->crearUsuario('administrador'));

        $this->postJson('/api/admin/publicaciones', $this->payload())->assertCreated();
    }

    // ── 7. Compatibilidad de rutas y contratos ──

    public function test_creacion_por_contratador_no_cambia(): void
    {
        Sanctum::actingAs($this->otroContratador);

        $this->postJson('/api/contratador/publicaciones', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Publicación creada exitosamente')
            ->assertJsonPath('data.cedula', $this->otroContratador->cedula);
    }

    public function test_publicacion_inexistente_sigue_devolviendo_404(): void
    {
        Sanctum::actingAs($this->otroContratador);

        $this->putJson('/api/contratador/publicaciones/999999', $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Publicación no encontrada.']);

        $this->deleteJson('/api/contratador/publicaciones/999999')->assertNotFound();
    }

    public function test_rutas_y_metodos_de_publicaciones_se_mantienen(): void
    {
        $esperadas = [
            'GET api/admin/publicaciones'                => 'index',
            'POST api/admin/publicaciones'               => 'store',
            'PUT api/admin/publicaciones/{id}'           => 'update',
            'DELETE api/admin/publicaciones/{id}'        => 'destroy',
            'POST api/contratador/publicaciones'         => 'store',
            'PUT api/contratador/publicaciones/{id}'     => 'update',
            'DELETE api/contratador/publicaciones/{id}'  => 'destroy',
        ];
        // Incremento 4: reporte de publicaciones (solo lectura, ReporteApiController), alta intencional.
        // Se inserta tras las rutas admin para respetar el orden de registro (assertSame compara orden).
        // Incremento 4 (PDF): descarga en PDF del mismo reporte (solo lectura), alta intencional.
        $esperadas = array_slice($esperadas, 0, 4, true)
            + ['GET api/admin/reportes/publicaciones' => 'publicaciones']
            + ['GET api/admin/reportes/publicaciones/pdf' => 'publicacionesPdf']
            + array_slice($esperadas, 4, null, true);

        $registradas = [];
        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            if (str_contains($ruta->uri(), 'publicaciones')) {
                foreach ($ruta->methods() as $m) {
                    if ($m !== 'HEAD') {
                        $registradas["{$m} {$ruta->uri()}"] = $ruta->getActionMethod();
                    }
                }
            }
        }

        $this->assertSame($esperadas, array_intersect_key($registradas, $esperadas));
        $this->assertCount(count($esperadas), $registradas, 'Apareció o desapareció una ruta de publicaciones.');
    }
}
