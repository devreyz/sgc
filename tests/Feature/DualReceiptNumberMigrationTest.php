<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DualReceiptNumberMigrationTest extends TestCase
{
    public function test_existing_receipts_receive_both_collision_safe_numbers(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('sales_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedSmallInteger('reference_year')->nullable();
        });
        Schema::create('receipt_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id')->nullable();
            $table->string('scope_key');
            $table->string('receipt_type');
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('last_number');
            $table->timestamps();
            $table->unique(['tenant_id', 'scope_key', 'receipt_type', 'receipt_year']);
        });

        foreach (['associate_receipts', 'customer_billing_receipts'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('sales_project_id')->nullable();
                $table->unsignedSmallInteger('receipt_year');
                $table->unsignedInteger('receipt_number');
                $table->date('issued_at')->nullable();
            });
        }

        DB::table('sales_projects')->insert([
            ['id' => 12, 'tenant_id' => 1, 'reference_year' => 2024],
            ['id' => 13, 'tenant_id' => 1, 'reference_year' => 2024],
        ]);
        DB::table('associate_receipts')->insert([
            ['tenant_id' => 1, 'sales_project_id' => 12, 'receipt_year' => 2024, 'receipt_number' => 1, 'issued_at' => '2026-01-02'],
            ['tenant_id' => 1, 'sales_project_id' => 13, 'receipt_year' => 2024, 'receipt_number' => 1, 'issued_at' => '2026-01-03'],
            ['tenant_id' => 1, 'sales_project_id' => 12, 'receipt_year' => 2024, 'receipt_number' => 8, 'issued_at' => '2026-01-04'],
        ]);

        $migration = require database_path('migrations/2026_08_05_000003_store_dual_receipt_numbers.php');
        $migration->up();

        $receipts = DB::table('associate_receipts')->orderBy('id')->get();
        $this->assertSame([1, 2, 8], $receipts->pluck('tenant_receipt_number')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([2026, 2026, 2026], $receipts->pluck('tenant_receipt_year')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([1, 1, 2], $receipts->pluck('project_receipt_number')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([2024, 2024, 2024], $receipts->pluck('project_receipt_year')->map(fn ($value) => (int) $value)->all());

        $this->assertDatabaseHas('receipt_number_sequences', [
            'tenant_id' => 1,
            'scope_key' => 'tenant',
            'receipt_type' => 'associate',
            'receipt_year' => 2026,
            'last_number' => 8,
        ]);
        $this->assertDatabaseHas('receipt_number_sequences', [
            'tenant_id' => 1,
            'scope_key' => 'project:12',
            'receipt_type' => 'associate',
            'receipt_year' => 2024,
            'last_number' => 2,
        ]);
    }
}
