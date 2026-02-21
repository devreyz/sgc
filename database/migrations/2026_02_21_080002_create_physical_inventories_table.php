<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventários Físicos
 * - physical_inventories: cabeçalho do inventário
 * - physical_inventory_items: itens contados
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_inventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            $table->string('description')->nullable();
            $table->date('inventory_date');
            $table->enum('status', ['draft', 'counting', 'adjusting', 'completed', 'cancelled'])
                ->default('draft');

            $table->text('notes')->nullable();

            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
        });

        Schema::create('physical_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_inventory_id')
                ->constrained('physical_inventories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Saldo teórico (coletado no momento da abertura do inventário)
            $table->decimal('expected_quantity', 12, 3);

            // Saldo informado pelo usuário
            $table->decimal('actual_quantity', 12, 3)->nullable();

            // Diferença gerada
            $table->decimal('difference', 12, 3)->nullable()
                ->comment('actual - expected. Positivo = sobra. Negativo = falta.');

            // Movimento de ajuste gerado ao fechar
            $table->foreignId('adjustment_movement_id')
                ->nullable()->constrained('stock_movements')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['physical_inventory_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_inventory_items');
        Schema::dropIfExists('physical_inventories');
    }
};
