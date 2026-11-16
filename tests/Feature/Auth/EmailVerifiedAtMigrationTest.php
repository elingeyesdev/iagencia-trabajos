<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migración de `usuarios.email_verified_at` (Tarea 1.4, adaptada después a bases
 * donde la columna ya existía).
 *
 * Comportamiento aprobado (no destructivo):
 * - up() crea la columna solo si falta; si ya existe no la duplica ni la altera.
 * - down() NO elimina la columna: la migración no puede distinguir con seguridad
 *   una columna preexistente de una creada por ella, así que conserva datos.
 * - Las cuentas previas quedan sin verificar (Opción A): nunca se marcan
 *   verificadas de forma artificial.
 *
 * Se ejecuta solo sobre SQLite en memoria.
 */
class EmailVerifiedAtMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'database/migrations/2026_11_06_000000_add_email_verified_at_to_usuarios_table.php';

    private function salvaguarda(): void
    {
        // Nunca ejecutar DDL fuera de la base de pruebas en memoria.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    private function insertarCuentaPrevia(array $extra = []): void
    {
        DB::table('usuarios')->insert(array_merge([
            'cedula' => 1234567,
            'nombre' => 'Cuenta Previa',
            'email' => 'previa@example.com',
            'contrasena' => 'hash-de-prueba',
            'fechanac' => '1990-01-01',
            'estado' => 1,
        ], $extra));
    }

    public function test_el_esquema_migrado_incluye_la_columna(): void
    {
        $this->salvaguarda();
        $this->assertTrue(Schema::hasColumn('usuarios', 'email_verified_at'));
    }

    public function test_up_crea_la_columna_si_falta_conservando_datos_sin_verificar(): void
    {
        $this->salvaguarda();
        $migracion = require base_path(self::MIGRACION);

        // Simula una base antigua sin la columna.
        Schema::table('usuarios', fn ($t) => $t->dropColumn('email_verified_at'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'email_verified_at'));
        $this->insertarCuentaPrevia();

        // up() usa ->after('email'): en SQLite debe ignorarse sin error.
        $migracion->up();
        $this->assertTrue(Schema::hasColumn('usuarios', 'email_verified_at'));

        $fila = DB::table('usuarios')->where('cedula', 1234567)->first();
        $this->assertNotNull($fila);
        $this->assertSame('Cuenta Previa', $fila->nombre);
        $this->assertSame('previa@example.com', $fila->email);
        $this->assertSame('hash-de-prueba', $fila->contrasena);
        $this->assertNull($fila->email_verified_at);
        $this->assertSame(1, DB::table('usuarios')->count());
    }

    public function test_up_es_idempotente_si_la_columna_ya_existe(): void
    {
        $this->salvaguarda();
        $migracion = require base_path(self::MIGRACION);

        $this->insertarCuentaPrevia(['email_verified_at' => '2026-01-01 10:00:00']);
        $columnas = Schema::getColumnListing('usuarios');

        $migracion->up(); // no debe lanzar "duplicate column"

        $this->assertSame($columnas, Schema::getColumnListing('usuarios'));
        $this->assertSame(1, collect(Schema::getColumnListing('usuarios'))->filter(fn ($c) => $c === 'email_verified_at')->count());
        $this->assertSame('2026-01-01 10:00:00', DB::table('usuarios')->where('cedula', 1234567)->value('email_verified_at'));
    }

    public function test_down_no_es_destructivo_y_conserva_columna_y_datos(): void
    {
        $this->salvaguarda();
        $migracion = require base_path(self::MIGRACION);

        $this->insertarCuentaPrevia(['email_verified_at' => '2026-01-01 10:00:00']);
        $this->insertarCuentaPrevia(['cedula' => 7654321, 'email' => 'otra@example.com']);

        $migracion->down();

        $this->assertTrue(Schema::hasColumn('usuarios', 'email_verified_at'));
        $this->assertSame('2026-01-01 10:00:00', DB::table('usuarios')->where('cedula', 1234567)->value('email_verified_at'));
        $this->assertNull(DB::table('usuarios')->where('cedula', 7654321)->value('email_verified_at'));
        $this->assertSame(2, DB::table('usuarios')->count());
    }
}
