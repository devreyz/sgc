<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clientes do PDV (compradores)
        Schema::create('pdv_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cpf_cnpj', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('credit_balance', 12, 2)->default(0); // saldo devedor fiado
            $table->text('notes')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'cpf_cnpj']);
        });

        // Vendas PDV (cabeçalho)
        Schema::create('pdv_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique(); // ex: PDV-20260309-001
            $table->foreignId('pdv_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable(); // nome avulso se não cadastrado
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->enum('status', ['open', 'completed', 'cancelled'])->default('open');
            $table->boolean('is_fiado')->default(false);
            $table->date('fiado_due_date')->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0); // juros % para fiado
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'is_fiado']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_sales');
        Schema::dropIfExists('pdv_customers');
    }
};
