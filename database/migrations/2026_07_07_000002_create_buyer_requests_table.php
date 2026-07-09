<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sales_project_id')->constrained('sales_projects')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'partially_fulfilled', 'fulfilled', 'exceeded', 'cancelled'])->default('open');
            $table->boolean('enforce_request_limits')
                ->default(false)
                ->comment('Bloqueia distribuicoes acima das quantidades solicitadas nesta requisicao.');
            $table->date('reference_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'organization_id', 'sales_project_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('buyer_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('buyer_request_id')->constrained('buyer_requests')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('requested_quantity', 14, 4);
            $table->decimal('unit_price_snapshot', 14, 4)->nullable();
            $table->foreignId('price_table_id')->nullable()->constrained('price_tables')->nullOnDelete();
            $table->string('price_source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'customer_id', 'product_id']);
            $table->index(['buyer_request_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_request_items');
        Schema::dropIfExists('buyer_requests');
    }
};
