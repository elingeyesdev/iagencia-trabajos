<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bloque 6 — KPIs sobre la RUTA REAL GET /api/admin/kpis (routes/api.php),
 * sin rutas temporales. Valores calculados a mano:
 *
 *  Publicaciones: 3 activas (estado 1) + 2 inactivas (estado 0) → publicaciones_activas = 3.
 *  Contrataciones: 2 activas, 1 finalizada, 2 canceladas → contrataciones_activas = 2.
 *  Pagos: confirmados 12.34 + 0.66 + 7.00 = 20.00; pendiente 500.00 y anulados 300.00 / 1.00 excluidos.
 */
class KpiRutaRealTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/kpis';
    private int $cedula = 7300;
    private int $ref = 1;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    private function usuario(?string $rol): User
    {
        $user = User::factory()->create(['cedula' => $this->cedula++]);
        if ($rol) {
            $user->assignRole($rol);
        }

        return $user;
    }

    private function publicacion(User $contratador, int $estado): Publicacion
    {
        return Publicacion::create([
            'tipo' => 'oferta', 'titulo' => 'Labor', 'descripcion' => 'D', 'pago' => '1000.00', 'lugar' => 'L',
            'fecha_publicacion' => '2026-01-10', 'cedula' => $contratador->cedula, 'id_categoria' => 1,
            'estado' => $estado,
        ]);
    }

    private function contratacion(Publicacion $pub, User $trabajador, string $estado): Contratacion
    {
        $pos = Postulacion::create([
            'id_labor' => $pub->id_labor, 'cedula' => $trabajador->cedula,
            'fecha_postulacion' => '2026-01-11', 'estado' => 'aceptada',
        ]);

        return Contratacion::create([
            'id_postulacion' => $pos->id_postulacion, 'fecha_inicio' => '2026-01-15',
            'monto_acordado' => '1000.00', 'estado' => $estado,
        ]);
    }

    private function pago(Contratacion $c, string $monto, string $estado): void
    {
        (new Pago)->forceFill([
            'id_contratacion' => $c->id_contratacion, 'monto' => $monto, 'porcentaje_comision' => '0.00',
            'monto_comision' => '0.00', 'monto_neto' => $monto, 'fecha_pago' => '2026-01-20',
            'metodo' => 'efectivo', 'estado' => $estado, 'referencia_externa' => 'SIM-KPIR-' . $this->ref++,
            'es_simulado' => true, 'proveedor' => 'simulado',
            'cedula_registra' => 1, 'fecha_registro' => now(),
        ])->save();
    }

    public function test_la_ruta_real_existe_con_auth_sanctum_y_rol_administrador(): void
    {
        $ruta = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/admin/kpis' && in_array('GET', $r->methods(), true));

        $this->assertNotNull($ruta, 'No existe GET /api/admin/kpis');
        $this->assertSame('App\Http\Controllers\Api\KpiApiController@index', $ruta->getActionName());
        $middleware = $ruta->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('role:administrador', $middleware);
    }

    public function test_valores_reales_excluyen_pendientes_anulados_inactivas_y_no_activas(): void
    {
        $admin = $this->usuario('administrador');
        $contratador = $this->usuario('contratador');
        $trabajador = $this->usuario('trabajador');

        $pa1 = $this->publicacion($contratador, 1);
        $pa2 = $this->publicacion($contratador, 1);
        $this->publicacion($contratador, 1);
        $pi1 = $this->publicacion($contratador, 0);
        $pi2 = $this->publicacion($contratador, 0);

        $activa1    = $this->contratacion($pa1, $trabajador, 'activa');
        $activa2    = $this->contratacion($pi1, $trabajador, 'activa');   // activa aunque la publicación esté inactiva
        $finalizada = $this->contratacion($pa2, $trabajador, 'finalizada');
        $cancelada1 = $this->contratacion($pi2, $trabajador, 'cancelada');
        $this->contratacion($pa2, $this->usuario('trabajador'), 'cancelada');

        $this->pago($activa1, '12.34', Pago::ESTADO_CONFIRMADO);
        $this->pago($finalizada, '0.66', Pago::ESTADO_CONFIRMADO);
        $this->pago($activa2, '7.00', Pago::ESTADO_CONFIRMADO);
        $this->pago($activa1, '500.00', Pago::ESTADO_PENDIENTE);
        $this->pago($finalizada, '300.00', Pago::ESTADO_ANULADO);
        $this->pago($cancelada1, '1.00', Pago::ESTADO_ANULADO);

        Sanctum::actingAs($admin);

        $r = $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('publicaciones_activas', 3)
            ->assertJsonPath('contrataciones_activas', 2)
            ->assertJsonPath('pagos_confirmados_bs', '20.00')
            ->assertJsonPath('simulado', true);

        $this->assertSame(
            ['publicaciones_activas', 'contrataciones_activas', 'pagos_confirmados_bs', 'simulado', 'generado_en'],
            array_keys($r->json())
        );
    }

    public function test_sin_datos_devuelve_ceros_en_la_ruta_real(): void
    {
        Sanctum::actingAs($this->usuario('administrador'));

        $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('publicaciones_activas', 0)
            ->assertJsonPath('contrataciones_activas', 0)
            ->assertJsonPath('pagos_confirmados_bs', '0.00');
    }

    public function test_invitado_401(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_contratador_trabajador_y_sin_rol_reciben_403(): void
    {
        foreach (['contratador', 'trabajador', null] as $rol) {
            Sanctum::actingAs($this->usuario($rol));
            $this->getJson(self::URL)->assertForbidden();
        }
    }

    public function test_solo_get_no_admite_escritura(): void
    {
        Sanctum::actingAs($this->usuario('administrador'));

        $this->postJson(self::URL)->assertStatus(405);
        $this->putJson(self::URL)->assertStatus(405);
        $this->deleteJson(self::URL)->assertStatus(405);
    }
}
