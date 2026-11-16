<?php

namespace Tests\Feature\Api;

use App\Models\Pago;
use App\Models\Parametro;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tarea 3.2 — Cálculo de comisiones (Pago::calcularComision y Parametro::comisionVigente; aritmética en centavos enteros).
 */
class ComisionPagoTest extends TestCase
{
    use RefreshDatabase;

    public static function casos(): array
    {
        return [
            '0 %'            => ['100.00', '0.00', '0.00', '0.00', '100.00'],
            '10 %'           => ['100.00', '10', '10.00', '10.00', '90.00'],
            '12,5 %'         => ['80.00', '12.5', '12.50', '10.00', '70.00'],
            '12,5 % redondeo half-up' => ['0.10', '12.50', '12.50', '0.01', '0.09'],  // 1.25 centavos → 1
            '10 % de 0.05 (0.5 centavos → 1)' => ['0.05', '10.00', '10.00', '0.01', '0.04'],
            '100 %'          => ['55.55', '100', '100.00', '55.55', '0.00'],
            'monto entero'   => [7, '10.00', '10.00', '0.70', '6.30'],
            'monto máximo'   => ['99999999.99', '12.50', '12.50', '12500000.00', '87499999.99'],
        ];
    }

    #[DataProvider('casos')]
    public function test_calcula_comision_y_neto(string|int $monto, string $porcentaje, string $p, string $comision, string $neto): void
    {
        $this->assertSame(
            ['porcentaje_comision' => $p, 'monto_comision' => $comision, 'monto_neto' => $neto],
            Pago::calcularComision($monto, $porcentaje)
        );
    }

    public static function invalidos(): array
    {
        return [
            'monto cero'          => ['0.00', '0'],
            'monto negativo'      => ['-1.00', '0'],
            'monto 3 decimales'   => ['1.005', '0'],
            'porcentaje > 100'    => ['10.00', '100.01'],
            'porcentaje negativo' => ['10.00', '-1'],
            'porcentaje texto'    => ['10.00', 'abc'],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_rechaza_valores_invalidos(string $monto, string $porcentaje): void
    {
        $this->expectException(DomainException::class);
        Pago::calcularComision($monto, $porcentaje);
    }

    public function test_porcentaje_vigente_lee_y_normaliza_el_parametro(): void
    {
        $this->assertSame('0.00', Parametro::comisionVigente());

        Parametro::where('clave', 'comision_porcentaje')->update(['valor' => '12.5']);
        $this->assertSame('12.50', Parametro::comisionVigente());

        Parametro::where('clave', 'comision_porcentaje')->delete();
        $this->assertSame('0.00', Parametro::comisionVigente());
    }

    public function test_porcentaje_vigente_invalido_lanza_excepcion_de_dominio(): void
    {
        Parametro::where('clave', 'comision_porcentaje')->update(['valor' => '150']);

        $this->expectException(DomainException::class);
        Parametro::comisionVigente();
    }
}
