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
        Schema::table('associate_receipts', function (Blueprint $table) {
            // IDs das entregas selecionadas ao gerar o comprovante
            $table->json('delivery_ids')->nullable()->after('acknowledged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->dropColumn('delivery_ids');
        });
    }
};
