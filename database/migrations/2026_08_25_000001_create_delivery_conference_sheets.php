<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_conference_sheets', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedInteger('receipt_year')->nullable();
            $table->unsignedInteger('receipt_number')->nullable();
            $table->string('number', 80)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('grouping_mode', 40);
            $table->string('status', 32)->default('draft');
            $table->unsignedSmallInteger('snapshot_version')->nullable();
            $table->json('snapshot')->nullable();
            $table->char('snapshot_hash', 64)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 1000)->nullable();
            $table->unsignedSmallInteger('revision')->default(1);
            $table->unsignedBigInteger('supersedes_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_year', 'receipt_number'], 'conference_sheet_number_unique');
            $table->index(['tenant_id', 'sales_project_id', 'status', 'period_start', 'period_end'], 'conference_sheet_project_queue_idx');
            $table->index(['tenant_id', 'customer_id', 'status'], 'conference_sheet_customer_idx');
            $table->index(['tenant_id', 'organization_id', 'status'], 'conference_sheet_organization_idx');
        });

        Schema::create('delivery_conference_sheet_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('delivery_conference_sheet_id');
            $table->unsignedBigInteger('distribution_id');
            $table->timestamps();

            $table->unique(['delivery_conference_sheet_id', 'distribution_id'], 'conference_sheet_item_unique');
            $table->index('distribution_id', 'conference_sheet_distribution_idx');
        });

        if ($this->parentTablesSupportForeignKeys()) {
            Schema::table('delivery_conference_sheets', function (Blueprint $table): void {
                $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
                $table->foreign('sales_project_id')->references('id')->on('sales_projects')->restrictOnDelete();
                $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
                $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
                $table->foreign('issued_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('supersedes_id', 'conference_sheet_supersedes_fk')->references('id')->on('delivery_conference_sheets')->restrictOnDelete();
            });
            Schema::table('delivery_conference_sheet_items', function (Blueprint $table): void {
                $table->foreign('delivery_conference_sheet_id', 'conference_sheet_item_sheet_fk')->references('id')->on('delivery_conference_sheets')->restrictOnDelete();
                $table->foreign('distribution_id', 'conference_sheet_item_distribution_fk')->references('id')->on('production_deliveries')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_conference_sheet_items');
        Schema::dropIfExists('delivery_conference_sheets');
    }

    private function parentTablesSupportForeignKeys(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return true;
        }

        $tables = ['tenants', 'sales_projects', 'customers', 'organizations', 'users', 'production_deliveries'];
        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $engines = collect(DB::select(
            "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders})",
            [DB::connection()->getDatabaseName(), ...$tables],
        ));

        return $engines->count() === count($tables)
            && $engines->every(fn (object $table): bool => strtolower((string) $table->ENGINE) === 'innodb');
    }
};
