<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulantes', function (Blueprint $table) {
            $table->id('id_postulante');
            $table->unsignedBigInteger('cedula')->unique(); // FK a usuarios, un perfil por trabajador
            $table->text('habilidades');
            $table->text('experiencia')->nullable();
            $table->string('disponibilidad', 100)->nullable();
            $table->decimal('tarifa', 10, 2)->nullable();
            $table->tinyInteger('estado')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulantes');
    }
};