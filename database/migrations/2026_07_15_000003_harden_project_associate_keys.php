<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE project_associates pa INNER JOIN sales_projects sp ON sp.id = pa.sales_project_id SET pa.tenant_id = sp.tenant_id WHERE pa.tenant_id IS NULL OR pa.tenant_id <> sp.tenant_id');
        DB::statement('UPDATE project_associate_product_limits papl INNER JOIN sales_projects sp ON sp.id = papl.sales_project_id SET papl.tenant_id = sp.tenant_id WHERE papl.tenant_id IS NULL OR papl.tenant_id <> sp.tenant_id');

        Schema::table('project_associates', function (Blueprint $table) {
            $table->dropUnique('pa_project_associate_unique');
            $table->unique(['tenant_id', 'sales_project_id', 'associate_id'], 'pa_tenant_project_associate_unique');
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });

        Schema::table('project_associate_product_limits', function (Blueprint $table) {
            $table->dropUnique('papl_project_associate_product_unique');
            $table->unique(
                ['tenant_id', 'sales_project_id', 'associate_id', 'product_id'],
                'papl_tenant_project_assoc_product_unique'
            );
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_associate_product_limits', function (Blueprint $table) {
            $table->dropUnique('papl_tenant_project_assoc_product_unique');
            $table->unique(
                ['sales_project_id', 'associate_id', 'product_id'],
                'papl_project_associate_product_unique'
            );
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        Schema::table('project_associates', function (Blueprint $table) {
            $table->dropUnique('pa_tenant_project_associate_unique');
            $table->unique(['sales_project_id', 'associate_id'], 'pa_project_associate_unique');
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });
    }
};
