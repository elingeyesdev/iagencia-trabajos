<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulaciones', function (Blueprint $table) {
            $table->id('id_postulacion');
            $table->unsignedBigInteger('id_labor');   // FK publicaciones.id_labor
            $table->unsignedBigInteger('cedula');      // FK usuarios.cedula (trabajador)
            $table->text('mensaje')->nullable();
            $table->date('fecha_postulacion');
            $table->string('estado', 20)->default('pendiente'); // pendiente|aceptada|rechazada|cancelada

            $table->index('id_labor');
            $table->index('cedula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulaciones');
    }
};