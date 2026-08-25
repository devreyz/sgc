<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array{table: string, column: string, previous: string}> */
    private array $references = [
        ['table' => 'customer_billing_receipts', 'column' => 'organization_id', 'previous' => 'set null'],
        ['table' => 'sales_project_organizations', 'column' => 'organization_id', 'previous' => 'cascade'],
        ['table' => 'buyer_requests', 'column' => 'organization_id', 'previous' => 'cascade'],
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

    /** @param array{table: string, column: string, previous: string} $reference */
    private function replaceDeleteRule(array $reference, string $deleteRule): void
    {
        if (! Schema::hasTable($reference['table']) || ! Schema::hasColumn($reference['table'], $reference['column'])) {
            return;
        }

        $foreign = collect(Schema::getForeignKeys($reference['table']))
            ->first(fn (array $foreign): bool => array_values($foreign['columns'] ?? []) === [$reference['column']]
                && ($foreign['foreign_table'] ?? null) === 'organizations');

        if (! $foreign || empty($foreign['name'])) {
            return;
        }

        $name = $foreign['name'];
        Schema::table($reference['table'], fn (Blueprint $table) => $table->dropForeign($name));
        Schema::table($reference['table'], function (Blueprint $table) use ($reference, $name, $deleteRule): void {
            $constraint = $table->foreign($reference['column'], $name)
                ->references('id')
                ->on('organizations');

            match ($deleteRule) {
                'cascade' => $constraint->cascadeOnDelete(),
                'set null' => $constraint->nullOnDelete(),
                default => $constraint->restrictOnDelete(),
            };
        });
    }
};
