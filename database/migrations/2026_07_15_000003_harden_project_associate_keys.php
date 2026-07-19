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

        $this->addIndexIfMissing('project_associates', ['sales_project_id'], 'pa_sales_project_fk_idx');
        $this->addIndexIfMissing('project_associates', ['associate_id'], 'pa_associate_fk_idx');
        $this->addIndexIfMissing('project_associate_product_limits', ['sales_project_id'], 'papl_sales_project_fk_idx');
        $this->addIndexIfMissing('project_associate_product_limits', ['associate_id'], 'papl_associate_fk_idx');
        $this->addIndexIfMissing('project_associate_product_limits', ['product_id'], 'papl_product_fk_idx');

        Schema::table('project_associates', function (Blueprint $table) {
            if ($this->indexExists('project_associates', 'pa_project_associate_unique')) {
                $table->dropUnique('pa_project_associate_unique');
            }

            if (! $this->indexExists('project_associates', 'pa_tenant_project_associate_unique')) {
                $table->unique(['tenant_id', 'sales_project_id', 'associate_id'], 'pa_tenant_project_associate_unique');
            }

            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });

        Schema::table('project_associate_product_limits', function (Blueprint $table) {
            if ($this->indexExists('project_associate_product_limits', 'papl_project_associate_product_unique')) {
                $table->dropUnique('papl_project_associate_product_unique');
            }

            if (! $this->indexExists('project_associate_product_limits', 'papl_tenant_project_assoc_product_unique')) {
                $table->unique(
                    ['tenant_id', 'sales_project_id', 'associate_id', 'product_id'],
                    'papl_tenant_project_assoc_product_unique'
                );
            }

            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_associate_product_limits', function (Blueprint $table) {
            if ($this->indexExists('project_associate_product_limits', 'papl_tenant_project_assoc_product_unique')) {
                $table->dropUnique('papl_tenant_project_assoc_product_unique');
            }

            if (! $this->indexExists('project_associate_product_limits', 'papl_project_associate_product_unique')) {
                $table->unique(
                    ['sales_project_id', 'associate_id', 'product_id'],
                    'papl_project_associate_product_unique'
                );
            }

            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        Schema::table('project_associates', function (Blueprint $table) {
            if ($this->indexExists('project_associates', 'pa_tenant_project_associate_unique')) {
                $table->dropUnique('pa_tenant_project_associate_unique');
            }

            if (! $this->indexExists('project_associates', 'pa_project_associate_unique')) {
                $table->unique(['sales_project_id', 'associate_id'], 'pa_project_associate_unique');
            }

            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });
    }

    private function addIndexIfMissing(string $table, array $columns, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
            $blueprint->index($columns, $index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
