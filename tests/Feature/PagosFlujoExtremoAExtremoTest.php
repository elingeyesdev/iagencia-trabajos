<?php

namespace Tests\Feature;

use App\Models\Contratacion;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bloque 6 — Flujo extremo a extremo de pagos SIMULADOS vía API + reporte web.
 *
 * Contratación objetivo: monto acordado 100.00 (activa).
 *  1. Contratador registra 40.00 (transferencia) y 35.50 (qr) → ambos pendientes, saldo 24.50.
 *  2. Admin lista con filtro estado=pendiente → 2 pagos, total 75.50.
 *  3. Admin confirma 40.00 y anula 35.50 con motivo.
 *  4. KPI pagos_confirmados_bs = 40.00 (solo el confirmado).
 *  5. Resumen contratador/trabajador: confirmado 40.00, pendiente 0.00, no anulado 40.00, saldo 60.00, parcial.
 *  6. /reportes/pagos: confirmados 40.00 / comisión 0.00 / neto 40.00, banda de simulados.
 *  7. Otra contratación (de otro contratador) y los usuarios quedan intactos.
 */
class PagosFlujoExtremoAExtremoTest extends TestCase
{
    use RefreshDatabase;

    private int $cedula = 8800;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    private function usuario(string $rol, string $nombre): User
    {
        $u = User::factory()->create(['cedula' => $this->cedula++, 'nombre' => $nombre]);
        $u->assignRole($rol);

        return $u;
    }

    private function contratacion(User $contratador, User $trabajador, string $titulo, string $monto): Contratacion
    {
        $pub = Publicacion::create([
            'tipo' => 'oferta', 'titulo' => $titulo, 'descripcion' => 'D', 'pago' => $monto, 'lugar' => 'L',
            'fecha_publicacion' => '2026-01-10', 'cedula' => $contratador->cedula, 'id_categoria' => 1, 'estado' => 1,
        ]);
        $pos = Postulacion::create([
            'id_labor' => $pub->id_labor, 'cedula' => $trabajador->cedula,
            'fecha_postulacion' => '2026-01-11', 'estado' => 'aceptada',
        ]);

        return Contratacion::create([
            'id_postulacion' => $pos->id_postulacion, 'fecha_inicio' => '2026-01-15',
            'monto_acordado' => $monto, 'estado' => 'activa',
        ]);
    }

    /** Instantánea de las tablas que el flujo NO debe modificar (salvo pagos). */
    private function instantanea(): array
    {
        return [
            'usuarios'       => DB::table('usuarios')->orderBy('cedula')->get()->map(fn ($r) => (array) $r)->all(),
            'publicaciones'  => DB::table('publicaciones')->orderBy('id_labor')->get()->map(fn ($r) => (array) $r)->all(),
            'postulaciones'  => DB::table('postulaciones')->orderBy('id_postulacion')->get()->map(fn ($r) => (array) $r)->all(),
            'contrataciones' => DB::table('contrataciones')->orderBy('id_contratacion')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public function test_flujo_completo_registro_confirmacion_anulacion_kpi_resumenes_y_reporte(): void
    {
        $admin       = $this->usuario('administrador', 'Ana Admin');
        $contratador = $this->usuario('contratador', 'Carlos Contratador');
        $trabajador  = $this->usuario('trabajador', 'Tania Trabajadora');
        $otroContr   = $this->usuario('contratador', 'Otro Contratador');
        $otroTrab    = $this->usuario('trabajador', 'Otro Trabajador');

        $c     = $this->contratacion($contratador, $trabajador, 'Pintar casa', '100.00');
        $ajena = $this->contratacion($otroContr, $otroTrab, 'Otra labor', '999.00');

        $antes = $this->instantanea();
        $hoy = now()->toDateString();
        $urlContr = "/api/contratador/contrataciones/{$c->id_contratacion}/pagos";

        // 1. Contratador registra 2 pagos parciales.
        Sanctum::actingAs($contratador);
        $p1 = $this->postJson($urlContr, ['monto' => '40.00', 'fecha_pago' => $hoy, 'metodo' => 'transferencia'])
            ->assertCreated()
            ->assertJsonPath('simulado', true)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('resumen.saldo_disponible', '60.00')
            ->json('data.id_pago');
        $p2 = $this->postJson($urlContr, ['monto' => '35.50', 'fecha_pago' => $hoy, 'metodo' => 'qr', 'observacion' => 'Segundo parcial'])
            ->assertCreated()
            ->assertJsonPath('resumen.pagado_no_anulado', '75.50')
            ->assertJsonPath('resumen.saldo_disponible', '24.50')
            ->json('data.id_pago');

        // 2. Admin lista con filtro de estado.
        Sanctum::actingAs($admin);
        $lista = $this->getJson('/api/admin/pagos?estado=pendiente')->assertOk()
            ->assertJsonPath('simulado', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('totales.pendiente.cantidad', 2)
            ->assertJsonPath('totales.pendiente.monto', '75.50');
        $this->assertEqualsCanonicalizing([$p1, $p2], array_column($lista->json('data'), 'id_pago'));
        $this->getJson('/api/admin/pagos?estado=confirmado')->assertOk()->assertJsonPath('meta.total', 0);

        // 3. Confirma uno y anula el otro con motivo.
        $this->putJson("/api/admin/pagos/{$p1}/confirmar")->assertOk()
            ->assertJsonPath('data.estado', 'confirmado')
            ->assertJsonPath('data.auditoria.confirmado_por.cedula', $admin->cedula);
        $this->putJson("/api/admin/pagos/{$p2}/anular", ['motivo_anulacion' => ''])->assertStatus(422)
            ->assertJsonValidationErrors('motivo_anulacion');
        $this->putJson("/api/admin/pagos/{$p2}/anular", ['motivo_anulacion' => 'Registro duplicado por error'])->assertOk()
            ->assertJsonPath('data.estado', 'anulado')
            ->assertJsonPath('data.motivo_anulacion', 'Registro duplicado por error')
            ->assertJsonPath('data.auditoria.anulado_por.cedula', $admin->cedula);

        // 4. KPI refleja solo el confirmado.
        $this->getJson('/api/admin/kpis')->assertOk()
            ->assertJsonPath('pagos_confirmados_bs', '40.00')
            ->assertJsonPath('contrataciones_activas', 2)
            ->assertJsonPath('simulado', true);

        $resumenEsperado = [
            'id_contratacion'     => $c->id_contratacion,
            'estado_contratacion' => 'activa',
            'monto_acordado'      => '100.00',
            'pagado_no_anulado'   => '40.00',
            'pagado_confirmado'   => '40.00',
            'pagado_pendiente'    => '0.00',
            'saldo_disponible'    => '60.00',
            'estado_pago'         => 'parcial',
        ];

        // 5a. Resumen del contratador.
        Sanctum::actingAs($contratador);
        $rc = $this->getJson($urlContr)->assertOk();
        $this->assertSame($resumenEsperado, $rc->json('data.resumen'));
        $this->assertCount(2, $rc->json('data.pagos'));

        // 5b. Resumen del trabajador (solo lectura) coherente con el del contratador.
        Sanctum::actingAs($trabajador);
        $rt = $this->getJson('/api/trabajador/pagos')->assertOk()->assertJsonPath('simulado', true);
        $this->assertSame([$resumenEsperado], $rt->json('resumen'));
        $this->assertEqualsCanonicalizing(
            [[$p1, 'confirmado'], [$p2, 'anulado']],
            array_map(fn ($p) => [$p['id_pago'], $p['estado']], $rt->json('data'))
        );
        // El trabajador no puede confirmar ni registrar.
        $this->putJson("/api/admin/pagos/{$p1}/confirmar")->assertForbidden();
        $this->postJson($urlContr, ['monto' => '1.00', 'fecha_pago' => $hoy, 'metodo' => 'otro'])->assertForbidden();

        // 6. Reporte de pagos (admin): página imprimible (plantilla + banda) y datos vía API JSON.
        $this->withoutVite();
        $html = $this->actingAs($admin, 'web')->get('/reportes/pagos')->assertOk()->getContent();
        $this->assertStringContainsString('OPERACIONES SIMULADAS — NO REPRESENTAN DINERO REAL', $html);
        $this->assertStringContainsString('banda-simulado', $html);
        $this->assertStringContainsString('<meta name="api-token" content="', $html);

        Sanctum::actingAs($admin);
        $rep = $this->getJson('/api/admin/reportes/pagos')->assertOk()
            ->assertJsonPath('simulado', true)
            // antes: data-total="confirmado-monto|comision|neto">Bs 40.00 / 0.00 / 40.00 en el HTML
            ->assertJsonPath('confirmados.monto', '40.00')
            ->assertJsonPath('confirmados.comision', '0.00')
            ->assertJsonPath('confirmados.neto', '40.00');
        // antes: <tr data-estado="confirmado|anulado"> con cantidad 1 y monto 40.00 / 35.50; sin fila «pendiente»
        $porEstado = collect($rep->json('por_estado'))->keyBy('estado');
        $this->assertSame(['anulado', 'confirmado'], $porEstado->keys()->all());
        $this->assertSame([1, '40.00'], [$porEstado['confirmado']['cantidad'], $porEstado['confirmado']['monto']]);
        $this->assertSame([1, '35.50'], [$porEstado['anulado']['cantidad'], $porEstado['anulado']['monto']]);
        // antes: nombres «Carlos Contratador» y «Tania Trabajadora» en el HTML
        $this->assertContains('Carlos Contratador', array_column(array_column($rep->json('filas'), 'contratador'), 'nombre'));
        $this->assertContains('Tania Trabajadora', array_column(array_column($rep->json('filas'), 'trabajador'), 'nombre'));

        // 7. Nada fuera de los pagos de la contratación objetivo se modificó.
        $this->assertSame($antes, $this->instantanea());
        $this->assertSame(0, DB::table('pagos')->where('id_contratacion', $ajena->id_contratacion)->count());
        $this->assertSame(2, DB::table('pagos')->count());
    }
}
