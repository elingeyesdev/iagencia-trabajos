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
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Seguridad de pagos simulados, KPIs y reportes: matriz endpoint × rol,
 * IDOR, asignación masiva, datos sensibles, 404 sin detalles internos y XSS.
 */
class PagosSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private int $siguienteCedula = 9300;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    // ─────────────────────────────── Helpers ───────────────────────────────

    private function usuario(?string $rol, ?string $nombre = null): User
    {
        $cedula = $this->siguienteCedula++;
        $user = User::create([
            'cedula'     => $cedula,
            'nombre'     => $nombre ?? "Usuario Seguridad {$cedula}",
            'email'      => "seguridad{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);
        if ($rol !== null) {
            $user->assignRole($rol);
        }

        return $user;
    }

    /** @return array{contratador: User, trabajador: User, contratacion: Contratacion, pago: Pago} */
    private function escenario(?User $contratador = null, ?User $trabajador = null, string $titulo = 'Trabajo seguro'): array
    {
        $contratador ??= $this->usuario('contratador');
        $trabajador  ??= $this->usuario('trabajador');

        $publicacion = Publicacion::create([
            'tipo' => 'oferta', 'titulo' => $titulo, 'descripcion' => 'Desc', 'pago' => '100.00',
            'lugar' => 'Ciudad', 'fecha_publicacion' => '2026-01-10', 'cedula' => $contratador->cedula,
            'id_categoria' => 1, 'estado' => 1,
        ]);
        $postulacion = Postulacion::create([
            'id_labor' => $publicacion->id_labor, 'cedula' => $trabajador->cedula,
            'fecha_postulacion' => '2026-01-11', 'estado' => 'aceptada',
        ]);
        $contratacion = Contratacion::create([
            'id_postulacion' => $postulacion->id_postulacion, 'fecha_inicio' => '2026-01-15',
            'monto_acordado' => '100.00', 'estado' => 'activa',
        ]);

        $pago = new Pago();
        $pago->forceFill([
            'id_contratacion'    => $contratacion->id_contratacion,
            'monto'              => '10.00',
            'monto_neto'         => '10.00',
            'fecha_pago'         => '2026-02-01',
            'metodo'             => 'efectivo',
            'estado'             => 'pendiente',
            'referencia_externa' => 'SIM-SEG-' . uniqid('', true),
            'cedula_registra'    => $contratador->cedula,
            'fecha_registro'     => now(),
        ])->save();

        return compact('contratador', 'trabajador', 'contratacion', 'pago');
    }

    private function url(string $plantilla, array $e): string
    {
        return strtr($plantilla, [
            '{c}' => $e['contratacion']->id_contratacion,
            '{p}' => $e['pago']->id_pago,
        ]);
    }

    private function cuerpo(string $plantilla): array
    {
        return match (true) {
            str_ends_with($plantilla, '/anular') => ['motivo_anulacion' => 'Motivo de prueba'],
            str_ends_with($plantilla, '/pagos') => ['monto' => '5.00', 'fecha_pago' => '2026-02-01', 'metodo' => 'efectivo'],
            default => [],
        };
    }

    private function assertSinDatosSensibles(string $contenido): void
    {
        $this->assertStringNotContainsString('contrasena', $contenido);
        $this->assertStringNotContainsString('@redwork.test', $contenido);
        $this->assertStringNotContainsString('$2y$', $contenido);
        $this->assertStringNotContainsString('token', strtolower($contenido));
    }

    // ───────────────────────────── Matriz de permisos ─────────────────────────────

    /** [método, URI, rol autorizado] */
    public static function endpoints(): array
    {
        return [
            'contratador GET pagos contratación'  => ['GET', '/api/contratador/contrataciones/{c}/pagos', 'contratador'],
            'contratador POST pagos contratación' => ['POST', '/api/contratador/contrataciones/{c}/pagos', 'contratador'],
            'admin GET pagos'                     => ['GET', '/api/admin/pagos', 'administrador'],
            'admin GET pago'                      => ['GET', '/api/admin/pagos/{p}', 'administrador'],
            'admin PUT confirmar'                 => ['PUT', '/api/admin/pagos/{p}/confirmar', 'administrador'],
            'admin PUT anular'                    => ['PUT', '/api/admin/pagos/{p}/anular', 'administrador'],
            'admin GET pagos contratación'        => ['GET', '/api/admin/contrataciones/{c}/pagos', 'administrador'],
            'admin GET kpis'                      => ['GET', '/api/admin/kpis', 'administrador'],
            // Reportes (API JSON). El de usuarios lista emails por diseño: su 401/403 se prueba en ReportesTest.
            'admin GET reporte publicaciones'     => ['GET', '/api/admin/reportes/publicaciones', 'administrador'],
            'admin GET reporte categorias'        => ['GET', '/api/admin/reportes/categorias', 'administrador'],
            'admin GET reporte actividad'         => ['GET', '/api/admin/reportes/actividad', 'administrador'],
            'admin GET reporte pagos'             => ['GET', '/api/admin/reportes/pagos', 'administrador'],
            'trabajador GET pagos'                => ['GET', '/api/trabajador/pagos', 'trabajador'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_sin_token_recibe_401(string $metodo, string $uri, string $rol): void
    {
        $e = $this->escenario();
        $this->json($metodo, $this->url($uri, $e), $this->cuerpo($uri))->assertUnauthorized();
        $this->assertSame('pendiente', $e['pago']->fresh()->estado);
        $this->assertSame(1, Pago::count());
    }

    #[DataProvider('endpoints')]
    public function test_roles_no_autorizados_y_sin_rol_reciben_403(string $metodo, string $uri, string $rol): void
    {
        $e = $this->escenario();

        foreach (['administrador', 'contratador', 'trabajador', null] as $otro) {
            if ($otro === $rol) {
                continue;
            }
            Sanctum::actingAs($this->usuario($otro));
            $this->json($metodo, $this->url($uri, $e), $this->cuerpo($uri))
                ->assertForbidden();
        }

        $this->assertSame('pendiente', $e['pago']->fresh()->estado);
        $this->assertSame(1, Pago::count());
    }

    #[DataProvider('endpoints')]
    public function test_rol_autorizado_y_propietario_accede_sin_datos_sensibles(string $metodo, string $uri, string $rol): void
    {
        $e = $this->escenario();
        $actor = match ($rol) {
            'contratador' => $e['contratador'],
            'trabajador'  => $e['trabajador'],
            default       => $this->usuario('administrador'),
        };
        Sanctum::actingAs($actor);

        $r = $this->json($metodo, $this->url($uri, $e), $this->cuerpo($uri));
        $this->assertContains($r->status(), [200, 201], $r->getContent());
        $this->assertSinDatosSensibles($r->getContent());
    }

    public function test_ids_no_numericos_no_coinciden_con_las_rutas(): void
    {
        Sanctum::actingAs($this->usuario('administrador'));
        $this->getJson('/api/admin/pagos/abc')->assertNotFound();
        $this->putJson('/api/admin/pagos/1%20OR%201=1/confirmar')->assertNotFound();
        $this->getJson('/api/admin/contrataciones/abc/pagos')->assertNotFound();

        Sanctum::actingAs($this->usuario('contratador'));
        $this->getJson('/api/contratador/contrataciones/abc/pagos')->assertNotFound();
        $this->postJson('/api/contratador/contrataciones/abc/pagos', [])->assertNotFound();
    }

    // ─────────────────────────────────── IDOR ───────────────────────────────────

    public function test_contratador_ajeno_no_lee_ni_registra_pagos(): void
    {
        $e = $this->escenario();
        Sanctum::actingAs($this->usuario('contratador'));

        $this->getJson($this->url('/api/contratador/contrataciones/{c}/pagos', $e))
            ->assertForbidden()->assertExactJson(['message' => 'No autorizado.']);
        $this->postJson($this->url('/api/contratador/contrataciones/{c}/pagos', $e), $this->cuerpo('/pagos'))
            ->assertForbidden()->assertExactJson(['message' => 'No autorizado.']);

        $this->assertSame(1, Pago::count());
    }

    public function test_trabajador_de_la_contratacion_no_usa_endpoints_de_contratador(): void
    {
        $e = $this->escenario();
        Sanctum::actingAs($e['trabajador']);

        $this->getJson($this->url('/api/contratador/contrataciones/{c}/pagos', $e))->assertForbidden();
        $this->postJson($this->url('/api/contratador/contrataciones/{c}/pagos', $e), $this->cuerpo('/pagos'))->assertForbidden();
        $this->getJson($this->url('/api/admin/pagos/{p}', $e))->assertForbidden();
        $this->assertSame(1, Pago::count());
    }

    public function test_trabajador_ajeno_no_ve_pagos_de_otro_trabajador(): void
    {
        $e = $this->escenario();
        Sanctum::actingAs($this->usuario('trabajador'));

        $r = $this->getJson('/api/trabajador/pagos?id_contratacion=' . $e['contratacion']->id_contratacion . '&cedula=' . $e['trabajador']->cedula)
            ->assertOk()
            ->assertJsonPath('data', []);
        $this->assertStringNotContainsString($e['pago']->referencia_externa, $r->getContent());
    }

    public function test_contratador_que_tambien_es_trabajador_solo_ve_pagos_como_trabajador_propios(): void
    {
        $doble = $this->usuario('contratador');
        $doble->assignRole('trabajador');
        $comoContratador = $this->escenario($doble);
        $comoTrabajador  = $this->escenario(null, $doble);

        Sanctum::actingAs($doble);
        $this->getJson('/api/trabajador/pagos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_pago', $comoTrabajador['pago']->id_pago);
        $this->getJson($this->url('/api/contratador/contrataciones/{c}/pagos', $comoTrabajador))->assertForbidden();
        $this->getJson($this->url('/api/contratador/contrataciones/{c}/pagos', $comoContratador))->assertOk();
    }

    // ─────────────────────────────── Asignación masiva ───────────────────────────────

    public function test_registro_ignora_campos_de_control_enviados_por_el_cliente(): void
    {
        $e     = $this->escenario();
        $otra  = $this->escenario();
        Sanctum::actingAs($e['contratador']);

        $r = $this->postJson($this->url('/api/contratador/contrataciones/{c}/pagos', $e), [
            'monto' => '5.00', 'fecha_pago' => '2026-02-01', 'metodo' => 'efectivo',
            'estado' => 'confirmado', 'porcentaje_comision' => '50', 'monto_comision' => '2.50', 'monto_neto' => '1.00',
            'es_simulado' => false, 'proveedor' => 'banco', 'referencia_externa' => 'REAL-1',
            'cedula_registra' => 1, 'cedula_confirma' => 1, 'fecha_confirmacion' => '2026-02-01 00:00:00',
            'id_contratacion' => $otra['contratacion']->id_contratacion, 'id_pago' => 999999,
        ])->assertCreated();

        $fila = (array) DB::table('pagos')->where('id_pago', $r->json('data.id_pago'))->first();
        $this->assertNotSame(999999, (int) $fila['id_pago']);
        $this->assertSame($e['contratacion']->id_contratacion, (int) $fila['id_contratacion']);
        $this->assertSame('pendiente', $fila['estado']);
        $this->assertSame(0.0, (float) $fila['porcentaje_comision']);
        $this->assertSame(0.0, (float) $fila['monto_comision']);
        $this->assertSame(5.0, (float) $fila['monto_neto']);
        $this->assertSame(1, (int) $fila['es_simulado']);
        $this->assertSame('simulado', $fila['proveedor']);
        $this->assertNotSame('REAL-1', $fila['referencia_externa']);
        $this->assertSame($e['contratador']->cedula, (int) $fila['cedula_registra']);
        $this->assertNull($fila['cedula_confirma']);
        $this->assertNull($fila['fecha_confirmacion']);
        $this->assertSame(1, Pago::where('id_contratacion', $otra['contratacion']->id_contratacion)->count());
    }

    public function test_confirmar_ignora_cuerpo_y_registra_al_admin_autenticado(): void
    {
        $e = $this->escenario();
        $admin = $this->usuario('administrador');
        Sanctum::actingAs($admin);

        $this->putJson($this->url('/api/admin/pagos/{p}/confirmar', $e), [
            'cedula_confirma' => 1, 'monto' => '99.00', 'estado' => 'anulado',
        ])->assertOk();

        $pago = $e['pago']->fresh();
        $this->assertSame('confirmado', $pago->estado);
        $this->assertSame('10.00', $pago->monto);
        $this->assertSame($admin->cedula, (int) $pago->cedula_confirma);
    }

    // ───────────────────── 404 sin detalles internos (corrección) ─────────────────────

    public static function rutasInexistentes(): array
    {
        return [
            'admin GET pago'               => ['administrador', 'GET', '/api/admin/pagos/999999'],
            'admin PUT confirmar'          => ['administrador', 'PUT', '/api/admin/pagos/999999/confirmar'],
            'admin PUT anular'             => ['administrador', 'PUT', '/api/admin/pagos/999999/anular'],
            'admin GET pagos contratación' => ['administrador', 'GET', '/api/admin/contrataciones/999999/pagos'],
            'contratador GET pagos'        => ['contratador', 'GET', '/api/contratador/contrataciones/999999/pagos'],
            'contratador POST pagos'       => ['contratador', 'POST', '/api/contratador/contrataciones/999999/pagos'],
        ];
    }

    #[DataProvider('rutasInexistentes')]
    public function test_404_no_revela_modelo_ni_identificador(string $rol, string $metodo, string $uri): void
    {
        Sanctum::actingAs($this->usuario($rol));

        $r = $this->json($metodo, $uri, $this->cuerpo($uri))->assertNotFound();
        $r->assertExactJson(['message' => 'Recurso no encontrado.']);
        $this->assertStringNotContainsString('App\\\\Models', $r->getContent());
        $this->assertStringNotContainsString('999999', $r->getContent());
    }

    // ─────────────────────────────── KPIs y reportes ───────────────────────────────

    public function test_kpis_solo_devuelven_agregados(): void
    {
        $this->escenario();
        Sanctum::actingAs($this->usuario('administrador'));

        $r = $this->getJson('/api/admin/kpis')->assertOk();
        $this->assertEqualsCanonicalizing(
            ['publicaciones_activas', 'contrataciones_activas', 'pagos_confirmados_bs', 'simulado', 'generado_en'],
            array_keys($r->json())
        );
        $this->assertSinDatosSensibles($r->getContent());
    }

    public function test_reportes_escapan_html_y_no_exponen_contrasenas(): void
    {
        $payload = '<script>alert("xss")</script>';
        $contratador = $this->usuario('contratador', $payload);
        $trabajador  = $this->usuario('trabajador', $payload);
        $this->escenario($contratador, $trabajador, $payload);
        $admin = $this->usuario('administrador');
        $admin->forceFill(['email_verified_at' => now()])->save();

        // Páginas imprimibles: plantillas sin datos incrustados → ni el payload ni hashes aparecen.
        $this->withoutVite();
        foreach (['reportes.usuarios', 'reportes.publicaciones', 'reportes.actividad', 'reportes.pagos'] as $ruta) {
            $r = $this->actingAs($admin)->get(route($ruta))->assertOk();
            $html = $r->getContent();
            $this->assertStringNotContainsString($payload, $html, "XSS sin escapar en {$ruta}");
            $this->assertStringNotContainsString('$2y$', $html, "Hash de contraseña expuesto en {$ruta}");
        }

        // API JSON: el dato llega íntegro al decodificar, pero el cuerpo crudo codifica < > " (JSON_HEX_*)
        // y nunca expone hashes. El escapado HTML de presentación lo hace esc() en reportes.js
        // (verificado en tests/js/reportes.test.mjs).
        Sanctum::actingAs($admin);
        $rutas = [
            'usuarios'      => 'filas.*.nombre',
            'publicaciones' => 'filas.*.titulo',
            'actividad'     => 'publicaciones.recientes.*.titulo',
            'pagos'         => 'filas.*.titulo',
        ];
        foreach ($rutas as $tipo => $ruta) {
            $r = $this->getJson("/api/admin/reportes/{$tipo}")->assertOk()->assertHeader('Content-Type', 'application/json');
            $crudo = $r->getContent();
            $this->assertStringNotContainsString($payload, $crudo, "Payload sin codificar en API {$tipo}");
            $this->assertStringNotContainsString('<script', $crudo, "Etiqueta sin codificar en API {$tipo}");
            $this->assertStringNotContainsString('$2y$', $crudo, "Hash de contraseña expuesto en API {$tipo}");
            $this->assertStringNotContainsString('contrasena', $crudo);
            $this->assertContains($payload, data_get($r->json(), $ruta), "Dato alterado en API {$tipo}");
        }
    }

    public function test_reportes_rechazan_filtros_manipulados(): void
    {
        $admin = $this->usuario('administrador');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reportes/usuarios?' . http_build_query(['rol' => "administrador' OR '1'='1"]))
            ->assertStatus(422)->assertJsonValidationErrors('rol');
        $this->getJson('/api/admin/reportes/pagos?' . http_build_query(['estado' => "confirmado' --"]))
            ->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->getJson('/api/admin/reportes/publicaciones?' . http_build_query(['categoria' => '1 OR 1=1']))
            ->assertStatus(422)->assertJsonValidationErrors('categoria');
        $this->getJson('/api/admin/reportes/actividad?' . http_build_query(['desde' => '2026-01-01; DROP TABLE usuarios']))
            ->assertStatus(422)->assertJsonValidationErrors('desde');
        $this->assertSame(1, User::where('cedula', $admin->cedula)->count());
    }
}
