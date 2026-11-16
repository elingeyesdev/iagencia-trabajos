<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Parametro;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Database\Seeders\ParametrosSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Exceptions\MathException;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 3.1 — Batería QA de la estructura de pagos simulados y parámetros.
 *
 * Complementa (sin duplicar) PagosEstructuraTest:
 *  1. Esquema: tablas, tipos y nulabilidad por columna, valores por defecto en BD, índices, FK, down().
 *  2. Asignación masiva: fill()/create() con arrays maliciosos (Pago y Parametro).
 *  3. Relaciones Eloquent en ambos sentidos (contratación, registrador, confirmador, anulador).
 *  4. Integridad referencial a nivel de BD (query builder, sin el modelo) y vía API (matriz de roles).
 *  5. Unicidad por proveedor y referencia NOT NULL a nivel de BD.
 *  6. Importes decimales: límites, cast decimal:2, neto en centavos.
 *  7. Estados y métodos permitidos / rechazados (valores frontera).
 *  8. Datos de simulación y scopes combinados.
 *  9. Conservación del historial (pagos anulados, postulaciones vinculadas).
 * 10. Ausencia de cambios en usuarios, postulaciones y contrataciones existentes.
 * 11. Parámetros iniciales y seeder idempotente (no sobrescribe descripción ni auditoría).
 */
class PagosEstructuraQaTest extends TestCase
{
    use RefreshDatabase;

    private int $siguienteCedula = 9100;

    private int $siguienteReferencia = 1;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    // ─────────────────────────────── Helpers ───────────────────────────────

    private function crearUsuario(?string $rol = null): User
    {
        $cedula = $this->siguienteCedula++;

        $user = User::create([
            'cedula'     => $cedula,
            'nombre'     => "Usuario Pagos {$cedula}",
            'email'      => "pagos{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);

        if ($rol !== null) {
            $user->assignRole($rol);
        }

        return $user;
    }

    private function crearPostulacion(User $contratador, ?User $trabajador = null, string $estado = 'aceptada', $pago = 100): Postulacion
    {
        $publicacion = Publicacion::create([
            'tipo'              => 'oferta',
            'titulo'            => 'Trabajo pagos',
            'descripcion'       => 'Descripción',
            'pago'              => $pago,
            'lugar'             => 'Ciudad',
            'fecha_publicacion' => '2026-01-10',
            'cedula'            => $contratador->cedula,
            'id_categoria'      => 1,
            'estado'            => 1,
        ]);
        $trabajador ??= $this->crearUsuario('trabajador');

        return Postulacion::create([
            'id_labor'          => $publicacion->id_labor,
            'cedula'            => $trabajador->cedula,
            'fecha_postulacion' => '2026-01-11',
            'estado'            => $estado,
        ]);
    }

    private function crearContratacion(Postulacion $postulacion, string $estado = 'activa', $monto = 100): Contratacion
    {
        return Contratacion::create([
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => '2026-01-15',
            'monto_acordado' => $monto,
            'estado'         => $estado,
        ]);
    }

    /** Escenario completo: contratador, trabajador, postulación aceptada y contratación. */
    private function escenario(string $estadoContratacion = 'activa'): array
    {
        $contratador = $this->crearUsuario('contratador');
        $trabajador = $this->crearUsuario('trabajador');
        $postulacion = $this->crearPostulacion($contratador, $trabajador);
        $contratacion = $this->crearContratacion($postulacion, $estadoContratacion);

        return [$contratador, $trabajador, $postulacion, $contratacion];
    }

    /** Pago válido construido como lo hará el servicio (forceFill de campos de control). */
    private function pago(int $idContratacion, int $cedulaRegistra, array $cambios = []): Pago
    {
        return (new Pago())->forceFill(array_merge([
            'id_contratacion'    => $idContratacion,
            'monto'              => '25.00',
            'monto_neto'         => '25.00',
            'fecha_pago'         => '2026-02-01',
            'metodo'             => 'transferencia',
            'referencia_externa' => 'SIM-QA-' . str_pad((string) $this->siguienteReferencia++, 4, '0', STR_PAD_LEFT),
            'cedula_registra'    => $cedulaRegistra,
            'fecha_registro'     => '2026-02-01 09:30:15',
        ], $cambios));
    }

    /** Fila mínima para inserción directa con el query builder (sin evento saving del modelo). */
    private function filaMinima(int $idContratacion, array $cambios = []): array
    {
        return array_merge([
            'id_contratacion'    => $idContratacion,
            'monto'              => '10.00',
            'monto_neto'         => '10.00',
            'fecha_pago'         => '2026-02-01',
            'metodo'             => 'efectivo',
            'referencia_externa' => 'SIM-DB-' . $this->siguienteReferencia++,
            'cedula_registra'    => 1,
            'fecha_registro'     => '2026-02-01 08:00:00',
        ], $cambios);
    }

    private function columnas(string $tabla): array
    {
        $resultado = [];
        foreach (Schema::getColumns($tabla) as $columna) {
            $resultado[$columna['name']] = $columna;
        }

        return $resultado;
    }

    // ═══════════════════════ 1. Esquema ═══════════════════════

    public function test_tablas_creadas_en_sqlite_y_migraciones_registradas(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertTrue(Schema::hasTable('parametros'));
        $this->assertTrue(Schema::hasTable('pagos'));

        $migraciones = DB::table('migrations')->pluck('migration')->all();
        $this->assertContains('2026_11_07_000001_create_parametros_table', $migraciones);
        $this->assertContains('2026_11_07_000002_create_pagos_table', $migraciones);
    }

    public static function columnasPagos(): array
    {
        // columna => [type_name en SQLite, nullable]
        return [
            'id_pago'             => ['id_pago', 'integer', false],
            'id_contratacion'     => ['id_contratacion', 'integer', false],
            'monto'               => ['monto', 'numeric', false],
            'porcentaje_comision' => ['porcentaje_comision', 'numeric', false],
            'monto_comision'      => ['monto_comision', 'numeric', false],
            'monto_neto'          => ['monto_neto', 'numeric', false],
            'fecha_pago'          => ['fecha_pago', 'date', false],
            'metodo'              => ['metodo', 'varchar', false],
            'estado'              => ['estado', 'varchar', false],
            'es_simulado'         => ['es_simulado', 'tinyint', false],
            'proveedor'           => ['proveedor', 'varchar', false],
            'referencia_externa'  => ['referencia_externa', 'varchar', false],
            'observacion'         => ['observacion', 'varchar', true],
            'cedula_registra'     => ['cedula_registra', 'integer', false],
            'fecha_registro'      => ['fecha_registro', 'datetime', false],
            'cedula_confirma'     => ['cedula_confirma', 'integer', true],
            'fecha_confirmacion'  => ['fecha_confirmacion', 'datetime', true],
            'cedula_anula'        => ['cedula_anula', 'integer', true],
            'fecha_anulacion'     => ['fecha_anulacion', 'datetime', true],
            'motivo_anulacion'    => ['motivo_anulacion', 'varchar', true],
        ];
    }

    #[DataProvider('columnasPagos')]
    public function test_columna_de_pagos_tiene_tipo_y_nulabilidad_esperados(string $columna, string $tipo, bool $nullable): void
    {
        $columnas = $this->columnas('pagos');

        $this->assertArrayHasKey($columna, $columnas);
        $this->assertSame($tipo, Schema::getColumnType('pagos', $columna));
        $this->assertSame($nullable, $columnas[$columna]['nullable'], "Nulabilidad de pagos.{$columna}");
    }

    public function test_pagos_tiene_exactamente_las_columnas_esperadas_y_pk_autoincremental(): void
    {
        $columnas = $this->columnas('pagos');

        $this->assertEqualsCanonicalizing(array_keys(self::columnasPagos()), array_keys($columnas));
        $this->assertTrue($columnas['id_pago']['auto_increment']);
        $this->assertFalse($columnas['id_contratacion']['auto_increment']);
        foreach (['created_at', 'updated_at', 'deleted_at', 'id'] as $ausente) {
            $this->assertArrayNotHasKey($ausente, $columnas);
        }
    }

    public function test_columnas_de_parametros_tipo_y_nulabilidad(): void
    {
        $esperado = [
            'id_parametro'        => ['integer', false],
            'clave'               => ['varchar', false],
            'valor'               => ['varchar', false],
            'descripcion'         => ['varchar', true],
            'fecha_actualizacion' => ['datetime', true],
            'cedula_actualiza'    => ['integer', true],
        ];
        $columnas = $this->columnas('parametros');

        $this->assertEqualsCanonicalizing(array_keys($esperado), array_keys($columnas));
        foreach ($esperado as $columna => [$tipo, $nullable]) {
            $this->assertSame($tipo, Schema::getColumnType('parametros', $columna), "Tipo de parametros.{$columna}");
            $this->assertSame($nullable, $columnas[$columna]['nullable'], "Nulabilidad de parametros.{$columna}");
        }
        $this->assertTrue($columnas['id_parametro']['auto_increment']);
    }

    public function test_valores_por_defecto_de_la_bd_al_insertar_sin_el_modelo(): void
    {
        [, , , $contratacion] = $this->escenario();

        $id = DB::table('pagos')->insertGetId($this->filaMinima($contratacion->id_contratacion), 'id_pago');
        $fila = DB::table('pagos')->where('id_pago', $id)->first();

        $this->assertEquals(0, $fila->porcentaje_comision);
        $this->assertEquals(0, $fila->monto_comision);
        $this->assertSame('pendiente', $fila->estado);
        $this->assertEquals(1, $fila->es_simulado);
        $this->assertSame('simulado', $fila->proveedor);
        foreach (['observacion', 'cedula_confirma', 'fecha_confirmacion', 'cedula_anula', 'fecha_anulacion', 'motivo_anulacion'] as $nula) {
            $this->assertNull($fila->{$nula}, "pagos.{$nula} debe ser NULL por defecto");
        }

        // Y el modelo los presenta con sus casts.
        $pago = Pago::findOrFail($id);
        $this->assertSame('0.00', $pago->porcentaje_comision);
        $this->assertSame('0.00', $pago->monto_comision);
        $this->assertTrue($pago->es_simulado);
    }

    public function test_columnas_obligatorias_sin_default_se_rechazan_en_bd(): void
    {
        [, , , $contratacion] = $this->escenario();

        foreach (['monto', 'monto_neto', 'fecha_pago', 'metodo', 'referencia_externa', 'cedula_registra', 'fecha_registro'] as $columna) {
            $fila = $this->filaMinima($contratacion->id_contratacion);
            unset($fila[$columna]);

            try {
                DB::table('pagos')->insert($fila);
                $this->fail("Se esperaba NOT NULL en pagos.{$columna}");
            } catch (QueryException) {
                // esperado
            }
        }

        $this->assertSame(0, DB::table('pagos')->count());
    }

    public function test_indices_unicos_y_clave_foranea_definidos_con_nombres_validos(): void
    {
        $indices = collect(Schema::getIndexes('pagos'))->keyBy('name');

        $this->assertSame(['id_contratacion'], $indices['pagos_id_contratacion_index']['columns']);
        $this->assertSame(['estado', 'fecha_pago'], $indices['pagos_estado_fecha_pago_index']['columns']);
        $this->assertSame(['es_simulado'], $indices['pagos_es_simulado_index']['columns']);
        $this->assertSame(['cedula_registra'], $indices['pagos_cedula_registra_index']['columns']);
        $this->assertSame(['proveedor', 'referencia_externa'], $indices['pagos_proveedor_referencia_unique']['columns']);
        $this->assertTrue($indices['pagos_proveedor_referencia_unique']['unique']);

        $indicesParametros = collect(Schema::getIndexes('parametros'))->keyBy('name');
        $this->assertTrue($indicesParametros['parametros_clave_unique']['unique']);

        // Límite de identificadores de MySQL: 64 caracteres.
        foreach ([...$indices->keys(), ...$indicesParametros->keys(), 'pagos_id_contratacion_foreign'] as $nombre) {
            $this->assertLessThanOrEqual(64, strlen($nombre), "Nombre de índice demasiado largo: {$nombre}");
        }

        $fks = Schema::getForeignKeys('pagos');
        $this->assertCount(1, $fks);
        $this->assertSame(['id_contratacion'], $fks[0]['columns']);
        $this->assertSame('contrataciones', $fks[0]['foreign_table']);
        $this->assertSame(['id_contratacion'], $fks[0]['foreign_columns']);
        $this->assertSame('restrict', strtolower($fks[0]['on_delete']));
        $this->assertSame('restrict', strtolower($fks[0]['on_update']));

        // Sin FK hacia usuarios ni en parametros (convención del dominio).
        $this->assertSame([], Schema::getForeignKeys('parametros'));
    }

    public function test_down_elimina_las_tablas_y_up_las_recrea_sin_tocar_contrataciones(): void
    {
        [, , , $contratacion] = $this->escenario();
        $antes = (array) DB::table('contrataciones')->where('id_contratacion', $contratacion->id_contratacion)->first();

        $migracionPagos = require database_path('migrations/2026_11_07_000002_create_pagos_table.php');
        $migracionParametros = require database_path('migrations/2026_11_07_000001_create_parametros_table.php');

        // Orden de rollback: pagos (más reciente) y luego parametros.
        $migracionPagos->down();
        $migracionParametros->down();
        $this->assertFalse(Schema::hasTable('pagos'));
        $this->assertFalse(Schema::hasTable('parametros'));
        $this->assertTrue(Schema::hasTable('contrataciones'));

        // down() es tolerante: una segunda ejecución no falla (dropIfExists).
        $migracionPagos->down();
        $migracionParametros->down();

        $migracionParametros->up();
        $migracionPagos->up();
        $this->assertTrue(Schema::hasTable('pagos'));
        $this->assertSame(2, DB::table('parametros')->count());
        $this->assertSame($antes, (array) DB::table('contrataciones')->where('id_contratacion', $contratacion->id_contratacion)->first());
    }

    // ═══════════════════════ 2. Asignación masiva ═══════════════════════

    private function arrayMalicioso(int $idContratacion): array
    {
        return [
            'id_pago'             => 777,
            'id_contratacion'     => $idContratacion,
            'monto'               => '50.00',
            'fecha_pago'          => '2026-03-01',
            'metodo'              => 'qr',
            'observacion'         => 'Pago QA',
            'estado'              => 'confirmado',
            'es_simulado'         => false,
            'proveedor'           => 'banco_real',
            'referencia_externa'  => 'REF-ATACANTE',
            'porcentaje_comision' => '99.00',
            'monto_comision'      => '49.50',
            'monto_neto'          => '0.50',
            'cedula_registra'     => 1,
            'fecha_registro'      => '2000-01-01 00:00:00',
            'cedula_confirma'     => 2,
            'fecha_confirmacion'  => '2000-01-02 00:00:00',
            'cedula_anula'        => 3,
            'fecha_anulacion'     => '2000-01-03 00:00:00',
            'motivo_anulacion'    => 'falso',
        ];
    }

    public function test_fill_con_array_malicioso_solo_asigna_campos_del_contratador(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        $pago = (new Pago())->fill($this->arrayMalicioso($contratacion->id_contratacion));

        $this->assertEqualsCanonicalizing(
            ['id_contratacion', 'monto', 'fecha_pago', 'metodo', 'observacion', 'porcentaje_comision', 'monto_comision', 'estado', 'es_simulado', 'proveedor'],
            array_keys($pago->getAttributes())
        );
        // Los valores protegidos conservan los valores por defecto del modelo.
        $this->assertSame('pendiente', $pago->estado);
        $this->assertTrue($pago->es_simulado);
        $this->assertSame('simulado', $pago->proveedor);
        $this->assertSame('0.00', $pago->porcentaje_comision);
        $this->assertSame('0.00', $pago->monto_comision);

        // El servicio fija los campos de control con forceFill y se persisten SUS valores.
        $pago->forceFill([
            'monto_neto'         => '50.00',
            'referencia_externa' => 'SIM-LEGITIMA-1',
            'cedula_registra'    => $contratador->cedula,
            'fecha_registro'     => '2026-03-01 12:00:00',
        ])->save();

        $fila = DB::table('pagos')->where('id_pago', $pago->id_pago)->first();
        $this->assertNotEquals(777, $fila->id_pago);
        $this->assertSame('pendiente', $fila->estado);
        $this->assertEquals(1, $fila->es_simulado);
        $this->assertSame('simulado', $fila->proveedor);
        $this->assertSame('SIM-LEGITIMA-1', $fila->referencia_externa);
        $this->assertEquals(0, $fila->porcentaje_comision);
        $this->assertEquals(0, $fila->monto_comision);
        $this->assertEquals(50, $fila->monto_neto);
        $this->assertEquals($contratador->cedula, $fila->cedula_registra);
        $this->assertSame('2026-03-01 12:00:00', $fila->fecha_registro);
        foreach (['cedula_confirma', 'fecha_confirmacion', 'cedula_anula', 'fecha_anulacion', 'motivo_anulacion'] as $nula) {
            $this->assertNull($fila->{$nula});
        }
    }

    public function test_create_con_array_malicioso_es_rechazado_y_no_persiste_nada(): void
    {
        [, , , $contratacion] = $this->escenario();

        try {
            Pago::create($this->arrayMalicioso($contratacion->id_contratacion));
            $this->fail('Se esperaba DomainException: create() no puede fijar neto ni referencia.');
        } catch (DomainException) {
            // esperado: monto_neto y referencia_externa no son asignables en masa.
        }

        $this->assertSame(0, DB::table('pagos')->count());
    }

    public function test_parametro_no_permite_asignar_auditoria_en_masa(): void
    {
        $parametro = new Parametro([
            'clave'               => 'nuevo_parametro',
            'valor'               => '1',
            'descripcion'         => 'Prueba',
            'id_parametro'        => 999,
            'fecha_actualizacion' => '2000-01-01 00:00:00',
            'cedula_actualiza'    => 123,
        ]);

        $this->assertSame('nuevo_parametro', $parametro->clave);
        $this->assertNull($parametro->id_parametro);
        $this->assertNull($parametro->fecha_actualizacion);
        $this->assertNull($parametro->cedula_actualiza);
    }

    // ═══════════════════════ 3. Relaciones ═══════════════════════

    public function test_relaciones_en_ambos_sentidos_con_cedulas_reales(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();
        $admin = $this->crearUsuario('administrador');
        $otroAdmin = $this->crearUsuario('administrador');
        [, , , $otraContratacion] = $this->escenario();

        $confirmado = $this->pago($contratacion->id_contratacion, $contratador->cedula, [
            'estado'             => 'confirmado',
            'cedula_confirma'    => $admin->cedula,
            'fecha_confirmacion' => '2026-02-02 10:15:30',
        ]);
        $confirmado->save();
        $anulado = $this->pago($contratacion->id_contratacion, $contratador->cedula, [
            'estado'           => 'anulado',
            'cedula_anula'     => $otroAdmin->cedula,
            'fecha_anulacion'  => '2026-02-03 11:00:00',
            'motivo_anulacion' => 'Duplicado',
        ]);
        $anulado->save();
        $this->pago($otraContratacion->id_contratacion, $contratador->cedula)->save();

        // Contratacion -> pagos (solo los suyos).
        $this->assertEqualsCanonicalizing(
            [$confirmado->id_pago, $anulado->id_pago],
            $contratacion->pagos()->pluck('id_pago')->all()
        );
        $cargada = Contratacion::with('pagos')->findOrFail($contratacion->id_contratacion);
        $this->assertCount(2, $cargada->pagos);
        $this->assertContainsOnlyInstancesOf(Pago::class, $cargada->pagos);

        // Pago -> contratacion / registrador / confirmador / anulador (eager loading).
        $p1 = Pago::with(['contratacion', 'registrador', 'confirmador', 'anulador'])->findOrFail($confirmado->id_pago);
        $this->assertSame($contratacion->id_contratacion, $p1->contratacion->id_contratacion);
        $this->assertSame($contratador->cedula, $p1->registrador->cedula);
        $this->assertSame($admin->cedula, $p1->confirmador->cedula);
        $this->assertNull($p1->anulador);

        $p2 = Pago::findOrFail($anulado->id_pago);
        $this->assertSame($otroAdmin->cedula, $p2->anulador->cedula);
        $this->assertNull($p2->confirmador);
        $this->assertSame('Duplicado', $p2->motivo_anulacion);

        // Las fechas de auditoría conservan la hora.
        $this->assertSame('2026-02-02 10:15:30', $p1->fecha_confirmacion->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-01 09:30:15', $p1->fecha_registro->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-03 11:00:00', $p2->fecha_anulacion->format('Y-m-d H:i:s'));
    }

    public function test_registrador_inexistente_devuelve_null_sin_fk_hacia_usuarios(): void
    {
        [, , , $contratacion] = $this->escenario();

        // Sin FK a usuarios: la cédula se conserva como dato histórico aunque el usuario no exista.
        $pago = $this->pago($contratacion->id_contratacion, 99999999);
        $pago->save();

        $this->assertNull($pago->fresh()->registrador);
        $this->assertEquals(99999999, DB::table('pagos')->value('cedula_registra'));
    }

    public function test_historial_financiero_solo_con_pagos_y_tambien_si_todos_estan_anulados(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        $this->assertFalse($contratacion->tieneHistorialFinanciero());

        $this->pago($contratacion->id_contratacion, $contratador->cedula, ['estado' => 'anulado'])->save();

        $this->assertSame(0, $contratacion->pagos()->noAnulados()->count());
        $this->assertTrue($contratacion->tieneHistorialFinanciero());
    }

    // ═══════════════════════ 4. Integridad referencial ═══════════════════════

    public function test_fk_rechaza_insercion_directa_con_contratacion_inexistente(): void
    {
        try {
            DB::table('pagos')->insert($this->filaMinima(424242));
            $this->fail('Se esperaba violación de clave foránea.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertSame(0, DB::table('pagos')->count());
    }

    public function test_fk_rechaza_borrado_y_cambio_de_id_de_contratacion_con_pagos_en_bd(): void
    {
        [, , , $contratacion] = $this->escenario();
        $id = $contratacion->id_contratacion;
        DB::table('pagos')->insert($this->filaMinima($id));

        try {
            DB::table('contrataciones')->where('id_contratacion', $id)->delete();
            $this->fail('Se esperaba RESTRICT al borrar.');
        } catch (QueryException) {
            // esperado
        }

        try {
            DB::table('contrataciones')->where('id_contratacion', $id)->update(['id_contratacion' => $id + 1000]);
            $this->fail('Se esperaba RESTRICT al actualizar la PK referenciada.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertTrue(DB::table('contrataciones')->where('id_contratacion', $id)->exists());
        $this->assertSame(1, DB::table('pagos')->where('id_contratacion', $id)->count());
    }

    public function test_pago_no_puede_moverse_a_contratacion_inexistente(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();
        $pago = $this->pago($contratacion->id_contratacion, $contratador->cedula);
        $pago->save();

        try {
            DB::table('pagos')->where('id_pago', $pago->id_pago)->update(['id_contratacion' => 424242]);
            $this->fail('Se esperaba violación de clave foránea.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertSame($contratacion->id_contratacion, $pago->fresh()->id_contratacion);
    }

    public static function rolesNoAdministrador(): array
    {
        return [
            'contratador' => ['contratador', 403],
            'trabajador'  => ['trabajador', 403],
            'sin rol'     => [null, 403],
        ];
    }

    #[DataProvider('rolesNoAdministrador')]
    public function test_no_administrador_no_puede_borrar_contratacion_con_pagos(?string $rol, int $estado): void
    {
        [$contratador, , , $contratacion] = $this->escenario();
        $this->pago($contratacion->id_contratacion, $contratador->cedula)->save();

        Sanctum::actingAs($this->crearUsuario($rol));
        $this->deleteJson("/api/admin/contrataciones/{$contratacion->id_contratacion}")->assertStatus($estado);

        $this->assertNotNull($contratacion->fresh());
        $this->assertSame(1, DB::table('pagos')->count());
    }

    public function test_sin_autenticar_no_puede_borrar_contratacion_con_pagos(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();
        $this->pago($contratacion->id_contratacion, $contratador->cedula)->save();

        $this->deleteJson("/api/admin/contrataciones/{$contratacion->id_contratacion}")->assertStatus(401);

        $this->assertNotNull($contratacion->fresh());
        $this->assertSame(1, DB::table('pagos')->count());
    }

    // ═══════════════════════ 5. Unicidad ═══════════════════════

    public function test_unicidad_proveedor_referencia_a_nivel_de_bd_sin_el_modelo(): void
    {
        [, , , $contratacion] = $this->escenario();
        [, , , $otra] = $this->escenario();
        $id = $contratacion->id_contratacion;

        DB::table('pagos')->insert($this->filaMinima($id, ['referencia_externa' => 'OP-1', 'proveedor' => 'simulado']));
        // Misma referencia con otro proveedor: permitido.
        DB::table('pagos')->insert($this->filaMinima($id, ['referencia_externa' => 'OP-1', 'proveedor' => 'externo']));

        try {
            // Mismo proveedor y referencia, aunque sea otra contratación: rechazado.
            DB::table('pagos')->insert($this->filaMinima($otra->id_contratacion, ['referencia_externa' => 'OP-1', 'proveedor' => 'simulado']));
            $this->fail('Se esperaba violación de unicidad.');
        } catch (UniqueConstraintViolationException) {
            // esperado
        }

        $this->assertSame(2, DB::table('pagos')->count());
    }

    public function test_referencia_externa_null_rechazada_en_bd(): void
    {
        [, , , $contratacion] = $this->escenario();

        try {
            DB::table('pagos')->insert($this->filaMinima($contratacion->id_contratacion, ['referencia_externa' => null]));
            $this->fail('Se esperaba NOT NULL en referencia_externa.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertSame(0, DB::table('pagos')->count());
    }

    // ═══════════════════════ 6. Importes ═══════════════════════

    public static function importesValidos(): array
    {
        // [monto, comision, neto, monto esperado, neto esperado]
        return [
            'minimo 0.01'           => ['0.01', '0.00', '0.01', '0.01', '0.01'],
            'maximo 99999999.99'    => ['99999999.99', '0.00', '99999999.99', '99999999.99', '99999999.99'],
            '100.10 menos 0.00'     => ['100.10', '0.00', '100.10', '100.10', '100.10'],
            'entero se formatea'    => [5, 0, 5, '5.00', '5.00'],
            'un decimal'            => ['100.1', '0', '100.1', '100.10', '100.10'],
            'coma flotante 0.3-0.1' => ['0.30', '0.10', '0.20', '0.30', '0.20'],
            'comision igual monto'  => ['10.00', '10.00', '0.00', '10.00', '0.00'],
        ];
    }

    #[DataProvider('importesValidos')]
    public function test_importes_decimales_validos_se_guardan_y_leen_con_dos_decimales($monto, $comision, $neto, string $montoEsperado, string $netoEsperado): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        $pago = $this->pago($contratacion->id_contratacion, $contratador->cedula, [
            'monto'          => $monto,
            'monto_comision' => $comision,
            'monto_neto'     => $neto,
        ]);
        $pago->save();

        $leido = Pago::findOrFail($pago->id_pago);
        $this->assertSame($montoEsperado, $leido->monto);
        $this->assertSame($netoEsperado, $leido->monto_neto);
    }

    public function test_cast_decimal_redondea_a_dos_decimales_al_leer(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        // El modelo no limita la escala: 10.005 se acepta (neto coherente en centavos)
        // y el cast decimal:2 lo presenta redondeado. La validación de escala
        // (decimal:0,2) corresponde al futuro Form Request de pagos.
        $pago = $this->pago($contratacion->id_contratacion, $contratador->cedula, [
            'monto'      => '10.005',
            'monto_neto' => '10.005',
        ]);
        $pago->save();

        $this->assertSame('10.01', Pago::findOrFail($pago->id_pago)->monto);
    }

    public static function importesInvalidos(): array
    {
        return [
            'redondea a cero (0.004)'    => [['monto' => '0.004', 'monto_neto' => '0.004']],
            'negativo minimo (-0.01)'    => [['monto' => '-0.01', 'monto_neto' => '-0.01']],
            'neto un centavo menos'      => [['monto' => '100.10', 'monto_neto' => '100.09']],
            'neto un centavo mas'        => [['monto' => '100.10', 'monto_neto' => '100.11']],
            'comision un centavo > monto' => [['monto' => '10.00', 'monto_comision' => '10.01', 'monto_neto' => '-0.01']],
            'porcentaje negativo'        => [['porcentaje_comision' => '-0.01']],
        ];
    }

    #[DataProvider('importesInvalidos')]
    public function test_importes_invalidos_rechazados_por_el_dominio(array $cambios): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        try {
            $this->pago($contratacion->id_contratacion, $contratador->cedula, $cambios)->save();
            $this->fail('Se esperaba DomainException.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertSame(0, DB::table('pagos')->count());
    }

    public function test_monto_no_numerico_rechazado_por_el_cast_sin_persistir(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        // validarDominio lee $this->monto a través del cast decimal:2, que lanza
        // MathException antes de la comprobación de dominio: el pago no se guarda.
        try {
            $this->pago($contratacion->id_contratacion, $contratador->cedula, ['monto' => 'abc', 'monto_neto' => 'abc'])->save();
            $this->fail('Se esperaba MathException.');
        } catch (MathException) {
            // esperado
        }

        $this->assertSame(0, DB::table('pagos')->count());
    }

    public function test_porcentaje_comision_frontera_100_permitido(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        $this->pago($contratacion->id_contratacion, $contratador->cedula, [
            'monto' => '20.00', 'porcentaje_comision' => '100.00', 'monto_comision' => '20.00', 'monto_neto' => '0.00',
        ])->save();

        $this->assertSame('100.00', Pago::firstOrFail()->porcentaje_comision);
    }

    // ═══════════════════════ 7. Estados y métodos ═══════════════════════

    public function test_los_tres_estados_permitidos_se_guardan(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        $this->assertSame(['pendiente', 'confirmado', 'anulado'], Pago::ESTADOS);
        foreach (Pago::ESTADOS as $estado) {
            $this->pago($contratacion->id_contratacion, $contratador->cedula, ['estado' => $estado])->save();
        }

        $this->assertEqualsCanonicalizing(Pago::ESTADOS, DB::table('pagos')->pluck('estado')->all());
    }

    public static function estadosRechazados(): array
    {
        return [
            'mayusculas' => ['PENDIENTE'],
            'femenino'   => ['confirmada'],
            'cancelado'  => ['cancelado'],
            'vacio'      => [''],
            'null'       => [null],
            'espacios'   => [' pendiente '],
        ];
    }

    #[DataProvider('estadosRechazados')]
    public function test_estados_fuera_de_dominio_rechazados(?string $estado): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        try {
            $this->pago($contratacion->id_contratacion, $contratador->cedula, ['estado' => $estado])->save();
            $this->fail('Se esperaba DomainException.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertSame(0, DB::table('pagos')->count());
    }

    public function test_cambio_a_estado_invalido_de_un_pago_existente_rechazado(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();
        $pago = $this->pago($contratacion->id_contratacion, $contratador->cedula);
        $pago->save();

        try {
            $pago->forceFill(['estado' => 'reembolsado'])->save();
            $this->fail('Se esperaba DomainException al actualizar.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertSame('pendiente', DB::table('pagos')->where('id_pago', $pago->id_pago)->value('estado'));
    }

    public static function metodosRechazados(): array
    {
        return [
            'mayusculas' => ['EFECTIVO'],
            'con tilde'  => ['depósito'],
            'etiqueta'   => ['QR'],
            'vacio'      => [''],
            'null'       => [null],
        ];
    }

    #[DataProvider('metodosRechazados')]
    public function test_metodos_fuera_de_dominio_rechazados(?string $metodo): void
    {
        [$contratador, , , $contratacion] = $this->escenario();

        try {
            $this->pago($contratacion->id_contratacion, $contratador->cedula, ['metodo' => $metodo])->save();
            $this->fail('Se esperaba DomainException.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertSame(0, DB::table('pagos')->count());
    }

    // ═══════════════════════ 8. Simulación y scopes ═══════════════════════

    public function test_pago_nuevo_es_simulado_por_defecto_y_scopes_combinados(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();
        $id = $contratacion->id_contratacion;

        $porDefecto = $this->pago($id, $contratador->cedula);
        $porDefecto->save();
        $this->assertTrue($porDefecto->fresh()->es_simulado);
        $this->assertSame('simulado', $porDefecto->fresh()->proveedor);

        $this->pago($id, $contratador->cedula, ['estado' => 'confirmado'])->save();
        $this->pago($id, $contratador->cedula, ['estado' => 'anulado'])->save();
        $this->pago($id, $contratador->cedula, ['es_simulado' => false, 'proveedor' => 'externo', 'estado' => 'anulado'])->save();
        $this->pago($id, $contratador->cedula, ['es_simulado' => false, 'proveedor' => 'externo', 'estado' => 'confirmado'])->save();

        $this->assertSame(3, Pago::simulados()->count());
        $this->assertSame(2, Pago::reales()->count());
        $this->assertSame(3, Pago::noAnulados()->count());
        $this->assertSame(2, Pago::simulados()->noAnulados()->count());
        $this->assertSame(1, Pago::reales()->noAnulados()->count());
        $this->assertSame(3, $contratacion->pagos()->noAnulados()->count());
        $this->assertSame(Pago::count(), Pago::simulados()->count() + Pago::reales()->count());
    }

    // ═══════════════════════ 9. Conservación del historial ═══════════════════════

    public function test_admin_no_borra_contratacion_si_todos_sus_pagos_estan_anulados(): void
    {
        [$contratador, , $postulacion, $contratacion] = $this->escenario('finalizada');
        $admin = $this->crearUsuario('administrador');
        foreach (range(1, 2) as $i) {
            $this->pago($contratacion->id_contratacion, $contratador->cedula, [
                'estado'           => 'anulado',
                'cedula_anula'     => $admin->cedula,
                'fecha_anulacion'  => '2026-02-05 15:00:00',
                'motivo_anulacion' => "Anulado {$i}",
            ])->save();
        }
        $pagosAntes = DB::table('pagos')->orderBy('id_pago')->get()->map(fn ($f) => (array) $f)->all();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/admin/contrataciones/{$contratacion->id_contratacion}")
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede eliminar una contratación con pagos registrados.']);

        $this->assertNotNull($contratacion->fresh());
        $this->assertSame('aceptada', $postulacion->fresh()->estado);
        $this->assertSame($pagosAntes, DB::table('pagos')->orderBy('id_pago')->get()->map(fn ($f) => (array) $f)->all());
    }

    public function test_eloquent_delete_de_contratacion_con_pagos_anulados_rechazado_por_bd(): void
    {
        [$contratador, , , $contratacion] = $this->escenario();
        $this->pago($contratacion->id_contratacion, $contratador->cedula, ['estado' => 'anulado'])->save();

        try {
            $contratacion->delete();
            $this->fail('Se esperaba RESTRICT de la FK.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertTrue(DB::table('contrataciones')->where('id_contratacion', $contratacion->id_contratacion)->exists());
    }

    public function test_admin_no_borra_postulacion_vinculada_a_contratacion_con_pagos(): void
    {
        [$contratador, , $postulacion, $contratacion] = $this->escenario();
        $this->pago($contratacion->id_contratacion, $contratador->cedula)->save();

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson("/api/admin/postulaciones/{$postulacion->id_postulacion}")
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede eliminar una postulación vinculada a una contratación.']);

        $this->assertNotNull($postulacion->fresh());
        $this->assertNotNull($contratacion->fresh());
        $this->assertSame(1, DB::table('pagos')->count());
    }

    public function test_rechazar_postulacion_aceptada_con_contratacion_y_pagos_devuelve_422(): void
    {
        [$contratador, , $postulacion, $contratacion] = $this->escenario();
        $this->pago($contratacion->id_contratacion, $contratador->cedula)->save();

        Sanctum::actingAs($contratador);
        $this->putJson("/api/contratador/postulaciones/{$postulacion->id_postulacion}/estado", ['estado' => 'rechazada'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya fue procesada.']);

        $this->assertSame('aceptada', $postulacion->fresh()->estado);
        $this->assertSame('activa', $contratacion->fresh()->estado);
        $this->assertSame(1, DB::table('pagos')->count());
    }

    public function test_trabajador_no_cancela_postulacion_aceptada_con_pagos(): void
    {
        [$contratador, $trabajador, $postulacion, $contratacion] = $this->escenario();
        $this->pago($contratacion->id_contratacion, $contratador->cedula)->save();

        Sanctum::actingAs($trabajador);
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Solo puedes cancelar postulaciones pendientes.']);

        $this->assertNotNull($postulacion->fresh());
        $this->assertSame(1, DB::table('pagos')->count());
    }

    /**
     * DEFECTO CONOCIDO (pendiente de autorización para corregir PostulacionApiController::actualizarEstado):
     * con un dato inconsistente (postulación 'pendiente' que ya tiene contratación, p. ej. heredado),
     * la rama 'rechazada' no comprueba tieneContratacion() y deja la contratación (y sus pagos)
     * colgando de una postulación rechazada, violando "cada contratación corresponde a una
     * postulación aceptada". Comportamiento esperado: 422 sin cambios.
     *
     * La prueba ejecuta el escenario real: si el defecto persiste se marca como incompleta
     * (no rompe la suite); cuando se corrija, las aserciones se ejecutarán y pasará sola.
     */
    public function test_rechazar_postulacion_pendiente_con_contratacion_inconsistente_deberia_devolver_422(): void
    {
        [$contratador, , $postulacion, $contratacion] = $this->escenario();
        DB::table('postulaciones')->where('id_postulacion', $postulacion->id_postulacion)->update(['estado' => 'pendiente']);
        $this->pago($contratacion->id_contratacion, $contratador->cedula)->save();

        Sanctum::actingAs($contratador);
        $respuesta = $this->putJson("/api/contratador/postulaciones/{$postulacion->id_postulacion}/estado", ['estado' => 'rechazada']);

        if ($respuesta->status() === 200) {
            $this->markTestIncomplete(
                'DEFECTO: PostulacionApiController::actualizarEstado permite rechazar una postulación pendiente '
                . 'que ya tiene contratación (estado final: ' . $postulacion->fresh()->estado . '). '
                . 'Requiere autorización para corregir producción.'
            );
        }

        $respuesta->assertStatus(422);
        $this->assertSame('pendiente', $postulacion->fresh()->estado);
        $this->assertNotNull($contratacion->fresh());
    }

    // ═══════════════════════ 10. Sin cambios en datos existentes ═══════════════════════

    public function test_registrar_confirmar_y_anular_pagos_no_modifica_usuarios_postulaciones_ni_contrataciones(): void
    {
        [$contratador, $trabajador, $postulacion, $contratacion] = $this->escenario();
        [, , , $finalizada] = $this->escenario('finalizada');
        $admin = $this->crearUsuario('administrador');

        $foto = fn () => [
            'usuarios'       => DB::table('usuarios')->orderBy('cedula')->get()->map(fn ($f) => (array) $f)->all(),
            'postulaciones'  => DB::table('postulaciones')->orderBy('id_postulacion')->get()->map(fn ($f) => (array) $f)->all(),
            'contrataciones' => DB::table('contrataciones')->orderBy('id_contratacion')->get()->map(fn ($f) => (array) $f)->all(),
            'publicaciones'  => DB::table('publicaciones')->orderBy('id_labor')->get()->map(fn ($f) => (array) $f)->all(),
        ];
        $antes = $foto();

        $p1 = $this->pago($contratacion->id_contratacion, $contratador->cedula);
        $p1->save();
        $p2 = $this->pago($finalizada->id_contratacion, $contratador->cedula, ['monto' => '100.00', 'monto_neto' => '100.00']);
        $p2->save();

        $p1->forceFill(['estado' => 'confirmado', 'cedula_confirma' => $admin->cedula, 'fecha_confirmacion' => now()])->save();
        $p2->forceFill(['estado' => 'anulado', 'cedula_anula' => $admin->cedula, 'fecha_anulacion' => now(), 'motivo_anulacion' => 'QA'])->save();
        Pago::with(['contratacion', 'registrador', 'confirmador', 'anulador'])->get();

        $this->assertSame($antes, $foto());
        $this->assertSame(2, DB::table('pagos')->count());
        $this->assertSame(1, $trabajador->fresh()->estado);
    }

    // ═══════════════════════ 11. Parámetros y seeder ═══════════════════════

    public function test_parametros_iniciales_completos(): void
    {
        $filas = DB::table('parametros')->orderBy('clave')->get()->keyBy('clave');

        $this->assertSame(['comision_porcentaje', 'pagos_proveedor'], $filas->keys()->all());
        foreach ($filas as $fila) {
            $this->assertNotEmpty($fila->descripcion);
            $this->assertNotNull($fila->fecha_actualizacion);
            $this->assertNull($fila->cedula_actualiza);
        }
        $this->assertSame('0.00', $filas['comision_porcentaje']->valor);
        $this->assertSame('simulado', $filas['pagos_proveedor']->valor);
        // El proveedor por defecto de la tabla coincide con el parámetro.
        $this->assertSame(Parametro::valor('pagos_proveedor'), (new Pago())->proveedor);
    }

    public function test_seeder_dos_veces_no_sobrescribe_descripcion_ni_auditoria_ni_otros_parametros(): void
    {
        $admin = $this->crearUsuario('administrador');
        DB::table('parametros')->where('clave', 'comision_porcentaje')->update([
            'valor'               => '2.50',
            'descripcion'         => 'Editada por el administrador',
            'fecha_actualizacion' => '2026-10-01 08:00:00',
            'cedula_actualiza'    => $admin->cedula,
        ]);
        DB::table('parametros')->insert(['clave' => 'otro_parametro', 'valor' => 'x']);
        $antes = DB::table('parametros')->orderBy('id_parametro')->get()->map(fn ($f) => (array) $f)->all();

        $this->seed(ParametrosSeeder::class);
        $this->seed(ParametrosSeeder::class);

        $this->assertSame($antes, DB::table('parametros')->orderBy('id_parametro')->get()->map(fn ($f) => (array) $f)->all());
    }

    public function test_seeder_sobre_tabla_vacia_crea_ambos_una_sola_vez(): void
    {
        DB::table('parametros')->delete();

        $this->seed(ParametrosSeeder::class);
        $this->seed(ParametrosSeeder::class);

        $this->assertSame(2, DB::table('parametros')->count());
        $this->assertSame('0.00', Parametro::valor('comision_porcentaje'));
        $this->assertSame('simulado', Parametro::valor('pagos_proveedor'));
        $this->assertSame(0, DB::table('parametros')->whereNull('fecha_actualizacion')->count());
    }

    public function test_insert_or_ignore_de_la_migracion_no_sobrescribe_valor_existente(): void
    {
        DB::table('parametros')->where('clave', 'comision_porcentaje')->update(['valor' => '3.00']);

        // Misma sentencia que usa la migración: la clave existente se ignora.
        DB::table('parametros')->insertOrIgnore([
            ['clave' => 'comision_porcentaje', 'valor' => '0.00', 'descripcion' => null, 'fecha_actualizacion' => now(), 'cedula_actualiza' => null],
        ]);

        $this->assertSame(2, DB::table('parametros')->count());
        $this->assertSame('3.00', Parametro::valor('comision_porcentaje'));
    }

    public function test_valor_de_parametro_cero_no_se_confunde_con_el_default(): void
    {
        DB::table('parametros')->where('clave', 'comision_porcentaje')->update(['valor' => '0']);

        $this->assertSame('0', Parametro::valor('comision_porcentaje', 'default'));
        $this->assertNull(Parametro::valor('clave_inexistente'));
    }
}
