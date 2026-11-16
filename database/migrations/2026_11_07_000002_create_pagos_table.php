<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarea 3.1 — Pagos (simulados o externos) de una contratación.
 *
 * Aditiva: tabla nueva y vacía; no modifica `contrataciones` ni sus datos.
 * Sin timestamps (convención del dominio): las fechas de auditoría son
 * columnas dateTime propias.
 *
 * - FK id_contratacion → contrataciones.id_contratacion con RESTRICT al borrar:
 *   ambos son BIGINT UNSIGNED (contrataciones usa $table->id()). Refuerza a nivel
 *   de BD que una contratación con pagos no se puede borrar físicamente.
 * - Sin FKs hacia usuarios (usuarios.cedula es INT y los usuarios se borran
 *   físicamente; se conserva la cédula como dato histórico).
 * - referencia_externa NOT NULL: en MySQL un UNIQUE admite múltiples NULL, por lo
 *   que solo con NOT NULL la unicidad (proveedor, referencia_externa) es efectiva.
 * - Las restricciones de dominio (monto > 0, estados, métodos, coherencia de
 *   montos) se validan en el modelo App\Models\Pago (CHECK no es portable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->bigIncrements('id_pago');
            $table->unsignedBigInteger('id_contratacion');

            $table->decimal('monto', 10, 2);
            $table->decimal('porcentaje_comision', 5, 2)->default(0); // copia del parámetro vigente al registrar
            $table->decimal('monto_comision', 10, 2)->default(0);
            $table->decimal('monto_neto', 10, 2);                    // monto - monto_comision

            $table->date('fecha_pago');
            $table->string('metodo', 20);
            $table->string('estado', 20)->default('pendiente');      // pendiente|confirmado|anulado

            $table->boolean('es_simulado')->default(true);
            $table->string('proveedor', 30)->default('simulado');
            $table->string('referencia_externa', 100);               // identificador único de la operación
            $table->string('observacion', 255)->nullable();

            // Auditoría (cédulas de usuarios.cedula, sin FK)
            $table->unsignedBigInteger('cedula_registra');
            $table->dateTime('fecha_registro');
            $table->unsignedBigInteger('cedula_confirma')->nullable();
            $table->dateTime('fecha_confirmacion')->nullable();
            $table->unsignedBigInteger('cedula_anula')->nullable();
            $table->dateTime('fecha_anulacion')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();

            $table->index('id_contratacion', 'pagos_id_contratacion_index');
            $table->index(['estado', 'fecha_pago'], 'pagos_estado_fecha_pago_index');
            $table->index('es_simulado', 'pagos_es_simulado_index');
            $table->index('cedula_registra', 'pagos_cedula_registra_index');
            $table->unique(['proveedor', 'referencia_externa'], 'pagos_proveedor_referencia_unique');

            $table->foreign('id_contratacion', 'pagos_id_contratacion_foreign')
                ->references('id_contratacion')
                ->on('contrataciones')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
