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
        Schema::table('service_orders', function (Blueprint $table) {
            $table->decimal('actual_quantity', 10, 2)
                ->nullable()
                ->after('quantity')
                ->comment('Quantidade efetivamente executada pelo prestador');

            $table->decimal('provider_payment', 12, 2)
                ->default(0)
                ->after('final_price')
                ->comment('Valor a pagar ao prestador pelo serviço');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn(['actual_quantity', 'provider_payment']);
        });
    }
};
