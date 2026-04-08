<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->index(['tenant_id', 'receipt_year'], 'idx_associate_receipts_tenant_year');
            $table->index(['tenant_id', 'associate_id'], 'idx_associate_receipts_tenant_associate');
        });
    }

    public function down(): void
    {
        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->dropIndex('idx_associate_receipts_tenant_year');
            $table->dropIndex('idx_associate_receipts_tenant_associate');
        });
    }
};
