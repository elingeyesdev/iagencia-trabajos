<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Parametro;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

/**
 * Tareas 3.2 + 3.3 — QA del registro de pagos SIMULADOS
 * (POST /api/contratador/contrataciones/{id}/pagos).
 *
 * Complementa RegistrarPagoApiTest y ComisionPagoTest (redwork-api) sin duplicarlos.
 *
 * LIMITACIONES DE SQLITE EN MEMORIA (único entorno de pruebas autorizado):
 *  - SQLiteGrammar::compileLock devuelve '': lockForUpdate() se ignora. Aquí solo se
 *    demuestra que el código SOLICITA el bloqueo dentro de una transacción (gramática de
 *    prueba que emite el comentario /* lock:update *\/), no que MySQL/InnoDB serialice.
 *  - `:memory:` usa una sola conexión: no hay concurrencia real. La "carrera" se simula de
 *    forma secuencial insertando un pago concurrente desde un DB::listen justo después del
 *    SELECT ... FOR UPDATE de la contratación y antes del cálculo del saldo. Como todo
 *    ocurre en la misma conexión, el rollback del código también deshace esa fila.
 *  - RefreshDatabase envuelve cada prueba en una transacción; las del código son SAVEPOINTs.
 */
class PagosRegistroQaTest extends TestCase
{
    use RefreshDatabase;

    private const MARCA_LOCK = 'lock:update';

    private int $siguienteCedula = 9700;

    /** @var array<int, array{sql: string, nivel: int}> */
    private array $consultas = [];

    private int $nivelBase = 0;

    /** ULID fijos para forzar colisiones de referencia ('SIM-' + ULID). */
    private const ULID_DUPLICADO = '01JCZ8Y0000000000000000001';
    private const ULID_NUEVO     = '01JCZ8Y0000000000000000002';

    /** Referencias generadas mientras los ULID están forzados (ver forzarUlids). */
    private int $llamadasUlid = 0;

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
            'nombre'     => "Usuario QA Pago {$cedula}",
            'email'      => "qapago{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);
        if ($rol !== null) {
            $user->assignRole($rol);
        }

        return $user;
    }

    /** @return array{0: User, 1: Contratacion} */
    private function escenario(string $estado = 'activa', $monto = '100.00', ?User $contratador = null): array
    {
        $contratador ??= $this->usuario('contratador');
        $trabajador  = $this->usuario('trabajador');

        $publicacion = Publicacion::create([
            'tipo' => 'oferta', 'titulo' => 'Trabajo QA', 'descripcion' => 'Desc', 'pago' => $monto,
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

    private function url(int|string $id): string
    {
        return "/api/contratador/contrataciones/{$id}/pagos";
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'monto'      => '40.00',
            'fecha_pago' => now()->toDateString(),
            'metodo'     => 'efectivo',
        ], $cambios);
    }

    private function porcentaje(string $valor): void
    {
        Parametro::where('clave', 'comision_porcentaje')->update(['valor' => $valor]);
    }

    /** Inserta un pago existente (p. ej. anulado o con referencia fija) con forceFill. */
    private function pagoExistente(int $idContratacion, string $monto, string $estado = 'pendiente', ?string $ref = null): Pago
    {
        $pago = new Pago();
        $pago->forceFill([
            'id_contratacion'    => $idContratacion,
            'monto'              => $monto,
            'monto_neto'         => $monto,
            'fecha_pago'         => '2026-02-01',
            'metodo'             => 'efectivo',
            'estado'             => $estado,
            'referencia_externa' => $ref ?? 'SIM-PREVIO-' . uniqid(),
            'cedula_registra'    => 1,
            'fecha_registro'     => now(),
        ])->save();

        return $pago;
    }

    /**
     * Fuerza los ULID que usa Pago::generarReferenciaSimulada() ('SIM-' + ULID) con la
     * utilidad nativa Str::createUlidsUsing(): devuelve los ULID en secuencia (el último se
     * repite) o lanza $lanzar. $this->llamadasUlid cuenta las referencias generadas.
     * tearDown() restaura la generación normal con Str::createUlidsNormally().
     */
    private function forzarUlids(array $ulids, ?RuntimeException $lanzar = null): void
    {
        $this->llamadasUlid = 0;

        Str::createUlidsUsing(function () use ($ulids, $lanzar) {
            $this->llamadasUlid++;
            if ($lanzar) {
                throw $lanzar;
            }

            return new Ulid($ulids[min($this->llamadasUlid - 1, count($ulids) - 1)]);
        });
    }

    protected function tearDown(): void
    {
        Str::createUlidsNormally();

        parent::tearDown();
    }

    private function capturarConsultas(): void
    {
        $conexion = DB::connection();
        $conexion->setQueryGrammar(new class($conexion) extends SQLiteGrammar {
            protected function compileLock(Builder $query, $value)
            {
                return $value === true ? '/* lock:update */' : '';
            }
        });

        $this->consultas = [];
        $this->nivelBase = DB::transactionLevel();

        DB::listen(function (QueryExecuted $q) {
            $this->consultas[] = ['sql' => strtolower($q->sql), 'nivel' => DB::transactionLevel()];
        });
    }

    private function consultasSobre(string $tabla, string $verbo): array
    {
        return array_values(array_filter($this->consultas, fn ($c) => str_starts_with($c['sql'], $verbo)
            && str_contains($c['sql'], '"' . $tabla . '"')));
    }

    private function fotoTablasAjenas(): array
    {
        return [
            'usuarios'       => DB::table('usuarios')->orderBy('cedula')->get()->toArray(),
            'publicaciones'  => DB::table('publicaciones')->orderBy('id_labor')->get()->toArray(),
            'postulaciones'  => DB::table('postulaciones')->orderBy('id_postulacion')->get()->toArray(),
            'contrataciones' => DB::table('contrataciones')->orderBy('id_contratacion')->get()->toArray(),
            'parametros'     => DB::table('parametros')->orderBy('id_parametro')->get()->toArray(),
        ];
    }

    // ═══════════════════════ 1. Comisión vía endpoint ═══════════════════════

    public static function comisiones(): array
    {
        return [
            '0% de 40.00'          => ['0.00', '40.00', '0.00', '0.00', '40.00'],
            '10% de 40.00'         => ['10', '40.00', '10.00', '4.00', '36.00'],
            '10% de 0.05 (half-up)' => ['10.00', '0.05', '10.00', '0.01', '0.04'],
            '12.5% de 0.01'        => ['12.50', '0.01', '12.50', '0.00', '0.01'],
            '12.5% de 0.04'        => ['12.50', '0.04', '12.50', '0.01', '0.03'],
            '12.5% de 10.05'       => ['12.50', '10.05', '12.50', '1.26', '8.79'],
            '12.5% de 33.33'       => ['12.5', '33.33', '12.50', '4.17', '29.16'],
            '12.5% de 99.99'       => ['12.50', '99.99', '12.50', '12.50', '87.49'],
            '100% de 1.00'         => ['100', '1.00', '100.00', '1.00', '0.00'],
            'porcentaje con espacios' => [' 10 ', '40', '10.00', '4.00', '36.00'],
        ];
    }

    #[DataProvider('comisiones')]
    public function test_comision_calculada_y_copiada_en_el_pago(string $param, string $monto, string $pct, string $comision, string $neto): void
    {
        $this->porcentaje($param);
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['monto' => $monto]))
            ->assertCreated()
            ->assertJsonPath('data.porcentaje_comision', $pct)
            ->assertJsonPath('data.monto_comision', $comision)
            ->assertJsonPath('data.monto_neto', $neto);

        $pago = Pago::sole();
        $this->assertSame($pct, $pago->porcentaje_comision);
        $this->assertSame($comision, $pago->monto_comision);
        $this->assertSame($neto, $pago->monto_neto);
    }

    public function test_sin_parametro_de_comision_se_aplica_cero(): void
    {
        Parametro::where('clave', 'comision_porcentaje')->delete();
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertCreated()
            ->assertJsonPath('data.porcentaje_comision', '0.00')
            ->assertJsonPath('data.monto_neto', '40.00');
    }

    public function test_cambiar_el_parametro_despues_no_altera_el_pago_historico(): void
    {
        $this->porcentaje('12.50');
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $id1 = $this->postJson($url, $this->datos(['monto' => '20.00']))->assertCreated()->json('data.id_pago');
        $antes = DB::table('pagos')->where('id_pago', $id1)->first();

        $this->porcentaje('20.00');
        $this->postJson($url, $this->datos(['monto' => '20.00']))
            ->assertCreated()
            ->assertJsonPath('data.porcentaje_comision', '20.00')
            ->assertJsonPath('data.monto_comision', '4.00')
            ->assertJsonPath('resumen.pagado_no_anulado', '40.00');

        $this->assertEquals($antes, DB::table('pagos')->where('id_pago', $id1)->first());
        $historico = Pago::find($id1);
        $this->assertSame('12.50', $historico->porcentaje_comision);
        $this->assertSame('2.50', $historico->monto_comision);
        $this->assertSame('17.50', $historico->monto_neto);
    }

    public static function porcentajesInvalidos(): array
    {
        return [
            'texto'          => ['abc'],
            'mayor que 100'  => ['150'],
            '100.01'         => ['100.01'],
            'negativo'       => ['-5'],
            'tres decimales' => ['12.555'],
            'notación e'     => ['1e1'],
            'coma decimal'   => ['12,5'],
            'vacío'          => [''],
        ];
    }

    #[DataProvider('porcentajesInvalidos')]
    public function test_porcentaje_invalido_en_bd_devuelve_503_sin_escritura(string $valor): void
    {
        $this->porcentaje($valor);
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertStatus(503)
            ->assertExactJson(['message' => 'La configuración de comisión no es válida. Contacte al administrador.']);
        $this->assertSame(0, Pago::count());
    }

    public function test_proveedor_no_soportado_devuelve_503_sin_escritura(): void
    {
        Parametro::where('clave', 'pagos_proveedor')->update(['valor' => 'banco']);
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El proveedor de pagos configurado no está soportado.']);
        $this->assertSame(0, Pago::count());
    }

    // ═══════════════════════ 2. Referencias SIM ═══════════════════════

    public function test_varios_pagos_generan_referencias_sim_unicas(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['monto' => '10.00']))->assertCreated();
        }

        $refs = Pago::pluck('referencia_externa')->all();
        $this->assertCount(5, $refs);
        $this->assertCount(5, array_unique($refs));
        foreach ($refs as $ref) {
            $this->assertStringStartsWith('SIM-', $ref);
            $this->assertLessThanOrEqual(100, strlen($ref));
        }
        $this->assertSame(['simulado'], Pago::distinct()->pluck('proveedor')->all());
    }

    public function test_colision_de_referencia_se_reintenta_con_una_nueva(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        $this->pagoExistente($contratacion->id_contratacion, '10.00', 'pendiente', 'SIM-' . self::ULID_DUPLICADO);
        $this->forzarUlids([self::ULID_DUPLICADO, self::ULID_NUEVO]);
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertCreated()
            ->assertJsonPath('data.referencia_externa', 'SIM-' . self::ULID_NUEVO)
            ->assertJsonPath('resumen.pagado_no_anulado', '50.00');

        $this->assertSame(2, $this->llamadasUlid);
        $this->assertSame(2, Pago::count());
        $this->assertSame(1, Pago::where('referencia_externa', 'SIM-' . self::ULID_DUPLICADO)->count());
    }

    public function test_colision_persistente_devuelve_422_controlado_sin_duplicados(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        $this->pagoExistente($contratacion->id_contratacion, '10.00', 'pendiente', 'SIM-' . self::ULID_DUPLICADO);
        $this->forzarUlids([self::ULID_DUPLICADO]);
        Sanctum::actingAs($contratador);
        $nivel = DB::transactionLevel();

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se pudo generar una referencia única para el pago. Intente nuevamente.']);

        $this->assertSame(3, $this->llamadasUlid, 'Se esperaban 3 intentos.');
        $this->assertSame(1, Pago::count());
        $this->assertSame($nivel, DB::transactionLevel(), 'La transacción quedó abierta.');
    }

    // ═══════════════════════ 3. Montos, tope y estados ═══════════════════════

    public function test_pago_minimo_de_un_centavo(): void
    {
        [$contratador, $contratacion] = $this->escenario('activa', '0.01');
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['monto' => '0.01']))
            ->assertCreated()
            ->assertJsonPath('data.monto', '0.01')
            ->assertJsonPath('resumen.saldo_disponible', '0.00');
    }

    public function test_tope_exacto_con_decimales_que_fallarian_en_float(): void
    {
        [$contratador, $contratacion] = $this->escenario('activa', '0.30');
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos(['monto' => '0.10']))->assertCreated();
        $this->postJson($url, $this->datos(['monto' => '0.20']))
            ->assertCreated()
            ->assertJsonPath('resumen.pagado_no_anulado', '0.30')
            ->assertJsonPath('resumen.saldo_disponible', '0.00');

        $this->postJson($url, $this->datos(['monto' => '0.01']))
            ->assertStatus(422)
            ->assertJsonPath('errors.monto.0', 'El monto supera el saldo disponible de la contratación (0.00).');
        $this->assertSame(2, Pago::count());
    }

    public function test_superar_por_un_centavo_informa_el_saldo(): void
    {
        [$contratador, $contratacion] = $this->escenario('activa', '1234.56');
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos(['monto' => '1000.00']))->assertCreated();
        $this->postJson($url, $this->datos(['monto' => '234.57']))
            ->assertStatus(422)
            ->assertJsonPath('errors.monto.0', 'El monto supera el saldo disponible de la contratación (234.56).');
        $this->postJson($url, $this->datos(['monto' => '234.56']))->assertCreated();
        $this->assertSame(2, Pago::count());
    }

    public function test_pago_anulado_libera_saldo_y_confirmado_lo_consume(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        $this->pagoExistente($contratacion->id_contratacion, '70.00', 'anulado');
        $this->pagoExistente($contratacion->id_contratacion, '30.00', 'confirmado');
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos(['monto' => '70.01']))
            ->assertStatus(422)
            ->assertJsonPath('errors.monto.0', 'El monto supera el saldo disponible de la contratación (70.00).');

        $this->postJson($url, $this->datos(['monto' => '70.00']))
            ->assertCreated()
            ->assertJsonPath('resumen.pagado_no_anulado', '100.00')
            ->assertJsonPath('resumen.saldo_disponible', '0.00');
    }

    public static function estadosValidos(): array
    {
        return [['activa'], ['finalizada']];
    }

    #[DataProvider('estadosValidos')]
    public function test_contratacion_activa_o_finalizada_admite_pago(string $estado): void
    {
        [$contratador, $contratacion] = $this->escenario($estado);
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())->assertCreated();
        $this->assertSame(1, Pago::count());
    }

    public function test_estado_desconocido_de_contratacion_devuelve_422(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        DB::table('contrataciones')->where('id_contratacion', $contratacion->id_contratacion)->update(['estado' => 'suspendida']);
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Solo se pueden registrar pagos en contrataciones activas o finalizadas.']);
        $this->assertSame(0, Pago::count());
    }

    public static function montosAcordadosInvalidos(): array
    {
        return [['0'], ['-10.00']];
    }

    #[DataProvider('montosAcordadosInvalidos')]
    public function test_monto_acordado_historico_no_positivo_devuelve_422(string $acordado): void
    {
        [$contratador, $contratacion] = $this->escenario();
        DB::table('contrataciones')->where('id_contratacion', $contratacion->id_contratacion)->update(['monto_acordado' => $acordado]);
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['monto' => '0.01']))
            ->assertStatus(422)
            ->assertExactJson(['message' => 'La contratación no tiene un monto acordado válido mayor que cero.']);
        $this->assertSame(0, Pago::count());
    }

    // ═══════════════════════ 4. Validación del request ═══════════════════════

    public static function datosInvalidos(): array
    {
        return [
            'monto ausente'          => ['monto', null],
            'monto cero'             => ['monto', '0.00'],
            'monto negativo'         => ['monto', '-1'],
            'monto 3 decimales'      => ['monto', '0.001'],
            'monto texto'            => ['monto', 'cien'],
            'monto notación e'       => ['monto', '1e3'],
            'monto coma decimal'     => ['monto', '10,50'],
            'monto excede máximo'    => ['monto', '100000000.00'],
            'monto arreglo'          => ['monto', ['10']],
            'fecha ausente'          => ['fecha_pago', null],
            'fecha inválida'         => ['fecha_pago', '2026-02-30'],
            'fecha texto'            => ['fecha_pago', 'ayer'],
            'fecha formato d/m/Y'    => ['fecha_pago', '01/02/2026'],
            'fecha sin cero'         => ['fecha_pago', '2026-2-1'],
            'fecha con hora'         => ['fecha_pago', '2026-02-01 10:00:00'],
            'método ausente'         => ['metodo', null],
            'método vacío'           => ['metodo', ''],
            'método tarjeta'         => ['metodo', 'tarjeta'],
            'método QR mayúsculas'   => ['metodo', 'QR'],
            'método depósito tilde'  => ['metodo', 'depósito'],
            'método arreglo'         => ['metodo', ['efectivo']],
            'observación 256'        => ['observacion', str_repeat('a', 256)],
            'observación arreglo'    => ['observacion', ['x']],
        ];
    }

    #[DataProvider('datosInvalidos')]
    public function test_dato_invalido_devuelve_422_en_su_campo_sin_escritura(string $campo, $valor): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $datos = $this->datos();
        if ($valor === null) {
            unset($datos[$campo]);
        } else {
            $datos[$campo] = $valor;
        }

        $this->postJson($this->url($contratacion->id_contratacion), $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campo])
            ->assertJsonMissingValidationErrors(array_values(array_diff(['monto', 'fecha_pago', 'metodo', 'observacion'], [$campo])));
        $this->assertSame(0, Pago::count());
    }

    public static function metodosValidos(): array
    {
        return [['efectivo'], ['transferencia'], ['qr'], ['deposito'], ['otro']];
    }

    #[DataProvider('metodosValidos')]
    public function test_cada_metodo_aprobado_es_aceptado(string $metodo): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['metodo' => $metodo]))
            ->assertCreated()
            ->assertJsonPath('data.metodo', $metodo);
    }

    public function test_fecha_futura_rechazada_y_hoy_aceptada_con_reloj_fijo(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(23, 59, 0));
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos(['fecha_pago' => '2026-03-11']))
            ->assertStatus(422)
            ->assertJsonPath('errors.fecha_pago.0', 'La fecha de pago no puede ser posterior a hoy.');

        $this->postJson($url, $this->datos(['fecha_pago' => '2026-03-10']))
            ->assertCreated()
            ->assertJsonPath('data.fecha_pago', '2026-03-10');
        $this->assertSame(1, Pago::count());
    }

    public function test_observacion_de_255_caracteres_y_nula_son_aceptadas(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos(['observacion' => str_repeat('ñ', 255)]))->assertCreated();
        $this->postJson($url, $this->datos(['observacion' => null]))->assertCreated();
        $this->assertSame(2, Pago::count());
    }

    public function test_monto_numerico_json_y_ceros_a_la_izquierda_razonables(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $url = $this->url($contratacion->id_contratacion);

        $this->postJson($url, $this->datos(['monto' => 10.5]))->assertCreated()->assertJsonPath('data.monto', '10.50');
        $this->postJson($url, $this->datos(['monto' => 7]))->assertCreated()->assertJsonPath('data.monto', '7.00');
        $this->postJson($url, $this->datos(['monto' => ' 2.50 ']))->assertCreated()->assertJsonPath('data.monto', '2.50');
        $this->postJson($url, $this->datos(['monto' => '0002.00']))->assertCreated()->assertJsonPath('data.monto', '2.00');
    }

    /**
     * DEFECTO: la regla `decimal:0,2` de Laravel acepta '5.', '.5', '+5' y enteros con más de
     * 12 dígitos por ceros a la izquierda, pero Pago::aCentavos los rechaza con una
     * DomainException que el registro del pago no captura → HTTP 500.
     * Se espera 422 en `monto` (o aceptar el valor normalizado) y nunca 500.
     */
    public static function montosQueRompenACentavos(): array
    {
        return [
            'punto final'             => ['5.'],
            'punto inicial'           => ['.5'],
            'signo más'               => ['+5'],
            'ceros a la izquierda 14' => ['00000000000005'],
        ];
    }

    #[DataProvider('montosQueRompenACentavos')]
    public function test_monto_con_formato_limite_no_debe_provocar_500(string $monto): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $respuesta = $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['monto' => $monto]));

        $this->assertNotSame(500, $respuesta->status(), "monto='{$monto}' provocó HTTP 500.");
        $this->assertContains($respuesta->status(), [201, 422]);
        if ($respuesta->status() === 422) {
            $respuesta->assertJsonValidationErrors(['monto']);
            $this->assertSame(0, Pago::count());
        }
    }

    // ═══════════════════════ 5. Autorización ═══════════════════════

    public function test_usuario_sin_rol_recibe_403_sin_escritura(): void
    {
        [, $contratacion] = $this->escenario();
        Sanctum::actingAs($this->usuario(null));

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())->assertForbidden();
        $this->assertSame(0, Pago::count());
    }

    public function test_contratador_ajeno_403_sin_escritura_ni_cambios(): void
    {
        [, $contratacion] = $this->escenario();
        $foto = $this->fotoTablasAjenas();
        Sanctum::actingAs($this->usuario('contratador'));

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertStatus(403)
            ->assertExactJson(['message' => 'No autorizado.']);
        $this->assertSame(0, Pago::count());
        $this->assertEquals($foto['contrataciones'], $this->fotoTablasAjenas()['contrataciones']);
    }

    public function test_contratacion_sin_postulacion_o_publicacion_devuelve_403(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        $postulacion = Postulacion::find($contratacion->id_postulacion);
        DB::table('publicaciones')->where('id_labor', $postulacion->id_labor)->delete();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())->assertForbidden();
        $this->assertSame(0, Pago::count());
    }

    public static function idsNoNumericos(): array
    {
        return [['abc'], ['1.5'], ['-1'], ['1e3']];
    }

    #[DataProvider('idsNoNumericos')]
    public function test_id_no_numerico_devuelve_404(string $id): void
    {
        $this->escenario();
        Sanctum::actingAs($this->usuario('contratador'));

        $this->postJson($this->url($id), $this->datos())->assertNotFound();
        $this->assertSame(0, Pago::count());
    }

    public function test_contratacion_inexistente_404_sin_escritura(): void
    {
        [$contratador] = $this->escenario();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url(0), $this->datos())->assertNotFound();
        $this->postJson($this->url(424242), $this->datos())->assertNotFound();
        $this->assertSame(0, Pago::count());
    }

    // ═══════════════════════ 6. Campos protegidos ═══════════════════════

    public function test_campos_protegidos_enviados_por_el_cliente_se_ignoran(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        [, $otra] = $this->escenario('activa', '500.00', $contratador);
        $this->porcentaje('10.00');
        Sanctum::actingAs($contratador);

        $respuesta = $this->postJson($this->url($contratacion->id_contratacion), $this->datos([
            'monto'               => '50.00',
            'id_pago'             => 777,
            'id_contratacion'     => $otra->id_contratacion,
            'estado'              => 'confirmado',
            'es_simulado'         => false,
            'proveedor'           => 'banco',
            'referencia_externa'  => 'BANCO-123',
            'porcentaje_comision' => '0.00',
            'monto_comision'      => '0.00',
            'monto_neto'          => '50.00',
            'cedula_registra'     => 1,
            'fecha_registro'      => '2020-01-01 00:00:00',
            'cedula_confirma'     => 1,
            'fecha_confirmacion'  => '2026-01-01 00:00:00',
            'cedula_anula'        => 1,
            'fecha_anulacion'     => '2026-01-01 00:00:00',
            'motivo_anulacion'    => 'manipulado',
        ]));

        $respuesta->assertCreated()
            ->assertJsonPath('data.id_contratacion', $contratacion->id_contratacion)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.es_simulado', true)
            ->assertJsonPath('data.proveedor', 'simulado')
            ->assertJsonPath('data.porcentaje_comision', '10.00')
            ->assertJsonPath('data.monto_comision', '5.00')
            ->assertJsonPath('data.monto_neto', '45.00');

        $pago = Pago::sole();
        $this->assertNotSame(777, (int) $pago->id_pago);
        $this->assertSame($contratacion->id_contratacion, (int) $pago->id_contratacion);
        $this->assertStringStartsWith('SIM-', $pago->referencia_externa);
        $this->assertSame($contratador->cedula, (int) $pago->cedula_registra);
        $this->assertTrue($pago->fecha_registro->isToday());
        $this->assertNull($pago->cedula_confirma);
        $this->assertNull($pago->fecha_confirmacion);
        $this->assertNull($pago->cedula_anula);
        $this->assertNull($pago->fecha_anulacion);
        $this->assertNull($pago->motivo_anulacion);
        $this->assertSame(0, Pago::where('id_contratacion', $otra->id_contratacion)->count());
    }

    public function test_respuesta_tiene_estructura_exacta_sin_datos_sensibles(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);

        $json = $this->postJson($this->url($contratacion->id_contratacion), $this->datos())->assertCreated()->json();

        $this->assertSame(['message', 'simulado', 'data', 'resumen'], array_keys($json));
        $this->assertEqualsCanonicalizing([
            'id_pago', 'id_contratacion', 'monto', 'metodo', 'fecha_pago', 'estado', 'porcentaje_comision',
            'monto_comision', 'monto_neto', 'referencia_externa', 'es_simulado', 'proveedor',
        ], array_keys($json['data']));
        $this->assertSame(['monto_acordado', 'pagado_no_anulado', 'saldo_disponible'], array_keys($json['resumen']));

        $texto = json_encode($json);
        foreach (['contrasena', 'email', 'cedula', 'token', 'postulacion', 'publicacion'] as $sensible) {
            $this->assertStringNotContainsString($sensible, $texto, "La respuesta expone '{$sensible}'.");
        }
    }

    // ═══════════════════════ 7. Sin operaciones reales ═══════════════════════

    public function test_no_hay_http_correo_notificaciones_ni_cambios_en_tablas_ajenas(): void
    {
        Http::fake();
        Mail::fake();
        Notification::fake();
        [$contratador, $contratacion] = $this->escenario();
        $foto = $this->fotoTablasAjenas();
        Sanctum::actingAs($contratador);

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())
            ->assertCreated()
            ->assertJsonPath('simulado', true)
            ->assertJsonPath('message', 'Pago simulado registrado. No se realizó ningún movimiento real de dinero.');

        Http::assertNothingSent();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        $this->assertEquals($foto, $this->fotoTablasAjenas());
    }

    // ═══════════════════════ 8. Fallos y rollback ═══════════════════════

    /**
     * Documentado: un fallo inesperado después del INSERT (aquí un listener `created`)
     * produce HTTP 500 genérico y la transacción se revierte. Se considera aceptable:
     * es un error interno no previsto, sin persistencia parcial.
     */
    public function test_excepcion_tras_el_insert_revierte_el_pago(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Pago::created(function () {
            throw new RuntimeException('Fallo simulado tras el INSERT.');
        });
        Sanctum::actingAs($contratador);
        $nivel = DB::transactionLevel();

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())->assertStatus(500);

        $this->assertSame(0, Pago::count());
        $this->assertSame($nivel, DB::transactionLevel(), 'La transacción quedó abierta.');
    }

    public function test_excepcion_del_procesador_no_persiste_nada(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        $this->forzarUlids([], new RuntimeException('Procesador caído.'));
        Sanctum::actingAs($contratador);
        $nivel = DB::transactionLevel();

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())->assertStatus(500);

        $this->assertSame(0, Pago::count());
        $this->assertSame($nivel, DB::transactionLevel());
    }

    // ═══════════════════════ 9. Bloqueo y carrera simulada ═══════════════════════

    public function test_bloqueo_de_contratacion_suma_e_insert_dentro_de_transaccion(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $this->capturarConsultas();

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos())->assertCreated();

        $bloqueos = array_filter($this->consultasSobre('contrataciones', 'select'),
            fn ($c) => str_contains($c['sql'], self::MARCA_LOCK));
        $this->assertNotEmpty($bloqueos, 'No se solicitó lockForUpdate() sobre contrataciones.');
        foreach ($bloqueos as $c) {
            $this->assertGreaterThan($this->nivelBase, $c['nivel']);
        }

        $sumas = $this->consultasSobre('pagos', 'select');
        $this->assertNotEmpty($sumas);
        $this->assertGreaterThan($this->nivelBase, $sumas[0]['nivel'], 'El saldo se calcula fuera de la transacción.');

        $inserts = $this->consultasSobre('pagos', 'insert');
        $this->assertCount(1, $inserts);
        $this->assertGreaterThan($this->nivelBase, $inserts[0]['nivel']);

        // Orden: bloqueo → suma → insert.
        $orden = array_keys(array_filter($this->consultas, fn ($c) => str_contains($c['sql'], self::MARCA_LOCK)
            || str_starts_with($c['sql'], 'insert into "pagos"')));
        $this->assertStringContainsString(self::MARCA_LOCK, $this->consultas[$orden[0]]['sql']);
        $this->assertSame($this->nivelBase, DB::transactionLevel());
    }

    /** Inserta un pago "concurrente" justo después del SELECT ... FOR UPDATE de la contratación. */
    private function pagoConcurrenteTrasElBloqueo(int $idContratacion, string $monto): void
    {
        $this->capturarConsultas();
        $hecho = false;
        DB::listen(function (QueryExecuted $q) use (&$hecho, $idContratacion, $monto) {
            if (! $hecho && str_contains(strtolower($q->sql), self::MARCA_LOCK)
                && str_contains(strtolower($q->sql), '"contrataciones"')) {
                $hecho = true;
                DB::table('pagos')->insert([
                    'id_contratacion' => $idContratacion, 'monto' => $monto, 'porcentaje_comision' => 0,
                    'monto_comision' => 0, 'monto_neto' => $monto, 'fecha_pago' => '2026-02-01',
                    'metodo' => 'efectivo', 'estado' => 'pendiente', 'es_simulado' => true,
                    'proveedor' => 'simulado', 'referencia_externa' => 'SIM-CONCURRENTE',
                    'cedula_registra' => 1, 'fecha_registro' => now(),
                ]);
            }
        });
    }

    public function test_carrera_simulada_el_tope_considera_el_pago_concurrente(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $this->pagoConcurrenteTrasElBloqueo($contratacion->id_contratacion, '70.00');

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['monto' => '40.00']))
            ->assertStatus(422)
            ->assertJsonPath('errors.monto.0', 'El monto supera el saldo disponible de la contratación (30.00).');

        // El rollback (misma conexión) también deshace la fila concurrente.
        $this->assertSame(0, Pago::where('referencia_externa', '!=', 'SIM-CONCURRENTE')->count(), 'Se registró el pago que superaba el tope.');
    }

    public function test_carrera_simulada_completa_exactamente_el_tope(): void
    {
        [$contratador, $contratacion] = $this->escenario();
        Sanctum::actingAs($contratador);
        $this->pagoConcurrenteTrasElBloqueo($contratacion->id_contratacion, '60.00');

        $this->postJson($this->url($contratacion->id_contratacion), $this->datos(['monto' => '40.00']))
            ->assertCreated()
            ->assertJsonPath('resumen.pagado_no_anulado', '100.00')
            ->assertJsonPath('resumen.saldo_disponible', '0.00');
        $this->assertSame(2, Pago::count());
    }
}
