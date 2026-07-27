<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_projects', 'associate_receipt_columns')) {
            Schema::table('sales_projects', function (Blueprint $table): void {
                $table->json('associate_receipt_columns')
                    ->nullable()
                    ->after('completion_notes');
            });
        }

        if (! Schema::hasColumn('sales_projects', 'associate_receipt_table_scale')) {
            Schema::table('sales_projects', function (Blueprint $table): void {
                $table->unsignedTinyInteger('associate_receipt_table_scale')
                    ->default(100)
                    ->after('associate_receipt_columns');
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['associate_receipt_columns', 'associate_receipt_table_scale'],
            fn (string $column): bool => Schema::hasColumn('sales_projects', $column),
        ));

        if ($columns !== []) {
            Schema::table('sales_projects', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
