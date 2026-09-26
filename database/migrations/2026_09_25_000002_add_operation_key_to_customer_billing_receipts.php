<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_billing_receipts', function (Blueprint $table): void {
            $table->uuid('operation_key')->nullable()->after('created_by');
            $table->unique(['tenant_id', 'operation_key'], 'customer_billing_receipts_tenant_operation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_billing_receipts', function (Blueprint $table): void {
            $table->dropUnique('customer_billing_receipts_tenant_operation_unique');
            $table->dropColumn('operation_key');
        });
    }
};
