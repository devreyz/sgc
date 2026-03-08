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
        Schema::table('sales_projects', function (Blueprint $table) {
            // Permite que o projeto receba qualquer produto cadastrado sem demandas pré-definidas
            $table->boolean('allow_any_product')->default(false)->after('notes')
                ->comment('Se true, o projeto aceita qualquer produto sem demandas específicas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn('allow_any_product');
        });
    }
};
