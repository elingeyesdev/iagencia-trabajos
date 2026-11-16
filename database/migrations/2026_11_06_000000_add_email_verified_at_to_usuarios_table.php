<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usuarios', 'email_verified_at')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->timestamp('email_verified_at')
                    ->nullable()
                    ->after('email');
            });
        }
    }

    public function down(): void
    {
        // No eliminar la columna porque ya existía
        // previamente en algunas bases de datos.
    }
};