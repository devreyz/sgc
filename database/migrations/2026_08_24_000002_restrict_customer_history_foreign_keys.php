<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array{table: string, column: string, parent: string, previous: string}> */
    private array $references = [
        ['table' => 'customers', 'column' => 'parent_customer_id', 'parent' => 'customers', 'previous' => 'set null'],
        ['table' => 'customers', 'column' => 'organization_id', 'parent' => 'organizations', 'previous' => 'set null'],
        ['table' => 'sales_projects', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'cascade'],
        ['table' => 'sales_project_customers', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'cascade'],
        ['table' => 'production_deliveries', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'set null'],
        ['table' => 'project_demands', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'set null'],
        ['table' => 'customer_billing_receipts', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'set null'],
        ['table' => 'buyer_requests', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'set null'],
        ['table' => 'buyer_request_items', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'set null'],
        ['table' => 'revenues', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'set null'],
        ['table' => 'quick_sales', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'set null'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->references as $reference) {
            $this->replaceDeleteRule($reference, 'restrict');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (array_reverse($this->references) as $reference) {
            $this->replaceDeleteRule($reference, $reference['previous']);
        }
    }

    /** @param array{table: string, column: string, parent: string, previous: string} $reference */
    private function replaceDeleteRule(array $reference, string $deleteRule): void
    {
        if (! Schema::hasTable($reference['table']) || ! Schema::hasColumn($reference['table'], $reference['column'])) {
            return;
        }

        $foreign = collect(Schema::getForeignKeys($reference['table']))
            ->first(function (array $foreign) use ($reference): bool {
                return array_values($foreign['columns'] ?? []) === [$reference['column']]
                    && ($foreign['foreign_table'] ?? null) === $reference['parent'];
            });

        if (! $foreign || empty($foreign['name'])) {
            return;
        }

        $name = $foreign['name'];
        Schema::table($reference['table'], function (Blueprint $table) use ($name): void {
            $table->dropForeign($name);
        });

        Schema::table($reference['table'], function (Blueprint $table) use ($reference, $name, $deleteRule): void {
            $constraint = $table->foreign($reference['column'], $name)
                ->references('id')
                ->on($reference['parent']);

            match ($deleteRule) {
                'cascade' => $constraint->cascadeOnDelete(),
                'set null' => $constraint->nullOnDelete(),
                default => $constraint->restrictOnDelete(),
            };
        });
    }
};
