<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Atualizar enum `status` da tabela `sales_projects` para incluir novos estados
        DB::statement("ALTER TABLE `sales_projects` MODIFY `status` ENUM('draft','active','suspended','awaiting_delivery','delivered','awaiting_payment','payment_received','associates_paid','completed','cancelled') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para os estados originais (sem os novos)
        DB::statement("ALTER TABLE `sales_projects` MODIFY `status` ENUM('draft','active','suspended','completed','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
