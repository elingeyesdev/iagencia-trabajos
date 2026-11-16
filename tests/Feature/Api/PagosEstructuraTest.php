<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Parametro;
use Database\Seeders\ParametrosSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tarea 3.1 — Estructura de datos de pagos simulados (sin endpoints ni servicios).
 */
class PagosEstructuraTest extends TestCase
{
    use RefreshDatabase;

    private function crearContratacion(int $idPostulacion = 1): Contratacion
    {
        return Contratacion::create([
            'id_postulacion' => $idPostulacion,
            'fecha_inicio'   => '2026-01-15',
            'monto_acordado' => 100,
            'estado'         => 'activa',
        ]);
    }

    private function pagoValido(int $idContratacion, array $cambios = []): Pago
    {
        return (new Pago())->forceFill(array_merge([
            'id_contratacion'    => $idContratacion,
            'monto'              => '40.00',
            'monto_neto'         => '40.00',
            'fecha_pago'         => '2026-02-01',
            'metodo'             => 'efectivo',
            'referencia_externa' => 'SIM-0001',
            'cedula_registra'    => 1001,
            'fecha_registro'     => '2026-02-01 10:00:00',
        ], $cambios));
    }

    public function test_tablas_y_columnas_existen(): void
    {
        $this->assertTrue(Schema::hasColumns('parametros', [
            'id_parametro', 'clave', 'valor', 'descripcion', 'fecha_actualizacion', 'cedula_actualiza',
        ]));
        $this->assertTrue(Schema::hasColumns('pagos', [
            'id_pago', 'id_contratacion', 'monto', 'porcentaje_comision', 'monto_comision', 'monto_neto',
            'fecha_pago', 'metodo', 'estado', 'es_simulado', 'proveedor', 'referencia_externa', 'observacion',
            'cedula_registra', 'fecha_registro', 'cedula_confirma', 'fecha_confirmacion',
            'cedula_anula', 'fecha_anulacion', 'motivo_anulacion',
        ]));
        $this->assertFalse(Schema::hasColumn('pagos', 'created_at'));
    }

    public function test_parametros_iniciales_de_la_migracion(): void
    {
        $this->assertSame('0.00', Parametro::valor('comision_porcentaje'));
        $this->assertSame('simulado', Parametro::valor('pagos_proveedor'));
        $this->assertSame('x', Parametro::valor('no_existe', 'x'));
        $this->assertSame(2, Parametro::count());
    }

    public function test_seeder_es_idempotente_y_no_sobrescribe_valores_cambiados(): void
    {
        Parametro::where('clave', 'comision_porcentaje')->update(['valor' => '5.00']);
        Parametro::where('clave', 'pagos_proveedor')->delete();

        $this->seed(ParametrosSeeder::class);
        $this->seed(ParametrosSeeder::class);

        $this->assertSame('5.00', Parametro::valor('comision_porcentaje'));
        $this->assertSame('simulado', Parametro::valor('pagos_proveedor'));
        $this->assertSame(2, Parametro::count());
    }

    public function test_clave_de_parametro_unica(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        Parametro::create(['clave' => 'comision_porcentaje', 'valor' => '1.00']);
    }

    public function test_pago_valido_con_valores_por_defecto_y_casts(): void
    {
        $contratacion = $this->crearContratacion();
        // Sin estado, es_simulado, proveedor ni comisión: aplican los valores por defecto.
        $pago = $this->pagoValido($contratacion->id_contratacion);
        $pago->save();

        $pago = Pago::findOrFail($pago->id_pago);
        $this->assertSame('pendiente', $pago->estado);
        $this->assertTrue($pago->es_simulado);
        $this->assertSame('simulado', $pago->proveedor);
        $this->assertSame('0.00', $pago->porcentaje_comision);
        $this->assertSame('40.00', $pago->monto_neto);
        $this->assertSame('2026-02-01', $pago->fecha_pago->toDateString());

        $this->assertTrue($contratacion->tieneHistorialFinanciero());
        $this->assertSame(1, $contratacion->pagos()->count());
        $this->assertSame($contratacion->id_contratacion, $pago->contratacion->id_contratacion);
    }

    public function test_fillable_no_incluye_campos_de_control(): void
    {
        $pago = new Pago([
            'monto' => 10, 'metodo' => 'efectivo', 'estado' => 'confirmado', 'es_simulado' => false,
            'proveedor' => 'banco', 'referencia_externa' => 'X', 'monto_neto' => 10,
            'porcentaje_comision' => 50, 'cedula_confirma' => 1, 'fecha_confirmacion' => now(),
        ]);

        $this->assertSame('pendiente', $pago->estado);
        $this->assertTrue($pago->es_simulado);
        $this->assertSame('simulado', $pago->proveedor);
        $this->assertNull($pago->referencia_externa);
        $this->assertNull($pago->monto_neto);
        $this->assertSame('0.00', $pago->porcentaje_comision);
        $this->assertNull($pago->cedula_confirma);
    }

    public function test_referencia_unica_por_proveedor(): void
    {
        $id = $this->crearContratacion()->id_contratacion;
        $this->pagoValido($id)->save();
        // Misma referencia con otro proveedor: permitido.
        $this->pagoValido($id, ['proveedor' => 'otro'])->save();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->pagoValido($id)->save();
    }

    public function test_fk_impide_pago_de_contratacion_inexistente(): void
    {
        $this->expectException(QueryException::class);
        $this->pagoValido(999)->save();
    }

    public function test_fk_impide_borrar_contratacion_con_pagos(): void
    {
        $contratacion = $this->crearContratacion();
        $this->pagoValido($contratacion->id_contratacion)->save();

        try {
            $contratacion->delete();
            $this->fail('Se esperaba violación de clave foránea.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertTrue(DB::table('contrataciones')->where('id_contratacion', $contratacion->id_contratacion)->exists());
    }

    public static function pagosInvalidos(): array
    {
        return [
            'monto cero'          => [['monto' => '0.00', 'monto_neto' => '0.00']],
            'monto negativo'      => [['monto' => '-5.00', 'monto_neto' => '-5.00']],
            'estado no permitido' => [['estado' => 'pagado']],
            'metodo no permitido' => [['metodo' => 'bitcoin']],
            'porcentaje > 100'    => [['porcentaje_comision' => '100.01']],
            'comision negativa'   => [['monto_comision' => '-1.00', 'monto_neto' => '41.00']],
            'comision > monto'    => [['monto_comision' => '50.00', 'monto_neto' => '-10.00']],
            'neto incoherente'    => [['monto_comision' => '4.00', 'monto_neto' => '40.00']],
            'neto ausente'        => [['monto_neto' => null]],
            'referencia vacia'    => [['referencia_externa' => '  ']],
        ];
    }

    #[DataProvider('pagosInvalidos')]
    public function test_validacion_de_dominio_rechaza_pagos_incoherentes(array $cambios): void
    {
        $id = $this->crearContratacion()->id_contratacion;

        try {
            $this->pagoValido($id, $cambios)->save();
            $this->fail('Se esperaba DomainException.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertSame(0, Pago::count());
    }

    public function test_metodos_aprobados_exactos_y_tarjeta_rechazada(): void
    {
        $this->assertSame(['efectivo', 'transferencia', 'qr', 'deposito', 'otro'], Pago::METODOS);

        $id = $this->crearContratacion()->id_contratacion;
        foreach (Pago::METODOS as $i => $metodo) {
            $this->pagoValido($id, ['metodo' => $metodo, 'referencia_externa' => "SIM-M{$i}"])->save();
        }
        $this->assertSame(5, Pago::count());

        try {
            $this->pagoValido($id, ['metodo' => 'tarjeta', 'referencia_externa' => 'SIM-TARJETA'])->save();
            $this->fail('Se esperaba DomainException para tarjeta.');
        } catch (DomainException) {
            // esperado
        }
        $this->assertSame(5, Pago::count());
    }

    public function test_neto_coherente_con_comision_en_centavos(): void
    {
        $id = $this->crearContratacion()->id_contratacion;
        $this->pagoValido($id, [
            'monto' => '33.33', 'porcentaje_comision' => '10.00', 'monto_comision' => '3.33', 'monto_neto' => '30.00',
        ])->save();

        $this->assertSame(1, Pago::count());
    }

    public function test_scopes(): void
    {
        $id = $this->crearContratacion()->id_contratacion;
        $this->pagoValido($id, ['referencia_externa' => 'A'])->save();
        $this->pagoValido($id, ['referencia_externa' => 'B', 'estado' => 'anulado'])->save();
        $this->pagoValido($id, ['referencia_externa' => 'C', 'es_simulado' => false, 'proveedor' => 'externo'])->save();

        $this->assertSame(2, Pago::noAnulados()->count());
        $this->assertSame(2, Pago::simulados()->count());
        $this->assertSame(1, Pago::reales()->count());
    }
}
