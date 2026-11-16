<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bloque 1 — Gestión de pagos SIMULADOS: consultas por rol, listado administrativo,
 * detalle, confirmación y anulación.
 */
class PagosGestionApiTest extends TestCase
{
    use RefreshDatabase;

    private const AVISO = 'Operaciones simuladas: no representan dinero real.';

    private int $siguienteCedula = 8100;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    // ─────────────────────────────── Helpers ───────────────────────────────

    private function usuario(?string $rol): User
    {
        $cedula = $this->siguienteCedula++;
        $user = User::create([
            'cedula'     => $cedula,
            'nombre'     => "Usuario Gestion {$cedula}",
            'email'      => "gestion{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);
        if ($rol !== null) {
            $user->assignRole($rol);
        }

        return $user;
    }

    /** @return array{contratador: User, trabajador: User, contratacion: Contratacion, publicacion: Publicacion} */
    private function escenario(string $estado = 'activa', string $monto = '100.00', ?User $contratador = null, ?User $trabajador = null): array
    {
        $contratador ??= $this->usuario('contratador');
        $trabajador  ??= $this->usuario('trabajador');

        $publicacion = Publicacion::create([
            'tipo' => 'oferta', 'titulo' => 'Trabajo ' . $this->siguienteCedula, 'descripcion' => 'Desc', 'pago' => $monto,
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

        return compact('contratador', 'trabajador', 'contratacion', 'publicacion');
    }

    private function pago(Contratacion $c, string $monto, string $estado = 'pendiente', string $fecha = '2026-02-01', ?int $registra = null): Pago
    {
        $pago = new Pago();
        $pago->forceFill([
            'id_contratacion'    => $c->id_contratacion,
            'monto'              => $monto,
            'monto_neto'         => $monto,
            'fecha_pago'         => $fecha,
            'metodo'             => 'efectivo',
            'estado'             => $estado,
            'referencia_externa' => 'SIM-GESTION-' . uniqid('', true),
            'cedula_registra'    => $registra ?? 1,
            'fecha_registro'     => now(),
        ])->save();

        return $pago;
    }

    private function filaPago(int $id): array
    {
        return (array) DB::table('pagos')->where('id_pago', $id)->first();
    }

    // ───────────────────── GET contratador/contrataciones/{id}/pagos ─────────────────────

    public function test_contratador_ve_pagos_y_resumen_de_su_contratacion(): void
    {
        $e = $this->escenario('activa', '100.00');
        $p1 = $this->pago($e['contratacion'], '30.00', 'confirmado');
        $p2 = $this->pago($e['contratacion'], '20.00', 'pendiente');
        $this->pago($e['contratacion'], '50.00', 'anulado');

        Sanctum::actingAs($e['contratador']);
        $r = $this->getJson("/api/contratador/contrataciones/{$e['contratacion']->id_contratacion}/pagos")
            ->assertOk()
            ->assertJsonPath('modo_pagos', 'simulado')
            ->assertJsonPath('aviso', self::AVISO)
            ->assertJsonPath('data.resumen', [
                'id_contratacion'     => $e['contratacion']->id_contratacion,
                'estado_contratacion' => 'activa',
                'monto_acordado'      => '100.00',
                'pagado_no_anulado'   => '50.00',
                'pagado_confirmado'   => '30.00',
                'pagado_pendiente'    => '20.00',
                'saldo_disponible'    => '50.00',
                'estado_pago'         => 'parcial',
            ])
            ->assertJsonCount(3, 'data.pagos');

        $pagos = $r->json('data.pagos');
        $this->assertGreaterThan($pagos[1]['id_pago'], $pagos[0]['id_pago'], 'Orden id_pago desc');
        $this->assertTrue($pagos[0]['es_simulado']);
        $this->assertArrayHasKey('auditoria', $pagos[0]);
        $this->assertStringNotContainsString('contrasena', $r->getContent());
        $this->assertStringNotContainsString('@redwork.test', $r->getContent());
        $this->assertContains($p1->id_pago, array_column($pagos, 'id_pago'));
        $this->assertContains($p2->id_pago, array_column($pagos, 'id_pago'));
    }

    public function test_resumen_estado_pago_sin_pagos_y_completo(): void
    {
        $e = $this->escenario('activa', '80.00');
        Sanctum::actingAs($e['contratador']);
        $url = "/api/contratador/contrataciones/{$e['contratacion']->id_contratacion}/pagos";

        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.resumen.estado_pago', 'sin_pagos')
            ->assertJsonPath('data.resumen.saldo_disponible', '80.00')
            ->assertJsonPath('data.pagos', []);

        $this->pago($e['contratacion'], '80.00', 'pendiente');
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.resumen.estado_pago', 'completo')
            ->assertJsonPath('data.resumen.saldo_disponible', '0.00');
    }

    public function test_contratador_recibe_403_en_contratacion_ajena_y_404_inexistente(): void
    {
        $e = $this->escenario();
        $this->pago($e['contratacion'], '10.00');
        Sanctum::actingAs($this->usuario('contratador'));

        $this->getJson("/api/contratador/contrataciones/{$e['contratacion']->id_contratacion}/pagos")
            ->assertForbidden()->assertExactJson(['message' => 'No autorizado.']);
        $this->getJson('/api/contratador/contrataciones/999999/pagos')->assertNotFound();
    }

    public function test_consulta_contratador_exige_autenticacion_y_rol(): void
    {
        $e = $this->escenario();
        $url = "/api/contratador/contrataciones/{$e['contratacion']->id_contratacion}/pagos";

        $this->getJson($url)->assertUnauthorized();

        Sanctum::actingAs($e['trabajador']);
        $this->getJson($url)->assertForbidden();

        Sanctum::actingAs($this->usuario('administrador'));
        $this->getJson($url)->assertForbidden();
    }

    // ───────────────────── GET admin/contrataciones/{id}/pagos ─────────────────────

    public function test_admin_ve_pagos_de_cualquier_contratacion(): void
    {
        $e = $this->escenario('finalizada', '60.00');
        $this->pago($e['contratacion'], '60.00', 'confirmado');

        Sanctum::actingAs($this->usuario('administrador'));
        $this->getJson("/api/admin/contrataciones/{$e['contratacion']->id_contratacion}/pagos")
            ->assertOk()
            ->assertJsonPath('modo_pagos', 'simulado')
            ->assertJsonPath('data.resumen.estado_contratacion', 'finalizada')
            ->assertJsonPath('data.resumen.estado_pago', 'completo')
            ->assertJsonPath('data.resumen.pagado_confirmado', '60.00')
            ->assertJsonCount(1, 'data.pagos');

        $this->getJson('/api/admin/contrataciones/999999/pagos')->assertNotFound();
    }

    public function test_consulta_admin_por_contratacion_rechaza_otros_roles(): void
    {
        $e = $this->escenario();
        $url = "/api/admin/contrataciones/{$e['contratacion']->id_contratacion}/pagos";

        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs($e['contratador']);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($e['trabajador']);
        $this->getJson($url)->assertForbidden();
    }

    // ───────────────────────────── GET trabajador/pagos ─────────────────────────────

    public function test_trabajador_solo_ve_sus_pagos(): void
    {
        $trabajador = $this->usuario('trabajador');
        $propio  = $this->escenario('activa', '100.00', null, $trabajador);
        $ajeno   = $this->escenario('activa', '100.00');
        $pPropio = $this->pago($propio['contratacion'], '40.00', 'confirmado');
        $this->pago($ajeno['contratacion'], '25.00');

        Sanctum::actingAs($trabajador);
        $r = $this->getJson('/api/trabajador/pagos')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('modo_pagos', 'simulado')
            ->assertJsonPath('aviso', self::AVISO)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_pago', $pPropio->id_pago)
            ->assertJsonPath('data.0.id_contratacion', $propio['contratacion']->id_contratacion)
            ->assertJsonPath('data.0.publicacion.titulo', $propio['publicacion']->titulo)
            ->assertJsonPath('data.0.contratacion.monto_acordado', '100.00')
            ->assertJsonPath('data.0.es_simulado', true)
            ->assertJsonPath('resumen.0.pagado_confirmado', '40.00')
            ->assertJsonPath('resumen.0.saldo_disponible', '60.00');

        $this->assertArrayNotHasKey('trabajador', $r->json('data.0'));
        $this->assertStringNotContainsString('@redwork.test', $r->getContent());
    }

    public function test_trabajador_sin_pagos_recibe_lista_vacia(): void
    {
        Sanctum::actingAs($this->usuario('trabajador'));
        $this->getJson('/api/trabajador/pagos')->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data', [])->assertJsonPath('resumen', []);
    }

    public function test_pagos_trabajador_exige_rol_trabajador(): void
    {
        $this->getJson('/api/trabajador/pagos')->assertUnauthorized();
        Sanctum::actingAs($this->usuario('contratador'));
        $this->getJson('/api/trabajador/pagos')->assertForbidden();
        Sanctum::actingAs($this->usuario('administrador'));
        $this->getJson('/api/trabajador/pagos')->assertForbidden();
    }

    public function test_trabajador_no_tiene_rutas_de_escritura_de_pagos(): void
    {
        $e = $this->escenario();
        $pago = $this->pago($e['contratacion'], '10.00');
        Sanctum::actingAs($e['trabajador']);

        $this->postJson('/api/trabajador/pagos', ['monto' => '5'])->assertStatus(405);
        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")->assertForbidden();
        $this->postJson("/api/contratador/contrataciones/{$e['contratacion']->id_contratacion}/pagos", [])->assertForbidden();
        $this->assertSame('pendiente', $pago->fresh()->estado);
    }

    // ───────────────────────────── GET admin/pagos ─────────────────────────────

    public function test_admin_lista_pagos_con_contexto_paginacion_y_totales(): void
    {
        $e1 = $this->escenario('activa', '200.00');
        $e2 = $this->escenario('activa', '200.00');
        $this->pago($e1['contratacion'], '10.50', 'pendiente');
        $this->pago($e1['contratacion'], '20.25', 'confirmado');
        $this->pago($e2['contratacion'], '30.00', 'anulado');
        $ultimo = $this->pago($e2['contratacion'], '0.10', 'pendiente');

        Sanctum::actingAs($this->usuario('administrador'));
        $r = $this->getJson('/api/admin/pagos?per_page=2')
            ->assertOk()
            ->assertJsonPath('modo_pagos', 'simulado')
            ->assertJsonPath('aviso', self::AVISO)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id_pago', $ultimo->id_pago)
            ->assertJsonPath('data.0.publicacion.titulo', $e2['publicacion']->titulo)
            ->assertJsonPath('data.0.contratador.cedula', $e2['contratador']->cedula)
            ->assertJsonPath('data.0.contratador.nombre', $e2['contratador']->nombre)
            ->assertJsonPath('data.0.trabajador.nombre', $e2['trabajador']->nombre)
            ->assertJsonPath('data.0.contratacion.monto_acordado', '200.00')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('totales.pendiente', ['cantidad' => 2, 'monto' => '10.60'])
            ->assertJsonPath('totales.confirmado', ['cantidad' => 1, 'monto' => '20.25'])
            ->assertJsonPath('totales.anulado', ['cantidad' => 1, 'monto' => '30.00'])
            ->assertJsonPath('totales.total', ['cantidad' => 4, 'monto' => '60.85']);

        $this->assertStringNotContainsString('@redwork.test', $r->getContent());
        $this->assertStringNotContainsString('contrasena', $r->getContent());
    }

    public function test_admin_filtra_por_estado_contratacion_y_fechas(): void
    {
        $e1 = $this->escenario('activa', '500.00');
        $e2 = $this->escenario('activa', '500.00');
        $a = $this->pago($e1['contratacion'], '10.00', 'pendiente', '2026-03-01');
        $b = $this->pago($e1['contratacion'], '20.00', 'confirmado', '2026-03-15');
        $c = $this->pago($e2['contratacion'], '30.00', 'pendiente', '2026-04-01');

        Sanctum::actingAs($this->usuario('administrador'));

        $ids = fn ($r) => array_column($r->json('data'), 'id_pago');

        $r = $this->getJson('/api/admin/pagos?estado=pendiente')->assertOk();
        $this->assertSame([$c->id_pago, $a->id_pago], $ids($r));
        $r->assertJsonPath('totales.pendiente.monto', '40.00')
          ->assertJsonPath('totales.confirmado', ['cantidad' => 0, 'monto' => '0.00']);

        $r = $this->getJson("/api/admin/pagos?id_contratacion={$e1['contratacion']->id_contratacion}")->assertOk();
        $this->assertSame([$b->id_pago, $a->id_pago], $ids($r));

        $r = $this->getJson('/api/admin/pagos?desde=2026-03-01&hasta=2026-03-15')->assertOk();
        $this->assertSame([$b->id_pago, $a->id_pago], $ids($r));
        $r->assertJsonPath('totales.total', ['cantidad' => 2, 'monto' => '30.00']);

        $r = $this->getJson('/api/admin/pagos?desde=2026-03-02')->assertOk();
        $this->assertSame([$c->id_pago, $b->id_pago], $ids($r));
    }

    public function test_admin_listado_valida_filtros(): void
    {
        Sanctum::actingAs($this->usuario('administrador'));

        $this->getJson('/api/admin/pagos?estado=pagado')->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->getJson('/api/admin/pagos?per_page=101')->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->getJson('/api/admin/pagos?per_page=0')->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->getJson('/api/admin/pagos?desde=01-03-2026')->assertStatus(422)->assertJsonValidationErrors('desde');
        $this->getJson('/api/admin/pagos?desde=2026-03-10&hasta=2026-03-01')->assertStatus(422)->assertJsonValidationErrors('hasta');
        $this->getJson('/api/admin/pagos?id_contratacion=abc')->assertStatus(422)->assertJsonValidationErrors('id_contratacion');

        $this->getJson('/api/admin/pagos')->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('totales.total', ['cantidad' => 0, 'monto' => '0.00']);
    }

    public function test_admin_listado_rechaza_otros_roles(): void
    {
        $this->getJson('/api/admin/pagos')->assertUnauthorized();
        Sanctum::actingAs($this->usuario('contratador'));
        $this->getJson('/api/admin/pagos')->assertForbidden();
        Sanctum::actingAs($this->usuario('trabajador'));
        $this->getJson('/api/admin/pagos')->assertForbidden();
    }

    public function test_admin_listado_no_genera_consultas_n_mas_1(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $e = $this->escenario('activa', '100.00');
            $this->pago($e['contratacion'], '10.00');
        }
        Sanctum::actingAs($this->usuario('administrador'));

        DB::enableQueryLog();
        $this->getJson('/api/admin/pagos')->assertOk()->assertJsonCount(6, 'data');
        $consultas = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'pagos')
            || str_contains($q['query'], 'contrataciones') || str_contains($q['query'], 'postulaciones')
            || str_contains($q['query'], 'publicaciones') || str_contains($q['query'], 'usuarios'));
        DB::disableQueryLog();

        // totales + count + página + 5 relaciones (contratación, postulación, trabajador, publicación, contratador)
        // + consultas propias de autenticación/roles: acotado e independiente del número de filas.
        $this->assertLessThanOrEqual(12, $consultas->count());
    }

    // ───────────────────────────── GET admin/pagos/{id} ─────────────────────────────

    public function test_admin_ve_detalle_con_auditoria(): void
    {
        $e = $this->escenario('activa', '100.00');
        $pago = $this->pago($e['contratacion'], '25.00', 'pendiente', '2026-02-01', $e['contratador']->cedula);

        Sanctum::actingAs($this->usuario('administrador'));
        $this->getJson("/api/admin/pagos/{$pago->id_pago}")
            ->assertOk()
            ->assertJsonPath('modo_pagos', 'simulado')
            ->assertJsonPath('simulado', true)
            ->assertJsonPath('data.id_pago', $pago->id_pago)
            ->assertJsonPath('data.es_simulado', true)
            ->assertJsonPath('data.contratacion.fecha_inicio', '2026-01-15')
            ->assertJsonPath('data.auditoria.registrado_por', [
                'cedula' => $e['contratador']->cedula, 'nombre' => $e['contratador']->nombre,
            ])
            ->assertJsonPath('data.auditoria.confirmado_por', null)
            ->assertJsonPath('data.auditoria.anulado_por', null)
            ->assertJsonPath('data.resumen.pagado_pendiente', '25.00');

        $this->getJson('/api/admin/pagos/999999')->assertNotFound();
    }

    public function test_detalle_admin_rechaza_otros_roles(): void
    {
        $e = $this->escenario();
        $pago = $this->pago($e['contratacion'], '10.00');

        $this->getJson("/api/admin/pagos/{$pago->id_pago}")->assertUnauthorized();
        Sanctum::actingAs($e['contratador']);
        $this->getJson("/api/admin/pagos/{$pago->id_pago}")->assertForbidden();
    }

    // ───────────────────────────── PUT confirmar ─────────────────────────────

    public function test_admin_confirma_pago_pendiente_con_auditoria(): void
    {
        $e = $this->escenario('activa', '100.00');
        $pago  = $this->pago($e['contratacion'], '40.00');
        $otro  = $this->pago($e['contratacion'], '10.00');
        $antes = $this->filaPago($otro->id_pago);
        $admin = $this->usuario('administrador');

        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")
            ->assertOk()
            ->assertJsonPath('message', 'Pago simulado confirmado.')
            ->assertJsonPath('simulado', true)
            ->assertJsonPath('data.estado', 'confirmado')
            ->assertJsonPath('data.auditoria.confirmado_por', ['cedula' => $admin->cedula, 'nombre' => $admin->nombre])
            ->assertJsonPath('data.resumen.pagado_confirmado', '40.00')
            ->assertJsonPath('data.resumen.pagado_pendiente', '10.00');

        $fresco = $pago->fresh();
        $this->assertSame('confirmado', $fresco->estado);
        $this->assertSame($admin->cedula, (int) $fresco->cedula_confirma);
        $this->assertNotNull($fresco->fecha_confirmacion);
        $this->assertNull($fresco->cedula_anula);
        $this->assertSame($antes, $this->filaPago($otro->id_pago), 'No se modifican otras filas.');
    }

    public function test_doble_confirmacion_responde_422(): void
    {
        $e = $this->escenario();
        $pago = $this->pago($e['contratacion'], '10.00');
        Sanctum::actingAs($this->usuario('administrador'));

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")->assertOk();
        $fecha = $pago->fresh()->fecha_confirmacion;

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")
            ->assertStatus(422)->assertJsonPath('message', 'El pago ya está confirmado.');
        $this->assertEquals($fecha, $pago->fresh()->fecha_confirmacion);
    }

    public function test_no_se_confirma_pago_anulado(): void
    {
        $e = $this->escenario();
        $pago = $this->pago($e['contratacion'], '10.00', 'anulado');
        Sanctum::actingAs($this->usuario('administrador'));

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")->assertStatus(422);
        $this->assertSame('anulado', $pago->fresh()->estado);
    }

    public function test_no_se_confirma_pago_de_contratacion_cancelada(): void
    {
        $e = $this->escenario('activa');
        $pago = $this->pago($e['contratacion'], '10.00');
        $e['contratacion']->update(['estado' => 'cancelada']);
        Sanctum::actingAs($this->usuario('administrador'));

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede confirmar un pago de una contratación cancelada.']);

        $fresco = $pago->fresh();
        $this->assertSame('pendiente', $fresco->estado);
        $this->assertNull($fresco->cedula_confirma);
        $this->assertNull($fresco->fecha_confirmacion);
    }

    public function test_confirmar_pago_de_contratacion_finalizada_es_valido(): void
    {
        $e = $this->escenario('finalizada');
        $pago = $this->pago($e['contratacion'], '10.00');
        Sanctum::actingAs($this->usuario('administrador'));

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")->assertOk();
    }

    public function test_confirmar_404_y_roles(): void
    {
        $e = $this->escenario();
        $pago = $this->pago($e['contratacion'], '10.00');

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")->assertUnauthorized();
        Sanctum::actingAs($e['contratador']);
        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")->assertForbidden();
        Sanctum::actingAs($this->usuario('administrador'));
        $this->putJson('/api/admin/pagos/999999/confirmar')->assertNotFound();

        $this->assertSame('pendiente', $pago->fresh()->estado);
    }

    // ───────────────────────────── PUT anular ─────────────────────────────

    public function test_anular_exige_motivo_valido(): void
    {
        $e = $this->escenario();
        $pago = $this->pago($e['contratacion'], '10.00');
        Sanctum::actingAs($this->usuario('administrador'));
        $url = "/api/admin/pagos/{$pago->id_pago}/anular";

        $this->putJson($url, [])->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_anulacion' => 'El motivo de anulación es obligatorio.']);
        $this->putJson($url, ['motivo_anulacion' => '    abc   '])->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_anulacion' => 'al menos 5 caracteres']);
        $this->putJson($url, ['motivo_anulacion' => str_repeat('x', 256)])->assertStatus(422)
            ->assertJsonValidationErrors('motivo_anulacion');

        $this->assertSame('pendiente', $pago->fresh()->estado);
    }

    public function test_admin_anula_pago_pendiente(): void
    {
        $e = $this->escenario();
        $pago  = $this->pago($e['contratacion'], '10.00');
        $admin = $this->usuario('administrador');
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/anular", ['motivo_anulacion' => '  Registro duplicado  '])
            ->assertOk()
            ->assertJsonPath('message', 'Pago simulado anulado.')
            ->assertJsonPath('simulado', true)
            ->assertJsonPath('data.estado', 'anulado')
            ->assertJsonPath('data.motivo_anulacion', 'Registro duplicado')
            ->assertJsonPath('data.auditoria.anulado_por', ['cedula' => $admin->cedula, 'nombre' => $admin->nombre]);

        $fresco = $pago->fresh();
        $this->assertSame($admin->cedula, (int) $fresco->cedula_anula);
        $this->assertNotNull($fresco->fecha_anulacion);
        $this->assertSame('Registro duplicado', $fresco->motivo_anulacion);
    }

    public function test_anular_confirmado_libera_saldo_y_conserva_confirmacion(): void
    {
        $e = $this->escenario('activa', '100.00');
        $pago = $this->pago($e['contratacion'], '100.00');
        $otraContratacion = $this->escenario('activa', '100.00');
        $ajeno = $this->pago($otraContratacion['contratacion'], '50.00');
        $antesAjeno = $this->filaPago($ajeno->id_pago);

        Sanctum::actingAs($this->usuario('administrador'));
        $this->putJson("/api/admin/pagos/{$pago->id_pago}/confirmar")->assertOk();
        $this->putJson("/api/admin/pagos/{$pago->id_pago}/anular", ['motivo_anulacion' => 'Pago revertido por error'])
            ->assertOk()
            ->assertJsonPath('data.resumen.pagado_no_anulado', '0.00')
            ->assertJsonPath('data.resumen.saldo_disponible', '100.00')
            ->assertJsonPath('data.resumen.estado_pago', 'sin_pagos');

        $fresco = $pago->fresh();
        $this->assertSame('anulado', $fresco->estado);
        $this->assertNotNull($fresco->cedula_confirma, 'Se conserva la auditoría de confirmación.');
        $this->assertNotNull($fresco->fecha_confirmacion);
        $this->assertSame($antesAjeno, $this->filaPago($ajeno->id_pago));

        // El saldo liberado permite registrar un nuevo pago por el total.
        Sanctum::actingAs($e['contratador']);
        $this->postJson("/api/contratador/contrataciones/{$e['contratacion']->id_contratacion}/pagos", [
            'monto' => '100.00', 'fecha_pago' => now()->toDateString(), 'metodo' => 'qr',
        ])->assertCreated();
        $this->getJson("/api/contratador/contrataciones/{$e['contratacion']->id_contratacion}/pagos")
            ->assertOk()->assertJsonPath('data.resumen.estado_pago', 'completo');
    }

    public function test_anular_pago_ya_anulado_responde_422(): void
    {
        $e = $this->escenario();
        $pago = $this->pago($e['contratacion'], '10.00');
        Sanctum::actingAs($this->usuario('administrador'));

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/anular", ['motivo_anulacion' => 'Primer motivo'])->assertOk();
        $this->putJson("/api/admin/pagos/{$pago->id_pago}/anular", ['motivo_anulacion' => 'Segundo motivo'])
            ->assertStatus(422)->assertJsonPath('message', 'El pago ya está anulado.');

        $this->assertSame('Primer motivo', $pago->fresh()->motivo_anulacion);
    }

    public function test_anular_pago_de_contratacion_cancelada_es_valido(): void
    {
        $e = $this->escenario('cancelada');
        $pago = $this->pago($e['contratacion'], '10.00');
        Sanctum::actingAs($this->usuario('administrador'));

        $this->putJson("/api/admin/pagos/{$pago->id_pago}/anular", ['motivo_anulacion' => 'Contratación cancelada'])
            ->assertOk()->assertJsonPath('data.estado', 'anulado');
    }

    public function test_anular_404_y_roles(): void
    {
        $e = $this->escenario();
        $pago = $this->pago($e['contratacion'], '10.00');
        $url = "/api/admin/pagos/{$pago->id_pago}/anular";
        $datos = ['motivo_anulacion' => 'Motivo válido'];

        $this->putJson($url, $datos)->assertUnauthorized();
        Sanctum::actingAs($e['contratador']);
        $this->putJson($url, $datos)->assertForbidden();
        Sanctum::actingAs($e['trabajador']);
        $this->putJson($url, $datos)->assertForbidden();
        Sanctum::actingAs($this->usuario('administrador'));
        $this->putJson('/api/admin/pagos/999999/anular', $datos)->assertNotFound();

        $this->assertSame('pendiente', $pago->fresh()->estado);
    }

    public function test_rutas_no_numericas_no_coinciden(): void
    {
        Sanctum::actingAs($this->usuario('administrador'));
        $this->getJson('/api/admin/pagos/abc')->assertNotFound();
        $this->putJson('/api/admin/pagos/abc/confirmar')->assertNotFound();
    }
}
