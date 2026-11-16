<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 3.0 — Batería QA de integridad de Postulaciones y Contrataciones.
 *
 * Complementa (sin duplicar) IntegridadPostulacionContratacionTest:
 *  1. Límites de monto (store) y de pago (aceptar).
 *  2. Postulación aceptada obligatoria para contratar.
 *  3. Propiedad y matriz de autorización (403 / 401 / 404).
 *  4. Duplicados y datos inconsistentes.
 *  5. Eliminaciones prohibidas (admin y trabajador).
 *  6. Transiciones de estado válidas e inválidas.
 *  7. Integridad de relaciones, forma de las respuestas y montos históricos.
 *
 * La parte de transacciones/concurrencia está en PostulacionContratacionTransaccionesQaTest.
 */
class PostulacionContratacionIntegridadQaTest extends TestCase
{
    use RefreshDatabase;

    private const URL_CONTRATACIONES = '/api/contratador/contrataciones';

    private int $siguienteCedula = 7000;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─────────────────────────────── Helpers ───────────────────────────────

    private function crearUsuario(?string $rol): User
    {
        $cedula = $this->siguienteCedula++;

        $user = User::create([
            'cedula'     => $cedula,
            'nombre'     => "Usuario QA {$cedula}",
            'email'      => "qa{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);

        if ($rol !== null) {
            $user->assignRole($rol);
        }

        return $user;
    }

    private function crearPublicacion(User $contratador, $pago = 100): Publicacion
    {
        return Publicacion::create([
            'tipo'              => 'oferta',
            'titulo'            => 'Trabajo QA',
            'descripcion'       => 'Descripción QA',
            'pago'              => $pago,
            'lugar'             => 'Ciudad',
            'fecha_publicacion' => now()->toDateString(),
            'cedula'            => $contratador->cedula,
            'id_categoria'      => 1,
            'estado'            => 1,
        ]);
    }

    private function crearPostulacion(User $contratador, ?User $trabajador = null, $pago = 100, string $estado = 'pendiente'): Postulacion
    {
        $publicacion = $this->crearPublicacion($contratador, $pago);
        $trabajador ??= $this->crearUsuario('trabajador');

        return Postulacion::create([
            'id_labor'          => $publicacion->id_labor,
            'cedula'            => $trabajador->cedula,
            'fecha_postulacion' => now()->toDateString(),
            'estado'            => $estado,
        ]);
    }

    /** Inserta una contratación directamente (datos históricos o inconsistentes). */
    private function insertarContratacion(Postulacion $postulacion, $monto = 100, string $estado = 'activa', ?string $fechaFin = null): int
    {
        return (int) DB::table('contrataciones')->insertGetId([
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => '2026-01-15',
            'fecha_fin'      => $fechaFin,
            'monto_acordado' => $monto,
            'estado'         => $estado,
        ], 'id_contratacion');
    }

    private function urlEstadoPostulacion(Postulacion $p): string
    {
        return "/api/contratador/postulaciones/{$p->id_postulacion}/estado";
    }

    private function urlEstadoContratacion(int $id): string
    {
        return "/api/contratador/contrataciones/{$id}/estado";
    }

    private function payloadStore(Postulacion $p, $monto): array
    {
        return [
            'id_postulacion' => $p->id_postulacion,
            'fecha_inicio'   => '2026-11-07',
            'monto_acordado' => $monto,
        ];
    }

    // ═══════════════════════ 1. Montos y pagos ═══════════════════════

    public static function montosInvalidos(): array
    {
        return [
            'cero como texto'        => ['0', 'El monto acordado debe ser mayor que cero.'],
            'cero decimal'           => [0.00, 'El monto acordado debe ser mayor que cero.'],
            'negativo entero'        => [-5, 'El monto acordado debe ser mayor que cero.'],
            'negativo decimal'       => ['-0.01', 'El monto acordado debe ser mayor que cero.'],
            'tres decimales texto'   => ['0.001', 'El monto acordado admite como máximo 2 decimales.'],
            'tres decimales número'  => [10.125, 'El monto acordado admite como máximo 2 decimales.'],
            'supera máximo'          => [100000000, 'El monto acordado no puede superar 99.999.999,99.'],
            'supera máximo decimal'  => ['100000000.00', 'El monto acordado no puede superar 99.999.999,99.'],
            'texto'                  => ['abc', 'El monto acordado debe ser un número.'],
            'vacío'                  => ['', 'El monto acordado es obligatorio.'],
            'nulo'                   => [null, 'El monto acordado es obligatorio.'],
        ];
    }

    #[DataProvider('montosInvalidos')]
    public function test_store_rechaza_montos_invalidos_con_mensaje_en_espanol($monto, string $mensaje): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, 'aceptada');

        Sanctum::actingAs($contratador);
        $respuesta = $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, $monto))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['monto_acordado']);

        $this->assertContains($mensaje, $respuesta->json('errors.monto_acordado'));
        $this->assertSame(0, Contratacion::count());
    }

    public function test_store_sin_campo_monto_devuelve_mensaje_obligatorio(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, 'aceptada');

        Sanctum::actingAs($contratador);
        $this->postJson(self::URL_CONTRATACIONES, [
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => '2026-11-07',
        ])->assertStatus(422)
          ->assertJsonPath('errors.monto_acordado.0', 'El monto acordado es obligatorio.');

        $this->assertSame(0, Contratacion::count());
    }

    public static function montosValidos(): array
    {
        return [
            'mínimo 0.01 texto'   => ['0.01', 0.01],
            'mínimo 0.01 número'  => [0.01, 0.01],
            'un decimal'          => [12.5, 12.5],
            'entero'              => [250, 250.0],
            'máximo texto'        => ['99999999.99', 99999999.99],
            'máximo número'       => [99999999.99, 99999999.99],
        ];
    }

    #[DataProvider('montosValidos')]
    public function test_store_acepta_montos_limite_validos($monto, float $esperado): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, 'aceptada');

        Sanctum::actingAs($contratador);
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, $monto))
            ->assertCreated()
            ->assertJsonPath('message', 'Contratación registrada.');

        $contratacion = Contratacion::sole();
        $this->assertEqualsWithDelta($esperado, (float) $contratacion->monto_acordado, 0.001);
        $this->assertSame($postulacion->id_postulacion, (int) $contratacion->id_postulacion);
        $this->assertSame('activa', $contratacion->estado);
    }

    public static function pagosNoPositivos(): array
    {
        return [
            'negativo'               => [-10],
            'negativo pequeño'       => [-0.01],
            'redondea a cero'        => [0.004],
        ];
    }

    #[DataProvider('pagosNoPositivos')]
    public function test_aceptar_con_pago_no_positivo_devuelve_422_sin_efectos($pago): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, $pago);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'aceptada'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede aceptar la postulación: el pago de la publicación debe ser mayor que cero.']);

        $this->assertSame('pendiente', $postulacion->fresh()->estado);
        $this->assertSame(0, Contratacion::count());
    }

    public function test_rechazar_con_pago_cero_si_esta_permitido(): void
    {
        // La regla de pago > 0 solo aplica a la aceptación (crea contratación).
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 0);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'rechazada'])->assertOk();

        $this->assertSame('rechazada', $postulacion->fresh()->estado);
        $this->assertSame(0, Contratacion::count());
    }

    public static function pagosPositivos(): array
    {
        return [
            'mínimo'    => [0.01],
            'decimal'   => [1234.56],
            'máximo'    => [99999999.99],
        ];
    }

    #[DataProvider('pagosPositivos')]
    public function test_aceptar_con_pago_positivo_crea_contratacion_con_monto_igual_al_pago($pago): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, $pago);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'aceptada'])->assertOk();

        $this->assertSame('aceptada', $postulacion->fresh()->estado);
        $contratacion = Contratacion::sole();
        $this->assertEqualsWithDelta((float) $pago, (float) $contratacion->monto_acordado, 0.001);
    }

    // ═══════════════ 2. Postulación aceptada obligatoria ═══════════════

    public static function estadosNoAceptados(): array
    {
        return [
            'pendiente' => ['pendiente'],
            'rechazada' => ['rechazada'],
            'cancelada' => ['cancelada'],
        ];
    }

    #[DataProvider('estadosNoAceptados')]
    public function test_store_exige_postulacion_aceptada(string $estado): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, $estado);

        Sanctum::actingAs($contratador);
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, 100))
            ->assertStatus(422)
            ->assertExactJson(['message' => 'La postulación debe estar aceptada antes de contratar.']);

        $this->assertSame(0, Contratacion::count());
        $this->assertSame($estado, $postulacion->fresh()->estado);
    }

    public function test_store_con_postulacion_inexistente_falla_validacion(): void
    {
        Sanctum::actingAs($this->crearUsuario('contratador'));
        $this->postJson(self::URL_CONTRATACIONES, [
            'id_postulacion' => 999999,
            'fecha_inicio'   => '2026-11-07',
            'monto_acordado' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors(['id_postulacion']);

        $this->assertSame(0, Contratacion::count());
    }

    // ═══════════════ 3. Propiedad y autorización ═══════════════

    public static function accionesAjenas(): array
    {
        return [
            'aceptar'  => ['aceptada'],
            'rechazar' => ['rechazada'],
        ];
    }

    #[DataProvider('accionesAjenas')]
    public function test_contratador_ajeno_no_puede_aceptar_ni_rechazar(string $estado): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'));

        Sanctum::actingAs($this->crearUsuario('contratador'));
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => $estado])
            ->assertStatus(403)
            ->assertExactJson(['message' => 'No autorizado.']);

        $this->assertSame('pendiente', $postulacion->fresh()->estado);
        $this->assertSame(0, Contratacion::count());
    }

    public function test_contratador_ajeno_no_puede_crear_contratacion(): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), null, 100, 'aceptada');

        Sanctum::actingAs($this->crearUsuario('contratador'));
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, 100))
            ->assertStatus(403)
            ->assertExactJson(['message' => 'No autorizado.']);

        $this->assertSame(0, Contratacion::count());
    }

    public function test_contratador_ajeno_no_cambia_estado_de_contratacion_y_no_altera_fecha_fin(): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), null, 100, 'aceptada');
        $id = $this->insertarContratacion($postulacion);

        Sanctum::actingAs($this->crearUsuario('contratador'));
        foreach (['finalizada', 'cancelada'] as $estado) {
            $this->putJson($this->urlEstadoContratacion($id), ['estado' => $estado])
                ->assertStatus(403)
                ->assertExactJson(['message' => 'No autorizado.']);
        }

        $fila = DB::table('contrataciones')->where('id_contratacion', $id)->first();
        $this->assertSame('activa', $fila->estado);
        $this->assertNull($fila->fecha_fin);
    }

    public static function rolesNoContratador(): array
    {
        return [
            'trabajador'    => ['trabajador'],
            'administrador' => ['administrador'],
            'sin rol'       => [null],
        ];
    }

    #[DataProvider('rolesNoContratador')]
    public function test_roles_no_contratador_reciben_403_en_rutas_de_contratador(?string $rol): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), null, 100, 'aceptada');
        $id = $this->insertarContratacion($postulacion);

        Sanctum::actingAs($this->crearUsuario($rol));
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'rechazada'])->assertStatus(403);
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, 100))->assertStatus(403);
        $this->putJson($this->urlEstadoContratacion($id), ['estado' => 'finalizada'])->assertStatus(403);

        $this->assertSame('aceptada', $postulacion->fresh()->estado);
        $this->assertSame(1, Contratacion::count());
        $this->assertSame('activa', DB::table('contrataciones')->value('estado'));
    }

    public function test_sin_token_reciben_401_todas_las_rutas_de_la_tarea(): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), null, 100, 'aceptada');
        $id = $this->insertarContratacion($postulacion);

        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'rechazada'])->assertStatus(401);
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, 100))->assertStatus(401);
        $this->putJson($this->urlEstadoContratacion($id), ['estado' => 'finalizada'])->assertStatus(401);
        $this->deleteJson("/api/admin/postulaciones/{$postulacion->id_postulacion}")->assertStatus(401);
        $this->deleteJson("/api/admin/contrataciones/{$id}")->assertStatus(401);
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")->assertStatus(401);

        $this->assertNotNull($postulacion->fresh());
        $this->assertSame(1, Contratacion::count());
    }

    public function test_recursos_inexistentes_devuelven_404(): void
    {
        Sanctum::actingAs($this->crearUsuario('contratador'));
        $this->putJson('/api/contratador/postulaciones/999999/estado', ['estado' => 'aceptada'])->assertNotFound();
        $this->putJson($this->urlEstadoContratacion(999999), ['estado' => 'finalizada'])->assertNotFound();

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson('/api/admin/postulaciones/999999')->assertNotFound();
        $this->deleteJson('/api/admin/contrataciones/999999')->assertNotFound();
    }

    // ═══════════════ 4. Duplicados e inconsistencias ═══════════════

    public function test_aceptar_dos_veces_devuelve_422_y_mantiene_una_sola_contratacion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'aceptada'])->assertOk();
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'aceptada'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya fue procesada.']);

        $this->assertSame(1, Contratacion::where('id_postulacion', $postulacion->id_postulacion)->count());
    }

    public function test_store_tras_aceptar_devuelve_422_no_500_y_no_crea_otra(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 300);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'aceptada'])->assertOk();
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, 500))
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya tiene una contratación registrada.']);

        $contratacion = Contratacion::sole();
        $this->assertEqualsWithDelta(300.0, (float) $contratacion->monto_acordado, 0.001);
    }

    public function test_store_con_contratacion_preexistente_insertada_por_bd_devuelve_422(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, 'aceptada');
        $this->insertarContratacion($postulacion, 100);

        Sanctum::actingAs($contratador);
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, 100))
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya tiene una contratación registrada.']);

        $this->assertSame(1, Contratacion::count());
    }

    public function test_postulacion_pendiente_con_contratacion_inconsistente_no_se_acepta(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, 'pendiente');
        $this->insertarContratacion($postulacion, 77);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'aceptada'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya tiene una contratación registrada.']);

        $this->assertSame('pendiente', $postulacion->fresh()->estado);
        $this->assertSame(1, Contratacion::count());
        $this->assertEqualsWithDelta(77.0, (float) Contratacion::sole()->monto_acordado, 0.001);
    }

    // ═══════════════ 5. Eliminaciones prohibidas ═══════════════

    public function test_admin_no_borra_postulacion_con_contratacion_finalizada_ni_la_contratacion(): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), null, 100, 'aceptada');
        $id = $this->insertarContratacion($postulacion, 100, 'finalizada', '2026-02-01');

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson("/api/admin/postulaciones/{$postulacion->id_postulacion}")
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede eliminar una postulación vinculada a una contratación.']);

        $this->assertNotNull($postulacion->fresh());
        $this->assertTrue(DB::table('contrataciones')->where('id_contratacion', $id)->exists());
    }

    public function test_admin_no_borra_contratacion_con_pagos_y_si_borra_otra_sin_pagos(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $conPagos = $this->insertarContratacion($this->crearPostulacion($contratador, null, 100, 'aceptada'));
        $sinPagos = $this->insertarContratacion($this->crearPostulacion($contratador, null, 100, 'aceptada'));

        // Pago válido en la tabla real `pagos` (Tarea 3.1).
        (new Pago())->forceFill([
            'id_contratacion'    => $conPagos,
            'monto'              => 10,
            'monto_neto'         => 10,
            'fecha_pago'         => '2026-01-20',
            'metodo'             => 'transferencia',
            'referencia_externa' => 'SIM-QA-0001',
            'cedula_registra'    => $contratador->cedula,
            'fecha_registro'     => now(),
        ])->save();

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson("/api/admin/contrataciones/{$conPagos}")
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede eliminar una contratación con pagos registrados.']);
        $this->deleteJson("/api/admin/contrataciones/{$sinPagos}")
            ->assertOk()
            ->assertExactJson(['message' => 'Contratación eliminada.']);

        $this->assertTrue(DB::table('contrataciones')->where('id_contratacion', $conPagos)->exists());
        $this->assertFalse(DB::table('contrataciones')->where('id_contratacion', $sinPagos)->exists());
        $this->assertSame(1, DB::table('pagos')->count());
    }

    public function test_sin_pagos_el_admin_borra_contratacion(): void
    {
        // La tabla `pagos` ya existe por migración (Tarea 3.1); el caso es "sin pagos".
        $this->assertTrue(Schema::hasTable('pagos'));
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), null, 100, 'aceptada');
        $id = $this->insertarContratacion($postulacion);
        $this->assertSame(0, DB::table('pagos')->where('id_contratacion', $id)->count());

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson("/api/admin/contrataciones/{$id}")->assertOk();

        $this->assertFalse(DB::table('contrataciones')->where('id_contratacion', $id)->exists());
        // La postulación no se toca al borrar la contratación.
        $this->assertSame('aceptada', $postulacion->fresh()->estado);
    }

    public static function estadosNoCancelables(): array
    {
        return [
            'aceptada'  => ['aceptada'],
            'rechazada' => ['rechazada'],
        ];
    }

    #[DataProvider('estadosNoCancelables')]
    public function test_trabajador_no_cancela_postulacion_procesada(string $estado): void
    {
        $trabajador = $this->crearUsuario('trabajador');
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), $trabajador, 100, $estado);

        Sanctum::actingAs($trabajador);
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Solo puedes cancelar postulaciones pendientes.']);

        $this->assertSame($estado, $postulacion->fresh()->estado);
    }

    public function test_trabajador_no_cancela_postulacion_aceptada_con_contratacion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $trabajador = $this->crearUsuario('trabajador');
        $postulacion = $this->crearPostulacion($contratador, $trabajador);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'aceptada'])->assertOk();

        Sanctum::actingAs($trabajador);
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")->assertStatus(422);

        $this->assertNotNull($postulacion->fresh());
        $this->assertSame(1, Contratacion::count());
    }

    public function test_trabajador_no_cancela_pendiente_con_contratacion_inconsistente(): void
    {
        $trabajador = $this->crearUsuario('trabajador');
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), $trabajador);
        $this->insertarContratacion($postulacion);

        Sanctum::actingAs($trabajador);
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'No se puede cancelar una postulación vinculada a una contratación.']);

        $this->assertNotNull($postulacion->fresh());
        $this->assertSame(1, Contratacion::count());
    }

    public function test_trabajador_cancela_pendiente_sin_contratacion(): void
    {
        $trabajador = $this->crearUsuario('trabajador');
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), $trabajador);

        Sanctum::actingAs($trabajador);
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Postulación cancelada correctamente.']);

        $this->assertNull($postulacion->fresh());
    }

    public function test_trabajador_no_cancela_postulacion_ajena(): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'));

        Sanctum::actingAs($this->crearUsuario('trabajador'));
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Postulación no encontrada o sin permisos.']);

        $this->assertNotNull($postulacion->fresh());
    }

    public static function rolesNoAdmin(): array
    {
        return [
            'contratador' => ['contratador'],
            'trabajador'  => ['trabajador'],
            'sin rol'     => [null],
        ];
    }

    #[DataProvider('rolesNoAdmin')]
    public function test_no_admin_no_puede_usar_delete_de_admin(?string $rol): void
    {
        $contratador = $this->crearUsuario('contratador');
        $sinContratacion = $this->crearPostulacion($contratador);
        $id = $this->insertarContratacion($this->crearPostulacion($contratador, null, 100, 'aceptada'));

        Sanctum::actingAs($this->crearUsuario($rol));
        $this->deleteJson("/api/admin/postulaciones/{$sinContratacion->id_postulacion}")->assertStatus(403);
        $this->deleteJson("/api/admin/contrataciones/{$id}")->assertStatus(403);

        $this->assertNotNull($sinContratacion->fresh());
        $this->assertTrue(DB::table('contrataciones')->where('id_contratacion', $id)->exists());
    }

    public function test_contratador_no_puede_usar_cancelar_de_trabajador(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);

        Sanctum::actingAs($contratador);
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")->assertStatus(403);

        $this->assertNotNull($postulacion->fresh());
    }

    // ═══════════════ 6. Transiciones de estado ═══════════════

    public function test_transicion_pendiente_a_rechazada_no_crea_contratacion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'rechazada'])
            ->assertOk()
            ->assertJsonPath('message', 'Postulación rechazada.')
            ->assertJsonPath('data.estado', 'rechazada')
            ->assertJsonPath('data.contratacion', null);

        $this->assertSame(0, Contratacion::count());
    }

    public static function transicionesPostulacionInvalidas(): array
    {
        return [
            'aceptada → rechazada' => ['aceptada', 'rechazada'],
            'aceptada → aceptada'  => ['aceptada', 'aceptada'],
            'rechazada → aceptada' => ['rechazada', 'aceptada'],
            'rechazada → rechazada'=> ['rechazada', 'rechazada'],
            'cancelada → aceptada' => ['cancelada', 'aceptada'],
        ];
    }

    #[DataProvider('transicionesPostulacionInvalidas')]
    public function test_transiciones_invalidas_de_postulacion_devuelven_422_sin_cambios(string $origen, string $destino): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, $origen);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => $destino])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya fue procesada.']);

        $this->assertSame($origen, $postulacion->fresh()->estado);
        $this->assertSame(0, Contratacion::count());
    }

    public static function estadosPostulacionNoPermitidos(): array
    {
        return [
            'cancelada' => ['cancelada'],
            'pendiente' => ['pendiente'],
            'foo'       => ['foo'],
            'vacío'     => [''],
            'nulo'      => [null],
        ];
    }

    #[DataProvider('estadosPostulacionNoPermitidos')]
    public function test_estado_de_postulacion_no_permitido_falla_validacion($estado): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => $estado])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);

        $this->assertSame('pendiente', $postulacion->fresh()->estado);
        $this->assertSame(0, Contratacion::count());
    }

    public static function cierresValidos(): array
    {
        return [
            'activa → finalizada' => ['finalizada'],
            'activa → cancelada'  => ['cancelada'],
        ];
    }

    #[DataProvider('cierresValidos')]
    public function test_contratacion_activa_se_cierra_con_fecha_fin_de_hoy(string $destino): void
    {
        Carbon::setTestNow('2026-11-07 10:30:00');
        $contratador = $this->crearUsuario('contratador');
        $id = $this->insertarContratacion($this->crearPostulacion($contratador, null, 100, 'aceptada'));

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoContratacion($id), ['estado' => $destino])
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => ['id_contratacion', 'id_postulacion', 'fecha_inicio', 'fecha_fin', 'monto_acordado', 'estado']])
            ->assertJsonPath('message', 'Contratación actualizada.')
            ->assertJsonPath('data.estado', $destino)
            ->assertJsonPath('data.fecha_fin', '2026-11-07');

        $fila = DB::table('contrataciones')->where('id_contratacion', $id)->first();
        $this->assertSame($destino, $fila->estado);
        $this->assertSame('2026-11-07', $fila->fecha_fin);
        $this->assertSame('2026-01-15', $fila->fecha_inicio);
    }

    public static function transicionesContratacionInvalidas(): array
    {
        return [
            'finalizada → cancelada'  => ['finalizada', 'cancelada'],
            'finalizada → finalizada' => ['finalizada', 'finalizada'],
            'cancelada → finalizada'  => ['cancelada', 'finalizada'],
            'cancelada → cancelada'   => ['cancelada', 'cancelada'],
        ];
    }

    #[DataProvider('transicionesContratacionInvalidas')]
    public function test_transiciones_invalidas_de_contratacion_no_alteran_estado_ni_fecha_fin(string $origen, string $destino): void
    {
        Carbon::setTestNow('2026-11-07 10:30:00');
        $contratador = $this->crearUsuario('contratador');
        $id = $this->insertarContratacion($this->crearPostulacion($contratador, null, 100, 'aceptada'), 100, $origen, '2026-03-01');

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoContratacion($id), ['estado' => $destino])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Solo una contratación activa puede cambiar de estado.']);

        $fila = DB::table('contrataciones')->where('id_contratacion', $id)->first();
        $this->assertSame($origen, $fila->estado);
        $this->assertSame('2026-03-01', $fila->fecha_fin);
    }

    public static function estadosContratacionNoPermitidos(): array
    {
        return [
            'activa' => ['activa'],
            'foo'    => ['foo'],
            'vacío'  => [''],
            'nulo'   => [null],
        ];
    }

    #[DataProvider('estadosContratacionNoPermitidos')]
    public function test_estado_de_contratacion_no_permitido_falla_validacion($estado): void
    {
        $contratador = $this->crearUsuario('contratador');
        $id = $this->insertarContratacion($this->crearPostulacion($contratador, null, 100, 'aceptada'), 100, 'finalizada', '2026-03-01');

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoContratacion($id), ['estado' => $estado])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);

        $fila = DB::table('contrataciones')->where('id_contratacion', $id)->first();
        $this->assertSame('finalizada', $fila->estado);
        $this->assertSame('2026-03-01', $fila->fecha_fin);
    }

    // ═══════ 7. Integridad de relaciones, forma de respuestas, históricos ═══════

    public function test_aceptar_crea_contratacion_vinculada_activa_con_fecha_de_hoy_y_respuesta_completa(): void
    {
        Carbon::setTestNow('2026-11-07 23:59:00');
        $contratador = $this->crearUsuario('contratador');
        $otra = $this->crearPostulacion($contratador, null, 50);
        $postulacion = $this->crearPostulacion($contratador, null, 845.75);

        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoPostulacion($postulacion), ['estado' => 'aceptada'])
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id_postulacion', 'id_labor', 'cedula', 'estado', 'fecha_postulacion',
                    'contratacion' => ['id_contratacion', 'id_postulacion', 'fecha_inicio', 'monto_acordado', 'estado'],
                ],
            ])
            ->assertJsonPath('message', 'Postulación aceptada.')
            ->assertJsonPath('data.estado', 'aceptada')
            ->assertJsonPath('data.contratacion.estado', 'activa')
            ->assertJsonPath('data.contratacion.fecha_inicio', '2026-11-07')
            ->assertJsonPath('data.contratacion.id_postulacion', $postulacion->id_postulacion);

        $contratacion = Contratacion::sole();
        $this->assertSame($postulacion->id_postulacion, (int) $contratacion->id_postulacion);
        $this->assertSame('activa', $contratacion->estado);
        $this->assertSame('2026-11-07', $contratacion->fecha_inicio);
        $this->assertNull($contratacion->fecha_fin);
        $this->assertEqualsWithDelta(845.75, (float) $contratacion->monto_acordado, 0.001);
        // La otra postulación del mismo contratador no se ve afectada.
        $this->assertSame('pendiente', $otra->fresh()->estado);
    }

    public function test_store_respuesta_201_con_message_y_data_con_trabajador(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $trabajador = $this->crearUsuario('trabajador');
        $postulacion = $this->crearPostulacion($contratador, $trabajador, 100, 'aceptada');

        Sanctum::actingAs($contratador);
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($postulacion, '1500.50'))
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id_contratacion', 'id_postulacion', 'fecha_inicio', 'monto_acordado', 'estado',
                    'postulacion' => ['id_postulacion', 'trabajador' => ['cedula', 'nombre']],
                ],
            ])
            ->assertJsonPath('message', 'Contratación registrada.')
            ->assertJsonPath('data.estado', 'activa')
            ->assertJsonPath('data.fecha_inicio', '2026-11-07')
            ->assertJsonPath('data.postulacion.trabajador.cedula', $trabajador->cedula)
            ->assertJsonMissingPath('data.postulacion.trabajador.contrasena');

        $this->assertEqualsWithDelta(1500.50, (float) Contratacion::sole()->monto_acordado, 0.001);
    }

    public function test_monto_historico_cero_no_se_altera_y_su_estado_puede_cambiar(): void
    {
        Carbon::setTestNow('2026-11-07 09:00:00');
        $contratador = $this->crearUsuario('contratador');
        $historica = $this->crearPostulacion($contratador, null, 0, 'aceptada');
        $idHistorica = $this->insertarContratacion($historica, 0);

        Sanctum::actingAs($contratador);

        // Operaciones de la tarea sobre otros registros.
        $p1 = $this->crearPostulacion($contratador, null, 200);
        $this->putJson($this->urlEstadoPostulacion($p1), ['estado' => 'aceptada'])->assertOk();
        $p2 = $this->crearPostulacion($contratador);
        $this->putJson($this->urlEstadoPostulacion($p2), ['estado' => 'rechazada'])->assertOk();
        $p3 = $this->crearPostulacion($contratador, null, 100, 'aceptada');
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($p3, 90))->assertCreated();

        // Intento de duplicar la histórica (con monto válido) → 422 y no se toca.
        $this->postJson(self::URL_CONTRATACIONES, $this->payloadStore($historica, 50))->assertStatus(422);

        // Admin borra una contratación ajena a la histórica.
        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson('/api/admin/contrataciones/' . Contratacion::where('id_postulacion', $p3->id_postulacion)->value('id_contratacion'))
            ->assertOk();

        $this->assertEqualsWithDelta(0.0, (float) DB::table('contrataciones')->where('id_contratacion', $idHistorica)->value('monto_acordado'), 0.0001);

        // Cambiar el estado de la histórica sigue funcionando y no recalcula el monto.
        Sanctum::actingAs($contratador);
        $this->putJson($this->urlEstadoContratacion($idHistorica), ['estado' => 'finalizada'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'finalizada');

        $fila = DB::table('contrataciones')->where('id_contratacion', $idHistorica)->first();
        $this->assertSame('finalizada', $fila->estado);
        $this->assertSame('2026-11-07', $fila->fecha_fin);
        $this->assertEqualsWithDelta(0.0, (float) $fila->monto_acordado, 0.0001);
        $this->assertSame('aceptada', $historica->fresh()->estado);
    }
}
