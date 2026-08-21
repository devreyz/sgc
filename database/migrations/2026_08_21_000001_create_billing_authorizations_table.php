<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_authorizations')) {
            if (DB::table('billing_authorizations')->exists()) {
                throw new RuntimeException('A tabela billing_authorizations já contém dados, mas a migration não está registrada. Revise-a manualmente antes de continuar.');
            }

            // MySQL DDL is not transactional. A failed FK may leave this empty
            // table behind even though the migration was not registered.
            Schema::drop('billing_authorizations');
        }

        Schema::create('billing_authorizations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_billing_receipt_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedInteger('sequence');
            $table->string('status', 32)->index();
            $table->boolean('active_marker')->nullable()->default(true);
            $table->unsignedSmallInteger('snapshot_version')->default(1);
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->char('current_hash', 64)->nullable();
            $table->uuid('operation_key');
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->string('sent_by_name')->nullable();
            $table->timestamp('sent_at');
            $table->string('response_decision', 32)->nullable();
            $table->unsignedBigInteger('responded_by')->nullable();
            $table->string('responded_by_name')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->unsignedBigInteger('invalidated_by')->nullable();
            $table->string('invalidation_reason', 1000)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'operation_key'], 'billing_auth_operation_unique');
            $table->unique(['tenant_id', 'customer_billing_receipt_id', 'organization_id', 'sequence'], 'billing_auth_round_unique');
            // MySQL permits multiple NULL values while allowing only one active marker (= 1).
            $table->unique(['tenant_id', 'customer_billing_receipt_id', 'organization_id', 'active_marker'], 'billing_auth_active_unique');
            $table->index(['tenant_id', 'organization_id', 'status', 'sent_at'], 'billing_auth_buyer_queue_idx');
            $table->index(['tenant_id', 'customer_billing_receipt_id', 'created_at'], 'billing_auth_history_idx');
        });

        if ($this->parentTablesSupportForeignKeys()) {
            Schema::table('billing_authorizations', function (Blueprint $table): void {
                $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
                $table->foreign('customer_billing_receipt_id')->references('id')->on('customer_billing_receipts')->restrictOnDelete();
                $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
                $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('responded_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('invalidated_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_authorizations');
    }

    private function parentTablesSupportForeignKeys(): bool
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            return $driver !== 'sqlite';
        }

        $engines = collect(DB::select(
            'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?, ?, ?)',
            [DB::connection()->getDatabaseName(), 'tenants', 'customer_billing_receipts', 'organizations', 'users'],
        ))->mapWithKeys(fn (object $table): array => [strtolower($table->TABLE_NAME) => strtolower((string) $table->ENGINE)]);

        return $engines->count() === 4 && $engines->every(fn (string $engine): bool => $engine === 'innodb');
    }
};
