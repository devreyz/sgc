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
        Schema::create('project_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_project_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'client_payment' ou 'associate_payment'
            $table->string('status')->default('pending'); // pending, deposited, paid, cancelled
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('payment_date')->nullable();
            $table->date('expected_date')->nullable();
            
            // Para pagamento do cliente
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->string('document_number')->nullable();
            
            // Para pagamento ao associado
            $table->foreignId('associate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_delivery_id')->nullable()->constrained()->nullOnDelete();
            
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sales_project_id', 'status']);
            $table->index('associate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_payments');
    }
};
