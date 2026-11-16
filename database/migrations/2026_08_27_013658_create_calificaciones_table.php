<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calificaciones', function (Blueprint $table) {
            $table->id('id_calificacion');
            $table->unsignedBigInteger('id_contratacion');
            $table->unsignedBigInteger('cedula_calificador');
            $table->unsignedBigInteger('cedula_calificado');
            $table->unsignedTinyInteger('puntuacion'); // 1-5
            $table->text('comentario')->nullable();
            $table->date('fecha');

            $table->index('id_contratacion');
            $table->unique(['id_contratacion', 'cedula_calificador'], 'calificacion_unica_por_contratacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calificaciones');
    }
};