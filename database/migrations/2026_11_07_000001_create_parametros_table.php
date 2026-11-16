<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarea 3.1 — Parámetros del sistema (clave/valor).
 *
 * Aditiva: crea una tabla nueva y no toca datos existentes.
 * Los valores iniciales se insertan de forma idempotente (insertOrIgnore
 * sobre la clave única), de modo que volver a ejecutar la migración o el
 * ParametrosSeeder nunca sobrescribe un valor ya cambiado por el administrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->bigIncrements('id_parametro');
            $table->string('clave', 50);
            $table->string('valor', 255);
            $table->string('descripcion', 255)->nullable();
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->unsignedBigInteger('cedula_actualiza')->nullable(); // usuarios.cedula (sin FK, convención del dominio)

            $table->unique('clave', 'parametros_clave_unique');
        });

        $ahora = now();

        DB::table('parametros')->insertOrIgnore([
            [
                'clave'               => 'comision_porcentaje',
                'valor'               => '0.00',
                'descripcion'         => 'Porcentaje de comisión de RedWork aplicado a cada pago.',
                'fecha_actualizacion' => $ahora,
                'cedula_actualiza'    => null,
            ],
            [
                'clave'               => 'pagos_proveedor',
                'valor'               => 'simulado',
                'descripcion'         => 'Proveedor de procesamiento de pagos activo.',
                'fecha_actualizacion' => $ahora,
                'cedula_actualiza'    => null,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
