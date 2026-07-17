<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'sales_project_id', 'associate_id', 'parent_delivery_id', 'status'],
                'pd_tenant_project_assoc_parent_status_idx'
            );
            $table->index(
                ['tenant_id', 'parent_delivery_id', 'status'],
                'pd_tenant_parent_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropIndex('pd_tenant_project_assoc_parent_status_idx');
            $table->dropIndex('pd_tenant_parent_status_idx');
        });
    }
};
