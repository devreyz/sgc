<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_billing_receipt_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_billing_receipt_id')
                ->constrained('customer_billing_receipts')->cascadeOnDelete();
            $table->foreignId('sales_project_id')
                ->constrained('sales_projects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['customer_billing_receipt_id', 'sales_project_id'],
                'customer_billing_receipt_project_unique',
            );
            $table->index(
                ['tenant_id', 'sales_project_id'],
                'customer_billing_receipt_project_tenant_idx',
            );
        });

        DB::table('customer_billing_receipts')
            ->whereNotNull('sales_project_id')
            ->orderBy('id')
            ->chunkById(500, function ($receipts): void {
                $now = now();
                DB::table('customer_billing_receipt_projects')->insertOrIgnore(
                    $receipts->map(fn ($receipt): array => [
                        'tenant_id' => $receipt->tenant_id,
                        'customer_billing_receipt_id' => $receipt->id,
                        'sales_project_id' => $receipt->sales_project_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_billing_receipt_projects');
    }
};
