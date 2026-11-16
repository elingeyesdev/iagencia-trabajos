<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Parametro;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 3.3 — POST /api/contratador/contrataciones/{id}/pagos (pruebas básicas;
 * la batería completa la añade redwork-qa).
 */
class RegistrarPagoApiTest extends TestCase
{
    use RefreshDatabase;

    private int $cedula = 9500;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    private function usuario(string $rol): User
    {
        $cedula = $this->cedula++;
        $user = User::create([
            'cedula'     => $cedula,
            'nombre'     => "Usuario {$cedula}",
            'email'      => "u{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);
        $user->assignRole($rol);

        return $user;
    }

    /** @return array{0: User, 1: Contratacion} */
    private function escenario(string $estado = 'activa', $monto = '100.00'): array
    {
        $contratador = $this->usuario('contratador');
        $trabajador  = $this->usuario('trabajador');

        $publicacion = Publicacion::create([
            'tipo' => 'oferta', 'titulo' => 'Trabajo', 'descripcion' => 'Desc', 'pago' => $monto,
            'lugar' => 'Ciudad', 'fecha_publicacion' => '2026-01-10', 'cedula' => $contratador->cedula,
            'id_categoria' => 1, 'estado' => 1,
        ]);
        $postulacion = Postulacion::create([
            'id_labor' => $publicacion->id_labor, 'cedula' => $trabajador->cedula,
            'fecha_postulacion' => '2026-01-11', 'estado' => 'aceptada',
        ]);
        $contratacion = Contratacion::create([
            'id_postulacion' => $postulacion->id_postulacion, 'fecha_inicio' => '2026-01-15',
            'monto_acordado' => $monto, 'estado' => $estado,
        ]);

        return [$contratador, $contratacion];
    }

    private function url(int $id): string
    {
        return "/api/contratador/contrataciones/{$id}/pagos";
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'monto'      => '40.00',
            'fecha_pago' => now()->toDateString(),
            'metodo'     => 'transferencia',
        ], $cambios);
    }

    public function test_contratador_registra_pago_simulado_pendiente(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $respuesta = $this->postJson($this->url($contratacion->id_contratacion), $this->datos([
            'observacion' => 'Primer abono',
            // Campos protegidos: deben ignorarse.
            'estado' => 'confirmado', 'monto_comision' => '40.00', 'es_simulado' => false,
            'referencia_externa' => 'HACK', 'cedula_registra' => 1,
        ]));

        $respuesta->assertCreated()
            ->assertJsonPath('message', 'Pago simulado registrado. No se realizó ningún movimiento real de dinero.')
            ->assertJsonPath('simulado', true)
            ->assertJsonPath('data.monto', '40.00')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.porcentaje_comision', '0.00')
            ->assertJsonPath('data.monto_comision', '0.00')
            ->assertJsonPath('data.monto_neto', '40.00')
            ->assertJsonPath('data.es_simulado', true)
            ->assertJsonPath('data.proveedor', 'simulado')
            ->assertJsonPath('resumen.monto_acordado', '100.00')
            ->assertJsonPath('resumen.pagado_no_anulado', '40.00')
            ->assertJsonPath('resumen.saldo_disponible', '60.00');

        $this->assertMatchesRegularExpression('/^SIM-[0-9A-Z]{26}$/', $respuesta->json('data.referencia_externa'));

        $pago = Pago::sole();
        $this->assertSame('pendiente', $pago->estado);
        $this->assertSame($contratador->cedula, (int) $pago->cedula_registra);
        $this->assertSame('Primer abono', $pago->observacion);
        $this->assertNotNull($pago->fecha_registro);
        $this->assertNull($pago->cedula_confirma);
    }

    public function test_copia_el_porcentaje_de_comision_vigente(): void
    {
        Parametro::where('clave', 'comision_porcentaje')->update(['valor' => '12.50']);
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['monto' => '80']))
            ->assertCreated()
            ->assertJsonPath('data.porcentaje_comision', '12.50')
            ->assertJsonPath('data.monto_comision', '10.00')
            ->assertJsonPath('data.monto_neto', '70.00');
    }

    public function test_pagos_parciales_hasta_el_tope_y_rechazo_al_superarlo(): void
    {
        [$contratador, $contratacion] = $this->escenario('finalizada');
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos(['monto' => '60.10']))->assertCreated();

        $this->postJson($url, $this->datos(['monto' => '39.91']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['monto'])
            ->assertJsonPath('errors.monto.0', 'El monto supera el saldo disponible de la contratación (39.90).');

        $this->postJson($url, $this->datos(['monto' => '39.90']))
            ->assertCreated()
            ->assertJsonPath('resumen.saldo_disponible', '0.00');

        $this->postJson($url, $this->datos(['monto' => '0.01']))->assertStatus(422)->assertJsonValidationErrors(['monto']);
        $this->assertSame(2, Pago::count());
    }

    public function test_los_pagos_anulados_no_cuentan_para_el_tope(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos(['monto' => '100.00']))->assertCreated();
        Pago::query()->update(['estado' => 'anulado']);

        $this->postJson($url, $this->datos(['monto' => '100.00']))->assertCreated();
    }

    public function test_contratacion_cancelada_devuelve_422_sin_escrituras(): void
    {
        [$contratador, $contratacion] = $this->escenario('cancelada');
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se pueden registrar pagos en una contratación cancelada.']);
        $this->assertSame(0, Pago::count());
    }

    public function test_contratacion_ajena_devuelve_403_y_inexistente_404(): void
    {
        [, $contratacion] = $this->escenario();
        Sanctum::actingAs($this->usuario('contratador'));

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertStatus(403)
            ->assertExactJson(['message' => 'No autorizado.']);
        $this->postJson($this->url(999999), $this->datos())->assertNotFound();
        $this->assertSame(0, Pago::count());
    }

    public function test_proveedor_no_soportado_no_oculta_403_ni_404(): void
    {
        Parametro::where('clave', 'pagos_proveedor')->update(['valor' => 'banco']);
        [, $contratacion] = $this->escenario();
        Sanctum::actingAs($this->usuario('contratador'));

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertStatus(403)
            ->assertExactJson(['message' => 'No autorizado.']);
        $this->postJson($this->url(999999), $this->datos())->assertNotFound();
        $this->assertSame(0, Pago::count());
    }

    public function test_formato_estricto_y_normalizacion_del_monto(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        foreach (['5.', '.5', '+5', '-5', '1e1', '12,5', 1.0E+25] as $invalido) {
            $this->postJson($url, $this->datos(['monto' => $invalido]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['monto']);
        }
        $this->assertSame(0, Pago::count());

        $this->postJson($url, $this->datos(['monto' => '00000000000005']))->assertCreated()->assertJsonPath('data.monto', '5.00');
        $this->postJson($url, $this->datos(['monto' => '05.50']))->assertCreated()->assertJsonPath('data.monto', '5.50');
        $this->postJson($url, $this->datos(['monto' => 12.5]))->assertCreated()->assertJsonPath('data.monto', '12.50');
        $this->postJson($url, $this->datos(['monto' => 7]))->assertCreated()->assertJsonPath('data.monto', '7.00');
    }

    public function test_roles_no_contratador_y_sin_autenticar(): void
    {
        [, $contratacion] = $this->escenario();
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos())->assertUnauthorized();

        Sanctum::actingAs($this->usuario('trabajador'));
        $this->postJson($url, $this->datos())->assertForbidden();

        Sanctum::actingAs($this->usuario('administrador'));
        $this->postJson($url, $this->datos())->assertForbidden();

        $this->assertSame(0, Pago::count());
    }

    public function test_validaciones_del_request(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors(['monto', 'fecha_pago', 'metodo']);
        $this->postJson($url, $this->datos(['monto' => '0']))->assertJsonValidationErrors(['monto']);
        $this->postJson($url, $this->datos(['monto' => '10.001']))->assertJsonValidationErrors(['monto']);
        $this->postJson($url, $this->datos(['metodo' => 'tarjeta']))->assertJsonValidationErrors(['metodo']);
        $this->postJson($url, $this->datos(['fecha_pago' => now()->addDay()->toDateString()]))->assertJsonValidationErrors(['fecha_pago']);
        $this->postJson($url, $this->datos(['observacion' => str_repeat('a', 256)]))->assertJsonValidationErrors(['observacion']);
        $this->assertSame(0, Pago::count());
    }
}
