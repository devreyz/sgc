<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite entregas avulsas (standalone) — sem projeto de venda vinculado.
 * Torna sales_project_id e project_demand_id nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->foreignId('sales_project_id')->nullable()->change();
            $table->foreignId('project_demand_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->foreignId('sales_project_id')->nullable(false)->change();
            $table->foreignId('project_demand_id')->nullable(false)->change();
        });
    }
};
