<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendas Rápidas (Caixa)
 * Vendas sem vínculo a projeto de venda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            // Produto e cliente
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Valores
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_value', 12, 2)->storedAs('quantity * unit_price');
            $table->string('payment_method', 30)->default('dinheiro')
                ->comment('dinheiro|pix|cartao_debito|cartao_credito|outro');

            // Controle
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->date('sale_date');

            // Movimento de estoque gerado ao confirmar
            $table->foreignId('stock_movement_id')
                ->nullable()->constrained('stock_movements')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('sale_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_sales');
    }
};
