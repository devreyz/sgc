<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tornar a coluna `date` anulável para evitar erros quando não for informada
        DB::statement('ALTER TABLE `expenses` MODIFY `date` DATE NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar NOT NULL (define um valor padrão se necessário)
        DB::statement('ALTER TABLE `expenses` MODIFY `date` DATE NOT NULL');
    }
};
