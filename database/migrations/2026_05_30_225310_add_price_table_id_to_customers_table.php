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
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('price_table_id')
                ->nullable()
                ->after('organization_id')
                ->comment('Tabela de preços padrão deste cliente');
            $table->foreign('price_table_id')->references('id')->on('price_tables')->nullOnDelete();
            $table->index('price_table_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['price_table_id']);
            $table->dropIndex(['price_table_id']);
            $table->dropColumn('price_table_id');
        });
    }
};
