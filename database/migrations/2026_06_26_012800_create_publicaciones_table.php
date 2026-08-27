<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('publicaciones', function (Blueprint $table) {
        $table->id('id_labor');
        $table->string('tipo');
        $table->string('titulo');
        $table->text('descripcion');
        $table->decimal('pago', 10, 2);
        $table->string('lugar');
        $table->date('fecha_publicacion');
        $table->unsignedBigInteger('cedula'); // FK a usuarios
        $table->unsignedBigInteger('id_categoria');
        $table->tinyInteger('estado')->default(0);
    });
}

public function down(): void
{
    Schema::dropIfExists('publicaciones');
}
};  
