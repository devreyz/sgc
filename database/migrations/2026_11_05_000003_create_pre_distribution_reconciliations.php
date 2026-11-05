<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_distribution_links', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('item_id');
            // Official records may be deleted by the existing correction workflow. Keep their identity and snapshot.
            $table->unsignedBigInteger('distribution_id');
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 32);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('operation_id');
            $table->string('origin', 32);
            $table->json('official_snapshot');
            $table->timestamp('created_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->unsignedBigInteger('reversal_operation_id')->nullable();
            $table->foreign(['tenant_id', 'item_id'], 'pdl_item_fk')->references(['tenant_id', 'id'])->on('pre_distribution_items')->restrictOnDelete();
            $table->foreign(['tenant_id', 'operation_id'], 'pdl_op_fk')->references(['tenant_id', 'id'])->on('pre_distribution_operations')->restrictOnDelete();
            $table->foreign(['tenant_id', 'reversal_operation_id'], 'pdl_reverse_op_fk')->references(['tenant_id', 'id'])->on('pre_distribution_operations')->restrictOnDelete();
            $table->unique(['tenant_id', 'operation_id', 'item_id', 'distribution_id'], 'pdl_operation_uq');
            $table->index(['tenant_id', 'item_id', 'reversed_at'], 'pdl_item_balance_idx');
            $table->index(['tenant_id', 'distribution_id', 'reversed_at'], 'pdl_official_balance_idx');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE pre_distribution_links ADD CONSTRAINT pdl_positive_ck CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pre_distribution_links') && DB::table('pre_distribution_links')->exists()) {
            throw new RuntimeException('Conciliações possuem histórico; desabilite o módulo sem apagar dados.');
        }
        Schema::dropIfExists('pre_distribution_links');
    }
};
