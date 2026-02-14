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
        // Adicionar campos na tabela services para diferenciar preços
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('associate_price', 10, 2)->nullable()->after('base_price')->comment('Preço para associados');
            $table->decimal('non_associate_price', 10, 2)->nullable()->after('associate_price')->comment('Preço para não-associados');
        });
        
        // Atualizar tabela service_orders
        Schema::table('service_orders', function (Blueprint $table) {
            // Tornar associate_id nullable para permitir pessoas avulsas
            $table->foreignId('associate_id')->nullable()->change();
            
            // Adicionar coluna service_provider_id se não existir
            if (!Schema::hasColumn('service_orders', 'service_provider_id')) {
                $table->foreignId('service_provider_id')->nullable()->after('associate_id')->constrained('service_providers')->nullOnDelete();
            }
            
            // Adicionar coluna receipt_path para comprovantes
            $table->string('receipt_path')->nullable()->after('work_description')->comment('Caminho do comprovante');
            
            // Adicionar coluna actual_quantity para quantidade efetivamente executada
            if (!Schema::hasColumn('service_orders', 'actual_quantity')) {
                $table->decimal('actual_quantity', 10, 2)->nullable()->after('quantity')->comment('Quantidade executada');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['associate_price', 'non_associate_price']);
        });
        
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn(['receipt_path']);
            
            if (Schema::hasColumn('service_orders', 'service_provider_id')) {
                $table->dropForeign(['service_provider_id']);
                $table->dropColumn('service_provider_id');
            }
            
            if (Schema::hasColumn('service_orders', 'actual_quantity')) {
                $table->dropColumn('actual_quantity');
            }
            
            // Reverter associate_id para NOT NULL (cuidado: pode falhar se houver dados)
            // $table->foreignId('associate_id')->nullable(false)->change();
        });
    }
};
