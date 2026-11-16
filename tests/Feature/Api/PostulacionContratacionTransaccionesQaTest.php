<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 3.0.3 — Transacciones, bloqueos y atomicidad (QA).
 *
 * LIMITACIONES DE SQLITE EN MEMORIA (único entorno de pruebas autorizado):
 *  - SQLite no soporta SELECT ... FOR UPDATE: su gramática (SQLiteGrammar::compileLock)
 *    devuelve '' y lockForUpdate() se ignora silenciosamente. El bloqueo real de filas
 *    solo existe en MySQL/InnoDB (producción).
 *  - `:memory:` usa una única conexión PDO por proceso: no es posible abrir dos
 *    conexiones que compitan por la misma fila ni ejecutar peticiones en paralelo.
 *    No hay concurrencia real; las carreras solo pueden SIMULARSE de forma secuencial.
 *  - RefreshDatabase envuelve cada prueba en una transacción; las transacciones del
 *    código se convierten en SAVEPOINTs. Por eso se compara DB::transactionLevel()
 *    con el nivel base medido justo antes de la petición.
 *
 * Qué se verifica aquí de forma significativa:
 *  1. Que la consulta de bloqueo y las escrituras se ejecutan DENTRO de una transacción
 *     abierta por el código (nivel > base), capturado con DB::listen.
 *  2. Que el código SOLICITA el bloqueo (lockForUpdate): se sustituye la gramática de
 *     consultas de la conexión de pruebas por una subclase de SQLiteGrammar que emite un
 *     comentario SQL inocuo (/* lock:update *\/) cuando la consulta pide bloqueo. No se
 *     modifica app/; solo se hace visible la intención de bloqueo en el SQL capturado.
 *  3. Atomicidad: una excepción a mitad de la transacción no deja escrituras parciales.
 *  4. Carrera del UNIQUE(id_postulacion) simulada: un listener `creating` inserta la
 *     contratación "concurrente" justo después de las comprobaciones y antes del INSERT
 *     del código, provocando una UniqueConstraintViolationException real → 422, no 500.
 *     (Como todo ocurre en la misma conexión, el rollback también deshace la fila
 *     "concurrente"; en MySQL esa fila pertenecería a otra transacción ya confirmada.)
 *
 * Lo que NO se puede verificar en SQLite: espera/serialización real entre dos
 * transacciones, deadlocks, timeouts de bloqueo ni aislamiento REPEATABLE READ.
 */
class PostulacionContratacionTransaccionesQaTest extends TestCase
{
    use RefreshDatabase;

    private const MARCA_LOCK = 'lock:update';

    private int $siguienteCedula = 8000;

    /** @var array<int, array{sql: string, nivel: int}> */
    private array $consultas = [];

    private int $nivelBase = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    protected function tearDown(): void
    {
        Contratacion::flushEventListeners();
        parent::tearDown();
    }

    // ─────────────────────────────── Helpers ───────────────────────────────

    private function crearUsuario(string $rol): User
    {
        $cedula = $this->siguienteCedula++;

        $user = User::create([
            'cedula'     => $cedula,
            'nombre'     => "Usuario TX {$cedula}",
            'email'      => "tx{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);
        $user->assignRole($rol);

        return $user;
    }

    private function crearPostulacion(User $contratador, ?User $trabajador = null, $pago = 100, string $estado = 'pendiente'): Postulacion
    {
        $publicacion = Publicacion::create([
            'tipo'              => 'oferta',
            'titulo'            => 'Trabajo TX',
            'descripcion'       => 'Descripción TX',
            'pago'              => $pago,
            'lugar'             => 'Ciudad',
            'fecha_publicacion' => now()->toDateString(),
            'cedula'            => $contratador->cedula,
            'id_categoria'      => 1,
            'estado'            => 1,
        ]);

        return Postulacion::create([
            'id_labor'          => $publicacion->id_labor,
            'cedula'            => ($trabajador ?? $this->crearUsuario('trabajador'))->cedula,
            'fecha_postulacion' => now()->toDateString(),
            'estado'            => $estado,
        ]);
    }

    private function insertarContratacion(Postulacion $postulacion, string $estado = 'activa'): int
    {
        return (int) DB::table('contrataciones')->insertGetId([
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => '2026-01-15',
            'monto_acordado' => 100,
            'estado'         => $estado,
        ], 'id_contratacion');
    }

    /**
     * Hace visible lockForUpdate() en el SQL y registra cada consulta con el nivel
     * de transacción vigente. Debe llamarse justo antes de la petición HTTP.
     */
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

    /** @return array<int, array{sql: string, nivel: int}> */
    private function consultasSobre(string $tabla, string $verbo): array
    {
        return array_values(array_filter($this->consultas, function ($c) use ($tabla, $verbo) {
            return str_starts_with($c['sql'], $verbo) && str_contains($c['sql'], '"' . $tabla . '"');
        }));
    }

    private function assertBloqueoDentroDeTransaccion(string $tabla): void
    {
        $bloqueos = array_values(array_filter(
            $this->consultasSobre($tabla, 'select'),
            fn ($c) => str_contains($c['sql'], self::MARCA_LOCK)
        ));

        $this->assertNotEmpty($bloqueos, "No se solicitó lockForUpdate() sobre {$tabla}.");
        foreach ($bloqueos as $c) {
            $this->assertGreaterThan($this->nivelBase, $c['nivel'], "Bloqueo fuera de transacción: {$c['sql']}");
        }
    }

    private function assertEscriturasDentroDeTransaccion(string $tabla, string $verbo): void
    {
        $escrituras = $this->consultasSobre($tabla, $verbo);

        $this->assertNotEmpty($escrituras, "No hubo {$verbo} sobre {$tabla}.");
        foreach ($escrituras as $c) {
            $this->assertGreaterThan($this->nivelBase, $c['nivel'], "Escritura fuera de transacción: {$c['sql']}");
        }
    }

    // ═══════════ 1-2. Bloqueo y escrituras dentro de transacción ═══════════

    public function test_la_gramatica_de_prueba_hace_visible_el_bloqueo(): void
    {
        // Control de la técnica: sin lockForUpdate no hay marca; con él, sí.
        $this->capturarConsultas();
        Postulacion::query()->where('id_postulacion', 1)->first();
        Postulacion::query()->lockForUpdate()->where('id_postulacion', 1)->first();

        $selects = $this->consultasSobre('postulaciones', 'select');
        $this->assertCount(2, $selects);
        $this->assertStringNotContainsString(self::MARCA_LOCK, $selects[0]['sql']);
        $this->assertStringContainsString(self::MARCA_LOCK, $selects[1]['sql']);
        $this->assertSame($this->nivelBase, $selects[1]['nivel']);
    }

    public function test_aceptar_bloquea_y_escribe_dentro_de_transaccion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);

        Sanctum::actingAs($contratador);
        $this->capturarConsultas();
        $this->putJson("/api/contratador/postulaciones/{$postulacion->id_postulacion}/estado", ['estado' => 'aceptada'])->assertOk();

        $this->assertBloqueoDentroDeTransaccion('postulaciones');
        $this->assertEscriturasDentroDeTransaccion('postulaciones', 'update');
        $this->assertEscriturasDentroDeTransaccion('contrataciones', 'insert');
        $this->assertSame($this->nivelBase, DB::transactionLevel(), 'La transacción quedó abierta.');
    }

    public function test_rechazar_bloquea_y_escribe_dentro_de_transaccion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);

        Sanctum::actingAs($contratador);
        $this->capturarConsultas();
        $this->putJson("/api/contratador/postulaciones/{$postulacion->id_postulacion}/estado", ['estado' => 'rechazada'])->assertOk();

        $this->assertBloqueoDentroDeTransaccion('postulaciones');
        $this->assertEscriturasDentroDeTransaccion('postulaciones', 'update');
    }

    public function test_store_bloquea_postulacion_e_inserta_dentro_de_transaccion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, 'aceptada');

        Sanctum::actingAs($contratador);
        $this->capturarConsultas();
        $this->postJson('/api/contratador/contrataciones', [
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => '2026-11-07',
            'monto_acordado' => 100,
        ])->assertCreated();

        $this->assertBloqueoDentroDeTransaccion('postulaciones');
        $this->assertEscriturasDentroDeTransaccion('contrataciones', 'insert');
    }

    public function test_actualizar_estado_contratacion_bloquea_y_actualiza_dentro_de_transaccion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $id = $this->insertarContratacion($this->crearPostulacion($contratador, null, 100, 'aceptada'));

        Sanctum::actingAs($contratador);
        $this->capturarConsultas();
        $this->putJson("/api/contratador/contrataciones/{$id}/estado", ['estado' => 'finalizada'])->assertOk();

        $this->assertBloqueoDentroDeTransaccion('contrataciones');
        $this->assertEscriturasDentroDeTransaccion('contrataciones', 'update');
    }

    public function test_cancelar_del_trabajador_bloquea_y_borra_dentro_de_transaccion(): void
    {
        $trabajador = $this->crearUsuario('trabajador');
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), $trabajador);

        Sanctum::actingAs($trabajador);
        $this->capturarConsultas();
        $this->deleteJson("/api/trabajador/postulaciones/{$postulacion->id_postulacion}")->assertOk();

        $this->assertBloqueoDentroDeTransaccion('postulaciones');
        $this->assertEscriturasDentroDeTransaccion('postulaciones', 'delete');
    }

    public function test_destroys_de_admin_bloquean_y_borran_dentro_de_transaccion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);
        $id = $this->insertarContratacion($this->crearPostulacion($contratador, null, 100, 'aceptada'));

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->capturarConsultas();
        $this->deleteJson("/api/admin/postulaciones/{$postulacion->id_postulacion}")->assertOk();
        $this->deleteJson("/api/admin/contrataciones/{$id}")->assertOk();

        $this->assertBloqueoDentroDeTransaccion('postulaciones');
        $this->assertBloqueoDentroDeTransaccion('contrataciones');
        $this->assertEscriturasDentroDeTransaccion('postulaciones', 'delete');
        $this->assertEscriturasDentroDeTransaccion('contrataciones', 'delete');
    }

    // ═══════════════════ 3. Atomicidad (sin escrituras parciales) ═══════════════════

    public function test_fallo_al_crear_la_contratacion_revierte_la_aceptacion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);

        // Fallo inesperado DESPUÉS del UPDATE de la postulación y ANTES del INSERT.
        Contratacion::creating(function () {
            throw new RuntimeException('Fallo simulado al crear la contratación.');
        });

        Sanctum::actingAs($contratador);
        $this->putJson("/api/contratador/postulaciones/{$postulacion->id_postulacion}/estado", ['estado' => 'aceptada'])
            ->assertStatus(500);

        $this->assertSame('pendiente', $postulacion->fresh()->estado, 'La aceptación no se revirtió.');
        $this->assertSame(0, Contratacion::count());
        $this->assertSame(1, DB::transactionLevel(), 'Quedó una transacción del código abierta.');
    }

    // ═══════════════ 4. Carrera del UNIQUE simulada (422, no 500) ═══════════════

    private function simularContratacionConcurrente(): void
    {
        Contratacion::creating(function (Contratacion $c) {
            // Otra petición "gana" justo después de las comprobaciones del código.
            DB::table('contrataciones')->insert([
                'id_postulacion' => $c->id_postulacion,
                'fecha_inicio'   => '2026-11-07',
                'monto_acordado' => 1,
                'estado'         => 'activa',
            ]);
        });
    }

    public function test_carrera_unique_al_aceptar_devuelve_422_y_revierte_el_cambio_de_estado(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador);
        $this->simularContratacionConcurrente();

        Sanctum::actingAs($contratador);
        $this->putJson("/api/contratador/postulaciones/{$postulacion->id_postulacion}/estado", ['estado' => 'aceptada'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya tiene una contratación registrada.']);

        // Toda la transacción (UPDATE + fila simulada) se revirtió.
        $this->assertSame('pendiente', $postulacion->fresh()->estado);
        $this->assertSame(0, Contratacion::count());
    }

    public function test_carrera_unique_en_store_devuelve_422_no_500(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, null, 100, 'aceptada');
        $this->simularContratacionConcurrente();

        Sanctum::actingAs($contratador);
        $this->postJson('/api/contratador/contrataciones', [
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => '2026-11-07',
            'monto_acordado' => 100,
        ])->assertStatus(422)
          ->assertExactJson(['message' => 'Esta postulación ya tiene una contratación registrada.']);

        $this->assertSame('aceptada', $postulacion->fresh()->estado);
        $this->assertSame(0, Contratacion::count());
    }
}
