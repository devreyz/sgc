<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associate_receipt_payments', function (Blueprint $table): void {
            $table->uuid('operation_key')->nullable()->after('associate_receipt_id');
            $table->unique(['tenant_id', 'operation_key'], 'arp_tenant_operation_unique');
        });

        Schema::table('customer_receipt_payments', function (Blueprint $table): void {
            $table->uuid('operation_key')->nullable()->after('customer_billing_receipt_id');
            $table->unique(['tenant_id', 'operation_key'], 'crp_tenant_operation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('associate_receipt_payments', function (Blueprint $table): void {
            $table->dropUnique('arp_tenant_operation_unique');
            $table->dropColumn('operation_key');
        });

        Schema::table('customer_receipt_payments', function (Blueprint $table): void {
            $table->dropUnique('crp_tenant_operation_unique');
            $table->dropColumn('operation_key');
        });
    }
};
