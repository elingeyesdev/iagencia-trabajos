
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {

            $table->unique(
                ['id_labor', 'cedula'],
                'postulacion_unica_por_trabajador'
            );

        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {

            $table->dropUnique(
                'postulacion_unica_por_trabajador'
            );

        });
    }
};
