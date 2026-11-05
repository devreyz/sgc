<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_distribution_sheets', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('title', 180);
            $table->date('operational_date');
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id'], 'pds_tenant_id_uq');
            $table->index(['tenant_id', 'assigned_to', 'status', 'operational_date'], 'pds_operator_status_idx');
        });
        Schema::create('pre_distribution_sheet_projects', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sheet_id');
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->foreign(['tenant_id', 'sheet_id'], 'pdsp_sheet_fk')->references(['tenant_id', 'id'])->on('pre_distribution_sheets')->restrictOnDelete();
            $table->unique(['tenant_id', 'sheet_id', 'sales_project_id'], 'pdsp_context_uq');
        });
        Schema::create('pre_distribution_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sheet_id');
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $unit = $table->string('unit', 32);
            // GROUP BY must not merge case-sensitive units under MySQL's usual *_ci collation.
            if (DB::getDriverName() === 'mysql') {
                $unit->charset('utf8mb4')->collation('utf8mb4_bin');
            }
            $table->decimal('planned_quantity', 18, 4)->nullable();
            $table->decimal('reported_quantity', 18, 4)->nullable();
            $table->decimal('reference_price', 18, 4)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('logistics_mode', 24)->default('unspecified');
            $table->string('origin', 32)->default('manager');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'sheet_id', 'sales_project_id'], 'pdi_project_fk')
                ->references(['tenant_id', 'sheet_id', 'sales_project_id'])->on('pre_distribution_sheet_projects')->restrictOnDelete();
            $table->unique(['tenant_id', 'id'], 'pdi_tenant_id_uq');
            $table->index(['tenant_id', 'sales_project_id', 'customer_id', 'product_id'], 'pdi_context_idx');
            $table->index(['tenant_id', 'sheet_id', 'cancelled_at'], 'pdi_sheet_idx');
        });
        Schema::create('pre_distribution_operations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->string('kind', 40);
            $table->char('request_hash', 64);
            $table->json('result');
            $table->timestamps();
            $table->unique(['tenant_id', 'operation_key'], 'pdo_idempotency_uq');
            $table->unique(['tenant_id', 'id'], 'pdo_tenant_id_uq');
        });
        Schema::create('pre_distribution_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sheet_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('operation_id');
            $table->foreign(['tenant_id', 'operation_id'], 'pde_operation_fk')->references(['tenant_id', 'id'])->on('pre_distribution_operations')->restrictOnDelete();
            $table->string('event', 40);
            $table->unsignedInteger('version');
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');
            $table->foreign(['tenant_id', 'sheet_id'], 'pde_sheet_fk')->references(['tenant_id', 'id'])->on('pre_distribution_sheets')->restrictOnDelete();
            $table->index(['tenant_id', 'sheet_id', 'id'], 'pde_history_idx');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE pre_distribution_items ADD CONSTRAINT pdi_nonnegative_ck CHECK ((planned_quantity IS NULL OR planned_quantity >= 0) AND (reported_quantity IS NULL OR reported_quantity >= 0) AND (reference_price IS NULL OR reference_price >= 0))');
        }
    }

    public function down(): void
    {
        // History must not be erased by a routine rollback after rollout.
        foreach (['pre_distribution_events', 'pre_distribution_operations', 'pre_distribution_items', 'pre_distribution_sheet_projects', 'pre_distribution_sheets'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Existem pré-distribuições/auditoria. Desabilite o módulo sem apagar dados; arquive os históricos antes de remover o schema.');
            }
        }
        foreach (['pre_distribution_events', 'pre_distribution_operations', 'pre_distribution_items', 'pre_distribution_sheet_projects', 'pre_distribution_sheets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
