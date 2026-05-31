<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Tabela principal de comprovantes de cobrança ao cliente ──────────
        Schema::create('customer_billing_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('sales_project_id')->nullable()->index();

            // Destinatário: cliente OU organização (exclusivos, validação no service)
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('organization_id')->nullable()->index();

            // Numeração sequencial por tenant/ano
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('receipt_number');
            $table->unique(['tenant_id', 'receipt_year', 'receipt_number'], 'uniq_cob_number');

            // Período de referência
            $table->date('issued_at');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();

            // Status do fluxo financeiro
            $table->string('status', 30)->default('draft')->index();

            // Snapshot financeiro congelado (preenchido em freezeReceipt)
            $table->decimal('total_gross', 14, 4)->nullable();
            $table->decimal('total_fees',  14, 4)->nullable();   // acréscimos/descontos do lado cliente
            $table->decimal('total_net',   14, 4)->nullable();
            $table->json('fee_snapshot')->nullable();            // taxas do cliente no momento da geração

            // IDs das distribuições incluídas (snapshot de seleção)
            $table->json('delivery_ids')->nullable();

            // Dados do recebimento efetivo
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('document_number', 100)->nullable();
            $table->text('payment_notes')->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // FKs
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('sales_project_id')->references('id')->on('sales_projects')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── billing_receipt_id em production_deliveries ─────────────────────
        // Uma distribuição só pode estar em UM comprovante de cliente.
        // Garantido por: unique index parcial (NOT NULL) + validação no service.
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('billing_receipt_id')
                ->nullable()
                ->after('associate_receipt_id');

            $table->foreign('billing_receipt_id')
                ->references('id')->on('customer_billing_receipts')
                ->nullOnDelete();

            $table->index('billing_receipt_id', 'idx_prod_del_billing_receipt');
        });
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropForeign(['billing_receipt_id']);
            $table->dropIndex('idx_prod_del_billing_receipt');
            $table->dropColumn('billing_receipt_id');
        });

        Schema::dropIfExists('customer_billing_receipts');
    }
};
