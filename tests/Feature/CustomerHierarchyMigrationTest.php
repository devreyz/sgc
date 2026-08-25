<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerHierarchyMigrationTest extends TestCase
{
    public function test_migration_preserves_existing_customers_and_relaxes_only_the_document_index(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('organizations');

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->string('cnpj', 18)->nullable()->unique('customers_cnpj_unique');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('customers')->insert([
            'id' => 77,
            'tenant_id' => 1,
            'name' => 'Cliente histórico',
            'cnpj' => '12.345.678/0001-90',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_24_000001_add_customer_unit_hierarchy_and_shared_documents.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumns('customers', ['unit_type', 'parent_customer_id']));
        $this->assertDatabaseHas('customers', ['id' => 77, 'name' => 'Cliente histórico']);
        $this->assertFalse(collect(Schema::getIndexes('customers'))->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && array_values($index['columns'] ?? []) === ['cnpj'],
        ));

        DB::table('customers')->insert([
            'tenant_id' => 1,
            'name' => 'Nova filial',
            'cnpj' => '12.345.678/0001-90',
            'unit_type' => 'branch',
            'parent_customer_id' => 77,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseCount('customers', 2);
    }
}
