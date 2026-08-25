<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $this->dropLegacyCnpjUniqueIndex();

        if (! Schema::hasColumn('customers', 'unit_type')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('unit_type', 20)
                    ->default('independent')
                    ->after('organization_id');
            });
        }

        if (! Schema::hasColumn('customers', 'parent_customer_id')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unsignedBigInteger('parent_customer_id')
                    ->nullable()
                    ->after('unit_type');
            });
        }

        $foreignNames = collect(Schema::getForeignKeys('customers'))->pluck('name');
        if (! $foreignNames->contains('customers_parent_customer_id_foreign')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreign('parent_customer_id')
                    ->references('id')
                    ->on('customers')
                    ->nullOnDelete();
            });
        }

        $indexNames = collect(Schema::getIndexes('customers'))->pluck('name');
        if (! $indexNames->contains('customers_tenant_cnpj_idx')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->index(['tenant_id', 'cnpj'], 'customers_tenant_cnpj_idx');
            });
        }
        if (! $indexNames->contains('customers_tenant_parent_idx')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->index(['tenant_id', 'parent_customer_id'], 'customers_tenant_parent_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $foreignNames = collect(Schema::getForeignKeys('customers'))->pluck('name');
        $indexNames = collect(Schema::getIndexes('customers'))->pluck('name');

        Schema::table('customers', function (Blueprint $table) use ($foreignNames, $indexNames): void {
            if ($foreignNames->contains('customers_parent_customer_id_foreign')) {
                $table->dropForeign('customers_parent_customer_id_foreign');
            }
            if ($indexNames->contains('customers_tenant_parent_idx')) {
                $table->dropIndex('customers_tenant_parent_idx');
            }
            if ($indexNames->contains('customers_tenant_cnpj_idx')) {
                $table->dropIndex('customers_tenant_cnpj_idx');
            }
            if (Schema::hasColumn('customers', 'parent_customer_id')) {
                $table->dropColumn('parent_customer_id');
            }
            if (Schema::hasColumn('customers', 'unit_type')) {
                $table->dropColumn('unit_type');
            }
        });

        $hasDuplicateDocuments = DB::table('customers')
            ->whereNotNull('cnpj')
            ->where('cnpj', '!=', '')
            ->select('cnpj')
            ->groupBy('cnpj')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $hasDuplicateDocuments) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unique('cnpj', 'customers_cnpj_unique');
            });
        }
    }

    private function dropLegacyCnpjUniqueIndex(): void
    {
        foreach (Schema::getIndexes('customers') as $index) {
            $columns = array_values($index['columns'] ?? []);
            if (($index['unique'] ?? false) && $columns === ['cnpj']) {
                Schema::table('customers', function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }
        }
    }
};
