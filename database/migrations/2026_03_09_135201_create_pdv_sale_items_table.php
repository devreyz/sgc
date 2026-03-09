<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Itens da venda PDV
        Schema::create('pdv_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdv_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('pdv_sale_id');
        });

        // Pagamentos da venda (múltiplos métodos)
        Schema::create('pdv_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdv_sale_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 30); // usa PaymentMethod enum
            $table->decimal('amount', 12, 2);
            $table->foreignId('cash_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('pdv_sale_id');
        });

        // Fiado - parcelas / pagamentos posteriores
        Schema::create('pdv_fiado_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdv_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30);
            $table->decimal('interest_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['pdv_sale_id']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_fiado_payments');
        Schema::dropIfExists('pdv_sale_payments');
        Schema::dropIfExists('pdv_sale_items');
    }
};
