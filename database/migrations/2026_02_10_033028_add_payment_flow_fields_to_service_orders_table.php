<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adiciona campos para controlar o fluxo de pagamento completo:
     * 1. Associado paga pelo serviço (débito do associado)
     * 2. Prestador recebe pelo trabalho (crédito do prestador)
     * 3. Diferença fica como saldo da cooperativa
     */
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            // Pagamento do Associado (ele paga pelo serviço recebido)
            $table->enum('associate_payment_status', ['pending', 'paid', 'cancelled'])
                ->default('pending')
                ->after('final_price')
                ->comment('Status do pagamento do associado pelo serviço');

            $table->timestamp('associate_paid_at')->nullable()->after('associate_payment_status')
                ->comment('Quando o associado pagou pelo serviço');

            $table->foreignId('associate_payment_id')->nullable()->after('associate_paid_at')
                ->constrained('cash_movements')->nullOnDelete()
                ->comment('Referência ao movimento de caixa do pagamento do associado');

            // Pagamento ao Prestador (ele recebe pelo trabalho executado)
            $table->enum('provider_payment_status', ['pending', 'paid', 'cancelled'])
                ->default('pending')
                ->after('associate_payment_id')
                ->comment('Status do pagamento ao prestador pelo trabalho');

            $table->timestamp('provider_paid_at')->nullable()->after('provider_payment_status')
                ->comment('Quando o prestador foi pago');

            $table->foreignId('provider_payment_id')->nullable()->after('provider_paid_at')
                ->constrained('cash_movements')->nullOnDelete()
                ->comment('Referência ao movimento de caixa do pagamento ao prestador');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['associate_payment_id']);
            $table->dropForeign(['provider_payment_id']);
            $table->dropColumn([
                'associate_payment_status',
                'associate_paid_at',
                'associate_payment_id',
                'provider_payment_status',
                'provider_paid_at',
                'provider_payment_id',
            ]);
        });
    }
};
