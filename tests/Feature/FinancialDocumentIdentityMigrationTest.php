<?php

namespace Tests\Feature;

use App\Models\AssociateReceipt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialDocumentIdentityMigrationTest extends TestCase
{
    public function test_migration_backfills_existing_qr_before_allowing_new_versions(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('associate_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id')->nullable();
            $table->unsignedBigInteger('associate_id')->nullable();
            $table->unsignedSmallInteger('receipt_year')->default(2026);
            $table->unsignedInteger('receipt_number')->default(1);
            $table->string('status')->default('pending_payment');
            $table->decimal('total_net', 14, 4)->default(0);
            $table->timestamps();
        });
        Schema::create('financial_document_identities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('public_id')->unique();
            $table->string('reference_code', 24)->unique();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['documentable_type', 'documentable_id'], 'financial_document_owner_unique');
        });

        DB::table('tenants')->insert(['id' => 1]);
        DB::table('associate_receipts')->insert([
            'id' => 5,
            'tenant_id' => 1,
            'total_net' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('financial_document_identities')->insert([
            'tenant_id' => 1,
            'public_id' => '11111111-1111-4111-8111-111111111111',
            'reference_code' => 'CP-LEGADO000001',
            'documentable_type' => (new AssociateReceipt)->getMorphClass(),
            'documentable_id' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_02_000001_version_financial_document_identities.php');
        $migration->up();

        $identity = DB::table('financial_document_identities')->first();
        self::assertSame(1, (int) $identity->revision);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $identity->document_hash);
        self::assertNull($identity->invalidated_at);
    }
}
