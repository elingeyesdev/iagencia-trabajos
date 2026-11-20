<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de asignaciones de roles (módulo Asignar Rol).
 *
 * Aditiva: SOLO crea la tabla nueva y vacía; no modifica usuarios ni las tablas de Spatie.
 * Registro de solo inserción (sin updated_at): cada fila es un cambio de rol ya confirmado.
 *
 * - usuario_cedula / admin_cedula: INTEGER, mismo tipo que usuarios.cedula
 *   ($table->integer('cedula')->primary() en 0001_01_01_000000_create_users_table.php).
 * - rol_anterior / rol_nuevo: VARCHAR(255), mismo largo que roles.name
 *   ($table->string('name') sin longitud en 2026_05_16_012406_create_permission_tables.php,
 *   y la app no fija Schema::defaultStringLength → 255).
 * - Snapshots de nombres (usuario_nombre, admin_nombre, roles por nombre): el historial se
 *   conserva legible aunque el usuario o el rol se editen o se borren después.
 * - SIN claves foráneas: convención del proyecto (las tablas de dominio no usan FKs hacia
 *   usuarios; los usuarios se pueden borrar físicamente y su cédula puede editarse) y un
 *   historial de auditoría no debe perder ni bloquear filas por cambios en usuarios/roles.
 * - accion: asignacion | cambio | retirada (valores internos; etiquetas en español en la UI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol_asignaciones_historial', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('usuario_cedula');
            $table->string('usuario_nombre');
            $table->string('rol_anterior')->nullable();
            $table->string('rol_nuevo')->nullable();
            $table->string('accion', 20);
            $table->integer('admin_cedula')->nullable();
            $table->string('admin_nombre')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['usuario_cedula', 'created_at'], 'rah_usuario_fecha_index');
            $table->index(['admin_cedula', 'created_at'], 'rah_admin_fecha_index');
            $table->index(['created_at', 'id'], 'rah_fecha_id_index');
            $table->index('accion', 'rah_accion_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_asignaciones_historial');
    }
};
