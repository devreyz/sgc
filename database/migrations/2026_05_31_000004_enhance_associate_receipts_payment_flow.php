<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transforma AssociateReceipt na entidade central do fluxo financeiro.
 *
 * Mudanças em associate_receipts:
 *   - status: draft | pending_payment | paid
 *   - Campos financeiros congelados no momento da geração (snapshot)
 *   - Campos de pagamento (data, método, banco, documento, notas)
 *
 * Mudanças em production_deliveries:
 *   - associate_receipt_id: vínculo reverso distribuição → comprovante
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associate_receipts', function (Blueprint $table) {
            // ── Status do comprovante ──────────────────────────────────────
            $table->string('status', 30)->default('draft')->after('delivery_ids');

            // ── Snapshot financeiro congelado na geração ───────────────────
            $table->decimal('total_gross', 14, 4)->nullable()->after('status');
            $table->decimal('total_fees',  14, 4)->nullable()->after('total_gross');
            $table->decimal('total_net',   14, 4)->nullable()->after('total_fees');
            $table->json('fee_snapshot')->nullable()->after('total_net');

            // ── Dados do pagamento efetivo ─────────────────────────────────
            $table->timestamp('paid_at')->nullable()->after('fee_snapshot');
            $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            $table->string('payment_method', 50)->nullable()->after('paid_by');
            $table->unsignedBigInteger('bank_account_id')->nullable()->after('payment_method');
            $table->string('document_number', 100)->nullable()->after('bank_account_id');
            $table->text('payment_notes')->nullable()->after('document_number');

            $table->index('status', 'idx_associate_receipts_status');

            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
        });

        Schema::table('production_deliveries', function (Blueprint $table) {
            // Vínculo reverso: qual comprovante cobre esta distribuição
            $table->unsignedBigInteger('associate_receipt_id')->nullable()->after('distribution_billing_id');
            $table->index('associate_receipt_id', 'idx_prod_del_receipt');
        });
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropIndex('idx_prod_del_receipt');
            $table->dropColumn('associate_receipt_id');
        });

        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropForeign(['bank_account_id']);
            $table->dropIndex('idx_associate_receipts_status');
            $table->dropColumn([
                'status', 'total_gross', 'total_fees', 'total_net', 'fee_snapshot',
                'paid_at', 'paid_by', 'payment_method', 'bank_account_id',
                'document_number', 'payment_notes',
            ]);
        });
    }
};
