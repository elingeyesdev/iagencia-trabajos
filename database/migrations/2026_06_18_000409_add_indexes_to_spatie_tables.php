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
    // Índice en model_has_roles — lo consulta Spatie en cada request
    Schema::table('model_has_roles', function (Blueprint $table) {
        $table->index(['model_id', 'model_type'], 'model_has_roles_model_idx');
    });

    // Índice en personal_access_tokens — lo consulta Sanctum en cada request
    Schema::table('personal_access_tokens', function (Blueprint $table) {
        $table->index('tokenable_id', 'pat_tokenable_idx');
    });
}

public function down(): void
{
    Schema::table('model_has_roles', function (Blueprint $table) {
        $table->dropIndex('model_has_roles_model_idx');
    });
    Schema::table('personal_access_tokens', function (Blueprint $table) {
        $table->dropIndex('pat_tokenable_idx');
    });
}
};
