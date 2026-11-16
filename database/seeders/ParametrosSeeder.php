<?php

namespace Database\Seeders;

use App\Models\Parametro;
use Illuminate\Database\Seeder;

/**
 * Parámetros iniciales (idempotente).
 *
 * firstOrCreate por clave: crea el parámetro solo si no existe y nunca
 * sobrescribe un valor ya cambiado por el administrador.
 * Ejecución manual: php artisan db:seed --class=ParametrosSeeder
 */
class ParametrosSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            'comision_porcentaje' => ['valor' => '0.00', 'descripcion' => 'Porcentaje de comisión de RedWork aplicado a cada pago.'],
            'pagos_proveedor'     => ['valor' => 'simulado', 'descripcion' => 'Proveedor de procesamiento de pagos activo.'],
        ];

        foreach ($parametros as $clave => $datos) {
            $parametro = Parametro::firstOrNew(['clave' => $clave]);

            if (! $parametro->exists) {
                $parametro->fill($datos);
                $parametro->forceFill(['fecha_actualizacion' => now()])->save();
            }
        }
    }
}
