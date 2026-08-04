<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('receipt_year')->nullable();
            $table->unsignedBigInteger('receipt_number')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('payer_type', 30)->default('other');
            $table->string('payer_name');
            $table->string('payer_document', 30)->nullable();
            $table->string('payer_contact')->nullable();
            $table->date('received_on');
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('chart_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 30);
            $table->string('payment_reference')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cash_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_movement_id')->nullable()->constrained('cash_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_year', 'receipt_number'], 'financial_receipts_number_unique');
            $table->index(['tenant_id', 'status', 'received_on']);
        });

        Schema::create('financial_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->text('description');
            $table->decimal('quantity', 15, 4)->default(1);
            $table->string('unit', 30)->default('un');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_amount', 15, 2);
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->index(['financial_receipt_id', 'position']);
        });

        Schema::create('financial_receipt_counters', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_receipt_counters');
        Schema::dropIfExists('financial_receipt_items');
        Schema::dropIfExists('financial_receipts');
    }
};
