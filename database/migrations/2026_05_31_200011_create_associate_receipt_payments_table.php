<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos parciais de comprovantes de associado (AssociateReceipt).
 * Cada linha representa um pagamento feito ao associado, permitindo
 * múltiplos repasses / formas diferentes para um mesmo comprovante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('associate_receipt_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('associate_receipt_id');

            $table->decimal('amount', 10, 2)->comment('Valor pago neste registro');
            $table->date('payment_date');
            $table->string('payment_method', 50)->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('document_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('associate_receipt_id')
                ->references('id')->on('associate_receipts')->cascadeOnDelete();
            $table->foreign('bank_account_id')
                ->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('created_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'associate_receipt_id'], 'arp_tenant_receipt_idx');
        });

        // Adiciona rastreamento de valor já pago no comprovante
        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->decimal('amount_paid', 10, 2)->default(0)->after('total_net');
        });
    }

    public function down(): void
    {
        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });
        Schema::dropIfExists('associate_receipt_payments');
    }
};
