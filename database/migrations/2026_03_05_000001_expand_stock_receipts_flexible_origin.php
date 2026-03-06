<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expande Recebimentos Avulsos para aceitar qualquer origem:
 * - Fornecedor (já existia)
 * - Associado (com fluxo de ledger)
 * - Pessoa avulsa (nome livre)
 *
 * Também adiciona campos de qualidade e tipo de entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            // Origem flexível: tipo de quem entregou
            $table->string('origin_type', 30)->default('supplier')->after('supplier_id');
            // origin_type: 'supplier', 'associate', 'other'

            $table->foreignId('associate_id')->nullable()->after('origin_type')
                ->constrained('associates')->nullOnDelete();

            // Para origem avulsa (não-fornecedor, não-associado)
            $table->string('origin_name', 255)->nullable()->after('associate_id');
            $table->string('origin_document', 50)->nullable()->after('origin_name');
            $table->string('origin_phone', 20)->nullable()->after('origin_document');

            // Ledger do associado (referência ao lançamento gerado)
            $table->foreignId('associate_ledger_id')->nullable()->after('stock_movement_id')
                ->constrained('associate_ledgers')->nullOnDelete();

            // Controle de qualidade
            $table->string('quality_grade', 1)->nullable()->after('expiry_date');
            $table->text('quality_notes')->nullable()->after('quality_grade');

            $table->index(['origin_type']);
            $table->index(['associate_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropForeign(['associate_id']);
            $table->dropForeign(['associate_ledger_id']);
            $table->dropIndex(['origin_type']);
            $table->dropIndex(['associate_id']);
            $table->dropColumn([
                'origin_type', 'associate_id',
                'origin_name', 'origin_document', 'origin_phone',
                'associate_ledger_id',
                'quality_grade', 'quality_notes',
            ]);
        });
    }
};
