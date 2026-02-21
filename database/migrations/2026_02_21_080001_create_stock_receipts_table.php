<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recebimentos Avulsos (Entrada de estoque sem projeto)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            // Produto e fornecedor (opcional)
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // Valores
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();

            // Controle
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->date('receipt_date');
            $table->string('batch')->nullable();
            $table->date('expiry_date')->nullable();

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
            $table->index('receipt_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipts');
    }
};
