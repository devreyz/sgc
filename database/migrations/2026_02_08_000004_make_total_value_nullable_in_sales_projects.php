<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alterar coluna total_value para aceitar NULL
        DB::statement("ALTER TABLE `sales_projects` MODIFY `total_value` DECIMAL(12,2) NULL;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para NOT NULL com valor padrão 0.00
        DB::statement("ALTER TABLE `sales_projects` MODIFY `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00;");
    }
};
