<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrataciones', function (Blueprint $table) {
            $table->id('id_contratacion');
            $table->unsignedBigInteger('id_postulacion')->unique(); // 1 contratación por postulación
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->decimal('monto_acordado', 10, 2);
            $table->string('estado', 20)->default('activa'); // activa|finalizada|cancelada
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrataciones');
    }
};