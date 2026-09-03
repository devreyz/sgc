<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_billing_receipt_projects')) {
            Schema::create('customer_billing_receipt_projects', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('customer_billing_receipt_id');
                $table->foreignId('sales_project_id');
                $table->timestamps();

                $table->unique(
                    ['customer_billing_receipt_id', 'sales_project_id'],
                    'customer_billing_receipt_project_unique',
                );
                $table->index(
                    ['tenant_id', 'sales_project_id'],
                    'customer_billing_receipt_project_tenant_idx',
                );
                $table->foreign('tenant_id', 'cbrp_tenant_fk')
                    ->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('customer_billing_receipt_id', 'cbrp_receipt_fk')
                    ->references('id')->on('customer_billing_receipts')->cascadeOnDelete();
                $table->foreign('sales_project_id', 'cbrp_project_fk')
                    ->references('id')->on('sales_projects')->cascadeOnDelete();
            });
        } else {
            // MySQL confirma a criação da tabela antes de executar os ALTER TABLE
            // das FKs. Se uma versão anterior falhou pelo limite de 64 caracteres,
            // complete com segurança a tabela parcial em vez de tentar recriá-la.
            $this->repairPartialTable();
        }

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

    private function repairPartialTable(): void
    {
        $tableName = 'customer_billing_receipt_projects';
        $indexes = collect(Schema::getIndexes($tableName));

        Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
            if (! $indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === 'customer_billing_receipt_project_unique')) {
                $table->unique(
                    ['customer_billing_receipt_id', 'sales_project_id'],
                    'customer_billing_receipt_project_unique',
                );
            }

            if (! $indexes->contains(fn (array $index): bool => ($index['name'] ?? null) === 'customer_billing_receipt_project_tenant_idx')) {
                $table->index(
                    ['tenant_id', 'sales_project_id'],
                    'customer_billing_receipt_project_tenant_idx',
                );
            }
        });

        $foreignColumns = collect(Schema::getForeignKeys($tableName))
            ->flatMap(fn (array $foreign): array => $foreign['columns'] ?? [])
            ->all();

        Schema::table($tableName, function (Blueprint $table) use ($foreignColumns): void {
            if (! in_array('tenant_id', $foreignColumns, true)) {
                $table->foreign('tenant_id', 'cbrp_tenant_fk')
                    ->references('id')->on('tenants')->cascadeOnDelete();
            }
            if (! in_array('customer_billing_receipt_id', $foreignColumns, true)) {
                $table->foreign('customer_billing_receipt_id', 'cbrp_receipt_fk')
                    ->references('id')->on('customer_billing_receipts')->cascadeOnDelete();
            }
            if (! in_array('sales_project_id', $foreignColumns, true)) {
                $table->foreign('sales_project_id', 'cbrp_project_fk')
                    ->references('id')->on('sales_projects')->cascadeOnDelete();
            }
        });
    }
};
