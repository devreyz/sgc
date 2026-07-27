<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'associate_term_singular')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->string('associate_term_singular', 50)
                    ->default('Associado')
                    ->after('name');
            });
        }

        if (! Schema::hasColumn('tenants', 'associate_term_plural')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->string('associate_term_plural', 50)
                    ->default('Associados')
                    ->after('associate_term_singular');
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['associate_term_singular', 'associate_term_plural'],
            fn (string $column): bool => Schema::hasColumn('tenants', $column),
        ));

        if ($columns !== []) {
            Schema::table('tenants', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
