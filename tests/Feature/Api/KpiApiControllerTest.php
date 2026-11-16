<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\KpiApiController;
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
 * Bloque 5 — KPIs básicos (KpiApiController@index).
 *
 * La ruta definitiva (/api/admin/kpis) la integra el coordinador en routes/api.php.
 * Para no depender de ella, la prueba registra una ruta temporal equivalente
 * (auth:sanctum + role:administrador) en /api/_test/kpis.
 */
class KpiApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/_test/kpis';
    private int $cedula = 7100;
    private int $ref = 1;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        Route::middleware(['api', 'auth:sanctum', 'role:administrador'])
            ->get(self::URL, [KpiApiController::class, 'index']);
    }

    private function usuario(string $rol): User
    {
        $user = User::factory()->create(['cedula' => $this->cedula++]);
        $user->assignRole($rol);

        return $user;
    }

    private function contratacion(User $contratador, User $trabajador, string $estado, int $estadoPublicacion = 1): Contratacion
    {
        $pub = Publicacion::create([
            'tipo' => 'oferta', 'titulo' => 'T', 'descripcion' => 'D', 'pago' => '500.00', 'lugar' => 'L',
            'fecha_publicacion' => '2026-01-10', 'cedula' => $contratador->cedula, 'id_categoria' => 1,
            'estado' => $estadoPublicacion,
        ]);
        $pos = Postulacion::create([
            'id_labor' => $pub->id_labor, 'cedula' => $trabajador->cedula,
            'fecha_postulacion' => '2026-01-11', 'estado' => 'aceptada',
        ]);

        return Contratacion::create([
            'id_postulacion' => $pos->id_postulacion, 'fecha_inicio' => '2026-01-15',
            'monto_acordado' => '500.00', 'estado' => $estado,
        ]);
    }

    private function pago(Contratacion $c, string $monto, string $estado): void
    {
        (new Pago)->forceFill([
            'id_contratacion' => $c->id_contratacion, 'monto' => $monto, 'porcentaje_comision' => '0.00',
            'monto_comision' => '0.00', 'monto_neto' => $monto, 'fecha_pago' => '2026-01-20',
            'metodo' => 'efectivo', 'estado' => $estado, 'referencia_externa' => 'SIM-KPI-' . $this->ref++,
            'cedula_registra' => 1, 'fecha_registro' => now(),
        ])->save();
    }

    public function test_calcula_kpis_excluyendo_inactivos_pendientes_y_anulados(): void
    {
        $admin = $this->usuario('administrador');
        $contratador = $this->usuario('contratador');
        $trabajador = $this->usuario('trabajador');

        $activa1 = $this->contratacion($contratador, $trabajador, 'activa');            // pub activa
        $this->contratacion($contratador, $trabajador, 'activa', 0);                    // pub inactiva
        $finalizada = $this->contratacion($contratador, $trabajador, 'finalizada');     // pub activa
        $this->contratacion($contratador, $trabajador, 'cancelada', 0);                 // pub inactiva

        // Confirmados: 10.10 + 20.20 + 0.70 = 31.00 (sumas que fallan con float ingenuo)
        $this->pago($activa1, '10.10', Pago::ESTADO_CONFIRMADO);
        $this->pago($activa1, '20.20', Pago::ESTADO_CONFIRMADO);
        $this->pago($finalizada, '0.70', Pago::ESTADO_CONFIRMADO);
        $this->pago($activa1, '99.99', Pago::ESTADO_PENDIENTE);
        $this->pago($finalizada, '50.00', Pago::ESTADO_ANULADO);

        Sanctum::actingAs($admin);

        $r = $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('publicaciones_activas', 2)
            ->assertJsonPath('contrataciones_activas', 2)
            ->assertJsonPath('pagos_confirmados_bs', '31.00')
            ->assertJsonPath('simulado', true);

        $this->assertSame(
            ['publicaciones_activas', 'contrataciones_activas', 'pagos_confirmados_bs', 'simulado', 'generado_en'],
            array_keys($r->json())
        );
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $r->json('generado_en'));
        $this->assertIsInt($r->json('publicaciones_activas'));
        $this->assertIsString($r->json('pagos_confirmados_bs'));
    }

    public function test_sin_datos_devuelve_ceros(): void
    {
        Sanctum::actingAs($this->usuario('administrador'));

        $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('publicaciones_activas', 0)
            ->assertJsonPath('contrataciones_activas', 0)
            ->assertJsonPath('pagos_confirmados_bs', '0.00')
            ->assertJsonPath('simulado', true);
    }

    public function test_no_administradores_reciben_403_e_invitado_401(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();

        Sanctum::actingAs($this->usuario('contratador'));
        $this->getJson(self::URL)->assertForbidden();

        Sanctum::actingAs($this->usuario('trabajador'));
        $this->getJson(self::URL)->assertForbidden();
    }
}
