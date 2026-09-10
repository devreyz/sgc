    <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_versions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->string('category', 80)->nullable();
            $table->string('unit', 30)->default('un');
            $table->string('review_mode', 20)->default('manual');
            $table->boolean('allow_provider_create_order')->default(false);
            $table->string('customer_pricing_method', 40)->default('fixed');
            $table->decimal('customer_rate', 14, 4)->nullable();
            $table->decimal('customer_percentage', 8, 4)->nullable();
            $table->boolean('receivable_enabled')->default(true);
            $table->string('provider_pricing_method', 40)->nullable();
            $table->decimal('default_provider_rate', 14, 4)->nullable();
            $table->decimal('provider_percentage', 8, 4)->nullable();
            $table->boolean('payable_enabled')->default(false);
            $table->json('execution_config')->nullable();
            $table->json('financial_config')->nullable();
            $table->json('evidence_config')->nullable();
            $table->json('document_config')->nullable();
            $table->char('snapshot_hash', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'service_id', 'version'], 'service_version_unique');
            $table->index(['tenant_id', 'status'], 'svc_version_tenant_status_idx');
        });

        Schema::create('service_version_fields', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_version_id')->constrained('service_versions')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 160);
            $table->string('type', 30);
            $table->string('phase', 20)->default('execution');
            $table->string('section', 80)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('visible_to_provider')->default(true);
            $table->boolean('editable_by_provider')->default(true);
            $table->boolean('visible_to_management')->default(true);
            $table->boolean('include_in_documents')->default(false);
            $table->boolean('reportable')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('unit', 30)->nullable();
            $table->unsignedTinyInteger('decimal_places')->nullable();
            $table->decimal('minimum', 18, 4)->nullable();
            $table->decimal('maximum', 18, 4)->nullable();
            $table->json('default_value')->nullable();
            $table->json('options')->nullable();
            $table->json('conditional_rule')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('help')->nullable();
            $table->timestamps();
            $table->unique(['service_version_id', 'key'], 'service_version_field_unique');
            $table->index(['tenant_id', 'phase'], 'svc_field_tenant_phase_idx');
        });

        Schema::create('service_provider_version_rates', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_version_id')->constrained('service_versions')->cascadeOnDelete();
            $table->foreignId('service_provider_id')->constrained('service_providers')->restrictOnDelete();
            $table->string('calculation_method', 40);
            $table->decimal('rate', 14, 4)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->decimal('fixed_amount', 14, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['service_version_id', 'service_provider_id'], 'service_provider_version_rate_unique');
            $table->index(['tenant_id', 'service_provider_id'], 'svc_provider_rate_tenant_provider_idx');
        });

        Schema::create('service_order_sequences', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->primary(['tenant_id', 'year'], 'svc_order_sequence_pk');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('current_version_id')->nullable()->after('id')->index();
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('service_versions')->nullOnDelete();
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropUnique('service_orders_number_unique');
            $table->foreignId('associate_id')->nullable()->change();
            $table->foreignId('service_version_id')->nullable()->after('service_id')->constrained('service_versions')->restrictOnDelete();
            $table->string('operational_status', 24)->default('draft')->after('status')->index();
            $table->timestamp('scheduled_at')->nullable()->after('scheduled_date');
            $table->json('order_data')->nullable();
            $table->json('beneficiary_snapshot')->nullable();
            $table->json('provider_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->index(['tenant_id', 'operational_status', 'scheduled_at'], 'service_order_work_queue_idx');
            $table->unique(['tenant_id', 'number'], 'service_order_tenant_number_unique');
        });

        Schema::create('service_executions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->restrictOnDelete();
            $table->foreignId('service_version_id')->constrained('service_versions')->restrictOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained('service_providers')->restrictOnDelete();
            $table->foreignId('associate_id')->nullable()->constrained('associates')->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->json('values')->nullable();
            $table->json('derived_values')->nullable();
            $table->json('catalog_snapshot');
            $table->char('snapshot_hash', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_reason')->nullable();
            $table->uuid('start_operation_key')->nullable();
            $table->uuid('submit_operation_key')->nullable();
            $table->uuid('review_operation_key')->nullable();
            $table->timestamps();
            $table->unique('service_order_id', 'svc_execution_order_uq');
            $table->unique(['tenant_id', 'start_operation_key'], 'svc_execution_start_op_uq');
            $table->unique(['tenant_id', 'submit_operation_key'], 'svc_execution_submit_op_uq');
            $table->unique(['tenant_id', 'review_operation_key'], 'svc_execution_review_op_uq');
            $table->index(['tenant_id', 'status', 'validated_at'], 'svc_execution_status_date_idx');
        });

        Schema::create('service_execution_evidences', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_execution_id')->constrained('service_executions')->restrictOnDelete();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->string('field_key', 80);
            $table->string('evidence_type', 50)->default('other');
            $table->string('phase', 20);
            $table->char('sha256', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_execution_id', 'field_key', 'document_id'], 'service_execution_evidence_unique');
        });

        Schema::create('service_execution_resources', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_execution_id')->constrained('service_executions')->restrictOnDelete();
            $table->string('resource_key', 80);
            $table->string('description', 191);
            $table->string('provided_by', 30)->nullable();
            $table->string('effect', 40)->default('information_only');
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'operation_key'], 'svc_resource_operation_uq');
            $table->index(['tenant_id', 'service_execution_id'], 'svc_resource_execution_idx');
        });

        Schema::create('service_composition_lines', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_execution_id')->constrained('service_executions')->restrictOnDelete();
            $table->string('direction', 16);
            $table->string('type', 24);
            $table->string('source_type', 60);
            $table->string('source_key', 100)->nullable();
            $table->string('description', 191);
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('financial_effect', 30);
            $table->json('rule_snapshot')->nullable();
            $table->boolean('manual')->default(false);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'service_execution_id', 'direction'], 'service_composition_lookup_idx');
        });

        Schema::create('service_obligation_sequences', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->primary(['tenant_id', 'year'], 'svc_obligation_sequence_pk');
        });

        Schema::create('service_obligations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('number', 30);
            $table->foreignId('service_execution_id')->constrained('service_executions')->restrictOnDelete();
            $table->string('direction', 16);
            $table->foreignId('associate_id')->nullable()->constrained('associates')->restrictOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained('service_providers')->restrictOnDelete();
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('adjustment_amount', 14, 2)->default(0);
            $table->string('status', 24)->default('open');
            $table->date('due_date')->nullable();
            $table->json('party_snapshot');
            $table->json('composition_snapshot');
            $table->char('snapshot_hash', 64);
            $table->uuid('operation_key');
            $table->timestamp('frozen_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number'], 'svc_obligation_number_uq');
            $table->unique(['tenant_id', 'operation_key'], 'svc_obligation_operation_uq');
            $table->unique(['service_execution_id', 'direction'], 'svc_obligation_execution_direction_uq');
            $table->index(['tenant_id', 'direction', 'status', 'due_date'], 'service_obligation_work_idx');
        });

        Schema::create('service_payment_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->string('event_type', 20)->default('payment');
            $table->string('status', 20)->default('confirmed');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 30);
            $table->date('payment_date');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->constrained('cash_movements')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('service_payment_events')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'operation_key'], 'svc_payment_operation_uq');
            $table->unique('reversal_of_id', 'svc_payment_reversal_uq');
            $table->index(['tenant_id', 'status', 'payment_date'], 'svc_payment_status_date_idx');
        });

        Schema::create('service_obligation_adjustments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_obligation_id')->constrained('service_obligations')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->string('type', 24);
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->foreignId('reversal_of_id')->nullable()->constrained('service_obligation_adjustments')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'operation_key'], 'svc_adjustment_operation_uq');
            $table->unique('reversal_of_id', 'svc_adjustment_reversal_uq');
        });

        Schema::create('service_payment_allocations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_payment_event_id')->constrained('service_payment_events')->restrictOnDelete();
            $table->foreignId('service_obligation_id')->constrained('service_obligations')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['service_payment_event_id', 'service_obligation_id'], 'service_payment_allocation_unique');
            $table->index(['tenant_id', 'service_obligation_id'], 'svc_allocation_obligation_idx');
        });

        Schema::create('service_payment_plans', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('service_payment_plan_obligations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('service_payment_plan_id');
            $table->foreign('service_payment_plan_id', 'svc_plan_obligation_plan_fk')->references('id')->on('service_payment_plans')->restrictOnDelete();
            $table->foreignId('service_obligation_id')->constrained('service_obligations')->restrictOnDelete();
            $table->decimal('included_amount', 14, 2);
            $table->primary(['service_payment_plan_id', 'service_obligation_id'], 'service_plan_obligation_pk');
        });

        Schema::create('service_payment_plan_installments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('service_payment_plan_id');
            $table->foreign('service_payment_plan_id', 'svc_plan_installment_plan_fk')->references('id')->on('service_payment_plans')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->unique(['service_payment_plan_id', 'number'], 'svc_plan_installment_number_uq');
        });

        Schema::create('service_negotiation_sequences', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->primary(['tenant_id', 'year'], 'svc_negotiation_sequence_pk');
        });

        Schema::create('service_negotiations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('number', 30);
            $table->string('status', 24)->default('draft');
            $table->foreignId('payment_plan_id')->nullable()->constrained('service_payment_plans')->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('service_negotiations')->restrictOnDelete();
            $table->decimal('original_amount', 14, 2);
            $table->decimal('negotiated_amount', 14, 2);
            $table->json('terms_snapshot');
            $table->foreignId('generated_document_id')->nullable()->constrained('generated_documents')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number'], 'svc_negotiation_number_uq');
        });

        Schema::create('service_payout_requests', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('service_provider_id')->constrained('service_providers')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending');
            $table->json('bank_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'operation_key'], 'svc_payout_operation_uq');
            $table->index(['tenant_id', 'service_provider_id', 'status'], 'service_payout_request_idx');
        });

        Schema::create('service_payout_request_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('service_payout_request_id')->constrained('service_payout_requests')->restrictOnDelete();
            $table->foreignId('service_obligation_id')->constrained('service_obligations')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->unique(['service_payout_request_id', 'service_obligation_id'], 'service_payout_item_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'service_versions', 'service_version_fields', 'service_provider_version_rates',
                'service_order_sequences', 'service_executions', 'service_execution_evidences',
                'service_execution_resources', 'service_composition_lines', 'service_obligation_sequences',
                'service_obligations', 'service_payment_events', 'service_obligation_adjustments',
                'service_payment_allocations', 'service_payment_plans', 'service_payment_plan_obligations',
                'service_payment_plan_installments', 'service_negotiation_sequences', 'service_negotiations',
                'service_payout_requests', 'service_payout_request_items',
            ] as $table) {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'service_payout_request_items', 'service_payout_requests', 'service_negotiations',
            'service_negotiation_sequences', 'service_payment_plan_installments', 'service_payment_plan_obligations', 'service_payment_plans',
            'service_payment_allocations', 'service_obligation_adjustments', 'service_payment_events', 'service_obligations',
            'service_obligation_sequences', 'service_composition_lines', 'service_execution_resources',
            'service_execution_evidences', 'service_executions',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_version_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropIndex('service_order_work_queue_idx');
            $table->dropUnique('service_order_tenant_number_unique');
            $table->dropColumn([
                'operational_status', 'scheduled_at', 'order_data', 'beneficiary_snapshot',
                'provider_snapshot', 'lock_version', 'cancelled_at', 'cancellation_reason',
            ]);
            $table->unique('number');
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_version_id');
        });

        Schema::dropIfExists('service_order_sequences');
        Schema::dropIfExists('service_provider_version_rates');
        Schema::dropIfExists('service_version_fields');
        Schema::dropIfExists('service_versions');
    }
};
