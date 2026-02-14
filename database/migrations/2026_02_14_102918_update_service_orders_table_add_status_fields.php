<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Atualizar enum de status para incluir novos valores
        DB::statement("ALTER TABLE service_orders MODIFY COLUMN status ENUM('scheduled', 'in_progress', 'completed', 'awaiting_payment', 'paid', 'cancelled', 'billed') DEFAULT 'scheduled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para enum antigo (cuidado: pode perder dados se houver registros com novos status)
        DB::statement("ALTER TABLE service_orders MODIFY COLUMN status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'billed') DEFAULT 'scheduled'");
    }
};
