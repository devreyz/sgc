<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_deliveries')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('production_deliveries'))->pluck('name');
        Schema::table('production_deliveries', function (Blueprint $table) use ($indexes): void {
            if (! $indexes->contains('pd_billing_period_customer_idx')) {
                $table->index(
                    ['tenant_id', 'sales_project_id', 'customer_id', 'delivery_date', 'billing_receipt_id'],
                    'pd_billing_period_customer_idx',
                );
            }
            if (! $indexes->contains('pd_associate_receipt_tenant_idx')) {
                $table->index(
                    ['tenant_id', 'associate_receipt_id', 'billing_receipt_id'],
                    'pd_associate_receipt_tenant_idx',
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_deliveries')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('production_deliveries'))->pluck('name');
        Schema::table('production_deliveries', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains('pd_billing_period_customer_idx')) {
                $table->dropIndex('pd_billing_period_customer_idx');
            }
            if ($indexes->contains('pd_associate_receipt_tenant_idx')) {
                $table->dropIndex('pd_associate_receipt_tenant_idx');
            }
        });
    }
};
