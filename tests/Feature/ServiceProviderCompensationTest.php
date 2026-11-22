<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Document;
use App\Models\ServiceExecution;
use App\Models\ServiceExecutionEvidence;
use App\Models\ServiceExecutionResource;
use App\Models\ServiceObligation;
use App\Models\ServiceNegotiation;
use App\Models\ServiceOrder;
use App\Models\ServicePaymentEvent;
use App\Models\ServicePaymentPlan;
use App\Models\ServicePaymentPlanInstallment;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderVersionRate;
use App\Models\ServiceVersion;
use App\Models\User;
use App\Models\Tenant;
use App\Http\Controllers\Services\ServiceManagementController;
use App\Services\TemplatedPdfService;
use App\Services\Services\ServiceReportService;
use App\Services\Services\ServiceCatalogService;
use App\Services\Services\ServiceCompositionCalculator;
use App\Services\Services\ServiceDocumentService;
use App\Services\Services\ServiceEvidenceService;
use App\Services\Services\ServiceExecutionWorkflow;
use App\Services\Services\ServiceFieldValidator;
use App\Services\Services\ServicePaymentService;
use App\Services\Services\ServiceNegotiationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceProviderCompensationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['activity_log', 'service_payment_plan_installments', 'service_payment_plan_obligations', 'service_payment_plans', 'service_payment_allocations', 'service_payment_events', 'cash_movements', 'bank_accounts', 'service_obligations', 'service_obligation_sequences', 'service_execution_resources', 'service_execution_evidences', 'service_composition_lines', 'service_executions', 'service_provider_version_rates', 'service_version_fields', 'service_versions', 'service_orders', 'service_providers', 'services', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('tenants', fn (Blueprint $t) => $this->base($t));
        Schema::create('services', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->string('name');
            $t->string('code')->nullable();
            $t->string('type')->default('outro');
            $t->string('unit')->default('hora');
            $t->decimal('base_price', 14, 2)->default(0);
            $t->boolean('status')->default(true);
            $t->softDeletes();
        });
        Schema::create('service_providers', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('name');
            $t->string('cpf')->nullable();
            $t->string('type')->default('outro');
            $t->boolean('status')->default(true);
            $t->string('bank_name')->nullable();
            $t->string('bank_agency')->nullable();
            $t->string('bank_account')->nullable();
            $t->string('pix_key')->nullable();
            $t->json('provider_roles')->nullable();
            $t->softDeletes();
        });
        Schema::create('service_versions', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_id');
            $t->unsignedInteger('version');
            $t->string('status')->default('published');
            $t->string('category')->nullable();
            $t->string('unit')->default('hora');
            $t->string('review_mode')->default('automatic');
            $t->boolean('allow_provider_create_order')->default(false);
            $t->boolean('members_only')->default(false);
            $t->string('customer_pricing_method')->default('quantity_x_rate');
            $t->decimal('customer_rate', 14, 4)->nullable();
            $t->decimal('customer_percentage', 8, 4)->nullable();
            $t->boolean('receivable_enabled')->default(true);
            $t->string('provider_pricing_method')->nullable();
            $t->decimal('default_provider_rate', 14, 4)->nullable();
            $t->decimal('provider_percentage', 8, 4)->nullable();
            $t->boolean('payable_enabled')->default(true);
            $t->json('execution_config')->nullable();
            $t->json('financial_config')->nullable();
            $t->json('evidence_config')->nullable();
            $t->json('document_config')->nullable();
            $t->string('snapshot_hash')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->unsignedBigInteger('published_by')->nullable();
            $t->timestamp('retired_at')->nullable();
        });
        Schema::create('service_version_fields', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_version_id');
            $t->string('key');
            $t->string('label');
            $t->string('type');
            $t->string('phase');
            $t->string('section')->nullable();
            $t->boolean('required')->default(false);
            $t->boolean('visible_to_provider')->default(true);
            $t->boolean('editable_by_provider')->default(true);
            $t->boolean('visible_to_management')->default(true);
            $t->boolean('include_in_documents')->default(false);
            $t->boolean('reportable')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->string('unit')->nullable();
            $t->unsignedTinyInteger('decimal_places')->nullable();
            $t->decimal('minimum', 18, 4)->nullable();
            $t->decimal('maximum', 18, 4)->nullable();
            $t->json('default_value')->nullable();
            $t->json('options')->nullable();
            $t->json('conditional_rule')->nullable();
            $t->string('placeholder')->nullable();
            $t->string('help')->nullable();
        });
        Schema::create('service_provider_version_rates', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_version_id');
            $t->unsignedBigInteger('service_provider_id');
            $t->string('calculation_method');
            $t->decimal('rate', 14, 4)->nullable();
            $t->decimal('percentage', 8, 4)->nullable();
            $t->decimal('fixed_amount', 14, 2)->nullable();
            $t->boolean('active')->default(true);
            $t->text('notes')->nullable();
        });
        Schema::create('service_orders', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->string('number');
            $t->unsignedBigInteger('service_id');
            $t->unsignedBigInteger('service_version_id');
            $t->unsignedBigInteger('service_provider_id')->nullable();
            $t->unsignedBigInteger('associate_id')->nullable();
            $t->json('provider_snapshot')->nullable();
            $t->json('beneficiary_snapshot')->nullable();
            $t->string('operational_status')->default('validated');
            $t->string('status')->default('scheduled');
            $t->date('execution_date')->nullable();
            $t->date('scheduled_date')->nullable();
            $t->dateTime('scheduled_at')->nullable();
            $t->string('location')->nullable();
            $t->json('order_data')->nullable();
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->decimal('actual_quantity', 18, 4)->nullable();
            $t->decimal('unit_price', 14, 2)->default(0);
            $t->decimal('provider_payment', 14, 2)->nullable();
            $t->decimal('total_price', 14, 2)->default(0);
            $t->decimal('final_price', 14, 2)->default(0);
            $t->softDeletes();
        });
        Schema::create('service_executions', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_order_id');
            $t->unsignedBigInteger('service_version_id');
            $t->unsignedBigInteger('service_provider_id')->nullable();
            $t->unsignedBigInteger('associate_id')->nullable();
            $t->string('status')->default('validated');
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedInteger('lock_version')->default(0);
            $t->decimal('quantity', 18, 4)->nullable();
            $t->string('unit')->nullable();
            $t->json('values')->nullable();
            $t->json('derived_values')->nullable();
            $t->json('catalog_snapshot');
            $t->string('snapshot_hash')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->unsignedBigInteger('started_by')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->unsignedBigInteger('submitted_by')->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->unsignedBigInteger('validated_by')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->text('review_reason')->nullable();
            $t->uuid('start_operation_key')->nullable();
            $t->uuid('submit_operation_key')->nullable();
            $t->uuid('review_operation_key')->nullable();
        });
        Schema::create('service_execution_evidences', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_execution_id');
            $t->unsignedBigInteger('document_id');
            $t->string('field_key');
        });
        Schema::create('service_execution_resources', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_execution_id');
            $t->string('resource_key');
            $t->string('description');
            $t->string('provided_by')->nullable();
            $t->string('effect');
            $t->decimal('quantity', 18, 4)->nullable();
            $t->string('unit')->nullable();
            $t->decimal('unit_price', 14, 4)->nullable();
            $t->decimal('amount', 14, 2)->default(0);
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('expense_id')->nullable();
            $t->uuid('operation_key');
            $t->json('metadata')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
        });
        Schema::create('service_composition_lines', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_execution_id');
            $t->string('direction');
            $t->string('type');
            $t->string('source_type');
            $t->string('source_key')->nullable();
            $t->string('description');
            $t->decimal('quantity', 18, 4)->nullable();
            $t->string('unit')->nullable();
            $t->decimal('unit_price', 14, 4)->nullable();
            $t->decimal('amount', 14, 2);
            $t->string('financial_effect');
            $t->json('rule_snapshot')->nullable();
            $t->boolean('manual')->default(false);
            $t->text('reason')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
        });
        Schema::create('service_obligations', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->string('number');
            $t->unsignedBigInteger('service_execution_id');
            $t->string('direction');
            $t->unsignedBigInteger('associate_id')->nullable();
            $t->unsignedBigInteger('service_provider_id')->nullable();
            $t->decimal('principal_amount', 14, 2);
            $t->decimal('adjustment_amount', 14, 2)->default(0);
            $t->string('status')->default('open');
            $t->date('due_date')->nullable();
            $t->json('party_snapshot');
            $t->json('composition_snapshot');
            $t->string('snapshot_hash');
            $t->uuid('operation_key');
            $t->timestamp('frozen_at');
            $t->unsignedBigInteger('created_by')->nullable();
        });
        Schema::create('service_obligation_sequences', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedSmallInteger('year');
            $t->unsignedBigInteger('last_number')->default(0);
            $t->timestamps();
            $t->primary(['tenant_id', 'year']);
        });
        Schema::create('bank_accounts', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->string('name');
            $t->string('type')->default('corrente');
            $t->decimal('initial_balance', 14, 2)->default(0);
            $t->decimal('current_balance', 14, 2)->default(0);
            $t->date('balance_date')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('status')->default(true);
            $t->text('notes')->nullable();
            $t->softDeletes();
        });
        Schema::create('cash_movements', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->string('type');
            $t->decimal('amount', 14, 2);
            $t->decimal('balance_after', 14, 2)->nullable();
            $t->string('description');
            $t->date('movement_date');
            $t->unsignedBigInteger('bank_account_id')->nullable();
            $t->unsignedBigInteger('transfer_to_account_id')->nullable();
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->unsignedBigInteger('chart_account_id')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('document_number')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->softDeletes();
        });
        Schema::create('service_payment_events', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->uuid('operation_key');
            $t->string('event_type');
            $t->string('status');
            $t->decimal('amount', 14, 2);
            $t->string('payment_method');
            $t->date('payment_date');
            $t->unsignedBigInteger('bank_account_id')->nullable();
            $t->unsignedBigInteger('cash_movement_id')->nullable();
            $t->unsignedBigInteger('reversal_of_id')->nullable();
            $t->unsignedBigInteger('document_id')->nullable();
            $t->text('reason')->nullable();
            $t->json('metadata')->nullable();
            $t->unsignedBigInteger('registered_by')->nullable();
            $t->unique(['tenant_id', 'operation_key']);
        });
        Schema::create('service_payment_allocations', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_payment_event_id');
            $t->unsignedBigInteger('service_obligation_id');
            $t->decimal('amount', 14, 2);
        });
        Schema::create('service_payment_plans', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->string('status')->default('active');
            $t->string('description')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
        });
        Schema::create('service_payment_plan_obligations', function (Blueprint $t) {
            $t->unsignedBigInteger('service_payment_plan_id');
            $t->unsignedBigInteger('service_obligation_id');
            $t->decimal('included_amount', 14, 2);
        });
        Schema::create('service_payment_plan_installments', function (Blueprint $t) {
            $this->base($t);
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_payment_plan_id');
            $t->unsignedInteger('number');
            $t->string('kind')->default('installment');
            $t->date('due_date');
            $t->decimal('amount', 14, 2);
            $t->string('status')->default('scheduled');
            $t->unsignedBigInteger('service_payment_event_id')->nullable();
            $t->timestamp('paid_at')->nullable();
        });
        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('log_name')->nullable();
            $t->text('description');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('event')->nullable();
            $t->string('causer_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->json('properties')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
        session(['tenant_id' => 1]);
    }

    protected function tearDown(): void
    {
        session()->forget('tenant_id');
        parent::tearDown();
    }

    public function test_provider_payable_is_independent_and_uses_default_version_rate_without_user(): void
    {
        [$execution,$provider] = $this->execution(3, 150, 300);
        $this->assertNull($provider->user_id);
        $result = app(ServiceCompositionCalculator::class)->calculate($execution);
        $this->assertSame(450.0, $result['receivable_total']);
        $this->assertSame(900.0, $result['payable_total']);
        $this->assertSame('service_version_default', $result['payable'][0]['rule_snapshot']['precedence']);
    }

    public function test_cloning_version_preserves_json_configs_as_arrays(): void
    {
        [,, $version] = $this->execution(1, 150, 30);
        $version->forceFill([
            'execution_config' => ['quantity_mode' => 'meter_difference', 'meter_start_field' => 'start', 'meter_end_field' => 'end'],
            'financial_config' => ['rules' => [['description' => 'Taxa', 'direction' => 'receivable', 'method' => 'fixed_addition', 'value' => 10]]],
            'evidence_config' => ['retention_days' => 365],
            'document_config' => ['include_values' => true],
        ])->saveQuietly();

        $clone = app(ServiceCatalogService::class)->clone($version->fresh());

        $this->assertSame('draft', $clone->status);
        $this->assertSame('meter_difference', data_get($clone->execution_config, 'quantity_mode'));
        $this->assertSame('Taxa', data_get($clone->financial_config, 'rules.0.description'));
        $this->assertSame(365, data_get($clone->evidence_config, 'retention_days'));
        $this->assertTrue(data_get($clone->document_config, 'include_values'));
    }

    public function test_provider_specific_version_override_has_precedence(): void
    {
        [$execution,$provider,$version] = $this->execution(3, 150, 300);
        $override = new ServiceProviderVersionRate(['service_version_id' => $version->id, 'service_provider_id' => $provider->id, 'calculation_method' => 'quantity_x_rate', 'rate' => 350, 'active' => true]);
        $override->tenant_id = 1;
        $override->saveQuietly(); // Fixture represents a rate configured before publication.
        $result = app(ServiceCompositionCalculator::class)->calculate($execution);
        $this->assertSame(1050.0, $result['payable_total']);
        $this->assertSame('provider_service_version', $result['payable'][0]['rule_snapshot']['precedence']);
    }

    public function test_provider_percentage_and_reimbursement_are_calculated_from_frozen_execution(): void
    {
        [$execution,, $version] = $this->execution(3, 150, 300);
        $version->forceFill([
            'provider_pricing_method' => 'percent_of_base',
            'default_provider_rate' => null,
            'provider_percentage' => 30,
        ])->saveQuietly();
        $resource = new ServiceExecutionResource([
            'service_execution_id' => $execution->id,
            'resource_key' => 'diesel',
            'description' => 'Combustível adiantado pelo prestador',
            'provided_by' => 'provider',
            'effect' => 'reimburse_provider',
            'quantity' => 1,
            'unit' => 'lote',
            'amount' => 75,
            'operation_key' => (string) Str::uuid(),
        ]);
        $resource->tenant_id = 1;
        $resource->save();

        $result = app(ServiceCompositionCalculator::class)->calculate($execution->fresh());

        $this->assertSame(450.0, $result['receivable_total']);
        $this->assertSame(210.0, $result['payable_total']);
        $this->assertSame(30.0, $result['payable'][0]['rule_snapshot']['percentage']);
        $this->assertSame('reimbursement', $result['payable'][1]['type']);
    }

    public function test_customer_percentage_uses_the_configured_financial_base(): void
    {
        [$execution,, $version] = $this->execution(1, 1000, 300);
        $version->forceFill([
            'customer_pricing_method' => 'percent_of_base',
            'customer_percentage' => 10,
        ])->saveQuietly();

        $result = app(ServiceCompositionCalculator::class)->calculate($execution);

        $this->assertSame(100.0, $result['receivable_total']);
    }

    public function test_missing_provider_rate_blocks_payable_generation(): void
    {
        [$execution] = $this->execution(3, 150, null);
        $this->expectException(ValidationException::class);
        app(ServiceCompositionCalculator::class)->calculate($execution);
    }

    public function test_partial_and_total_provider_payments_are_idempotent_and_update_cash(): void
    {
        [$obligation,$account,$actor] = $this->payable();
        $service = app(ServicePaymentService::class);
        $key = (string) Str::uuid();
        $first = $service->record($obligation, 400, 'pix', '2026-09-09', $account->id, $key, $actor);
        $retry = $service->record($obligation, 400, 'pix', '2026-09-09', $account->id, $key, $actor);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, ServicePaymentEvent::count());
        $this->assertSame(500.0, (float) $account->fresh()->current_balance);
        $this->assertSame(500.0, $obligation->fresh()->balance);
        $service->record($obligation->fresh(), 500, 'transferencia', '2026-09-09', $account->id, (string) Str::uuid(), $actor);
        $this->assertSame(0.0, (float) $account->fresh()->current_balance);
        $this->assertSame('paid', $obligation->fresh()->status);
    }

    public function test_provider_overpayment_is_blocked_and_reversal_restores_cash_and_balance(): void
    {
        [$obligation,$account,$actor] = $this->payable();
        $service = app(ServicePaymentService::class);
        $payment = $service->record($obligation, 400, 'pix', '2026-09-09', $account->id, (string) Str::uuid(), $actor);
        $service->reverse($payment, 'Pagamento lançado em duplicidade', (string) Str::uuid(), $actor);
        $this->assertSame(900.0, $obligation->fresh()->balance);
        $this->assertSame(900.0, (float) $account->fresh()->current_balance);
        $this->expectException(ValidationException::class);
        $service->record($obligation->fresh(), 901, 'pix', '2026-09-09', $account->id, (string) Str::uuid(), $actor);
    }

    public function test_custom_negotiation_installment_allocates_and_reverses_across_obligations(): void
    {
        [$execution] = $this->execution(1, 100, null);
        $first = $this->receivableObligation($execution, 'REC-1', 60);
        $second = $this->receivableObligation($execution, 'REC-2', 60);
        $plan = new ServicePaymentPlan(['status' => 'active', 'description' => 'Teste']);
        $plan->tenant_id = 1;
        $plan->save();
        $plan->obligations()->attach($first->id, ['included_amount' => 60]);
        $plan->obligations()->attach($second->id, ['included_amount' => 60]);
        $installment = new ServicePaymentPlanInstallment([
            'service_payment_plan_id' => $plan->id,
            'number' => 1,
            'kind' => 'entry',
            'due_date' => '2026-09-19',
            'amount' => 75,
            'status' => 'scheduled',
        ]);
        $installment->tenant_id = 1;
        $installment->save();
        $account = new BankAccount(['name' => 'Caixa', 'initial_balance' => 0, 'current_balance' => 0, 'status' => true]);
        $account->tenant_id = 1;
        $account->save();
        $actor = new User;
        $service = app(ServicePaymentService::class);

        $payment = $service->recordInstallment($installment, 'pix', '2026-09-19', $account->id, (string) Str::uuid(), $actor);

        $this->assertSame(2, $payment->allocations->count());
        $this->assertSame('paid', $installment->fresh()->status);
        $this->assertSame(0.0, $first->fresh()->balance);
        $this->assertSame(45.0, $second->fresh()->balance);

        $service->reverse($payment, 'Recebimento lançado incorretamente', (string) Str::uuid(), $actor);

        $this->assertSame('scheduled', $installment->fresh()->status);
        $this->assertSame(60.0, $first->fresh()->balance);
        $this->assertSame(60.0, $second->fresh()->balance);
    }

    public function test_cancelling_an_unpaid_agreement_preserves_the_balance_and_blocks_installment_receipt(): void
    {
        Schema::create('service_negotiations', function (Blueprint $table): void {
            $this->base($table);
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('payment_plan_id');
            $table->string('status');
        });
        [$execution] = $this->execution(1, 100, null);
        $obligation = $this->receivableObligation($execution, 'REC-1', 60);
        $plan = new ServicePaymentPlan(['status' => 'active']);
        $plan->tenant_id = 1;
        $plan->save();
        $plan->obligations()->attach($obligation->id, ['included_amount' => 60]);
        $installment = $plan->installments()->make(['number' => 1, 'kind' => 'installment', 'due_date' => '2026-11-20', 'amount' => 60, 'status' => 'scheduled']);
        $installment->tenant_id = 1;
        $installment->save();
        $agreement = new ServiceNegotiation(['status' => 'active', 'payment_plan_id' => $plan->id]);
        $agreement->tenant_id = 1;
        $agreement->save();
        $actor = new User;

        app(ServiceNegotiationService::class)->cancel($agreement, 'Corrigir os vencimentos', $actor);

        $this->assertSame('cancelled', $plan->fresh()->status);
        $this->assertSame('cancelled', $agreement->fresh()->status);
        $this->assertSame('cancelled', $installment->fresh()->status);
        $this->assertSame(60.0, $obligation->fresh()->balance);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Este acordo não aceita novos recebimentos.');
        app(ServicePaymentService::class)->recordInstallment($installment, 'pix', '2026-11-20', 1, (string) Str::uuid(), $actor);
    }

    public function test_negotiation_uses_origin_and_beneficiary_then_keeps_the_financial_trail_on_renegotiation(): void
    {
        $this->negotiationSchema();
        [$execution] = $this->execution(1, 100, null);
        DB::table('associates')->insert([
            ['id' => 10, 'tenant_id' => 1, 'nickname' => 'Ana'],
            ['id' => 20, 'tenant_id' => 1, 'nickname' => 'Outra Ana'],
        ]);
        $origin = $execution->order;
        $origin->update(['beneficiary_snapshot' => ['name' => 'Ana'], 'execution_date' => '2026-11-20']);
        $otherOrder = $origin->replicate();
        $otherOrder->number = 'OS-2026-000002';
        $otherOrder->save();
        $otherExecution = $execution->replicate();
        $otherExecution->service_order_id = $otherOrder->id;
        $otherExecution->save();
        $first = $this->receivableObligation($execution, 'REC-01', 60, 10);
        $second = $this->receivableObligation($otherExecution, 'REC-02', 60, 10);
        $unrelated = $this->receivableObligation($execution, 'REC-03', 100, 20);
        $foreign = $this->receivableObligation($execution, 'REC-04', 100, 10, 2);
        $actor = new class extends User {
            public function checkPermissionTo($permission, $guardName = null): bool { return true; }
        };
        $actor->name = 'Operador';
        $account = new BankAccount(['name' => 'Caixa', 'status' => true, 'initial_balance' => 0, 'current_balance' => 0]);
        $account->tenant_id = 1;
        $account->save();
        $tenant = new Tenant(['name' => 'Associação']);
        $tenant->id = 1;
        $tenant->slug = 'associacao';
        $negotiations = app(ServiceNegotiationService::class);
        $controller = app(ServiceManagementController::class);
        $payments = app(ServicePaymentService::class);
        $payments->record($first, 10, 'pix', '2026-11-20', $account->id, (string) Str::uuid(), $actor);

        $route = (new Route('GET', '/', []))->name('services.management.agreements.create');
        $fromOrder = Request::create('/', 'GET', ['origin' => $origin->id]);
        $route->bind($fromOrder);
        $fromOrder->setUserResolver(fn () => $actor)->setRouteResolver(fn () => $route);
        $orderForm = $controller->agreements($fromOrder, $tenant, $negotiations)->getData();
        $this->assertSame([$first->id], $orderForm['requiredIds']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $orderForm['obligations']->pluck('id')->all());
        $this->assertSame(5000, $orderForm['obligations']->firstWhere('id', $first->id)->balanceCents());
        $wizard = view('services._agreement-create', $orderForm + [
            'createUrl' => route('services.management.agreements.create', $tenant->slug),
            'tenantSlug' => $tenant->slug, 'canPay' => true,
            'money' => fn ($amount) => 'R$ '.number_format($amount, 2, ',', '.'),
            'uuid' => fn () => (string) Str::uuid(),
        ])->render();
        $this->assertStringContainsString('Ordem de origem', $wizard);
        $this->assertStringContainsString('Personalizar parcelas', $wizard);
        $this->assertStringNotContainsString($unrelated->number, $wizard);

        $fromFinance = Request::create('/', 'GET', ['beneficiary' => 10]);
        $fromFinance->setUserResolver(fn () => $actor)->setRouteResolver(fn () => $route);
        $financeForm = $controller->agreements($fromFinance, $tenant, $negotiations)->getData();
        $this->assertSame([], $financeForm['requiredIds']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $financeForm['obligations']->pluck('id')->all());

        $schedule = [
            ['kind' => 'entry', 'amount' => '20.00', 'due_date' => '2026-11-25'],
            ['kind' => 'installment', 'amount' => '90.00', 'due_date' => '2026-12-25'],
        ];
        foreach ([$unrelated, $foreign] as $invalid) {
            try {
                $negotiations->create(1, [$first->id, $invalid->id], $schedule, $actor);
                $this->fail('A cobrança inválida foi incluída.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('obligation_ids', $exception->errors());
            }
        }
        $key = (string) Str::uuid();
        $agreement = $negotiations->create(1, [$first->id, $second->id], $schedule, $actor, operationKey: $key);
        $this->assertSame($agreement->id, $negotiations->create(1, [$second->id, $first->id], $schedule, $actor, operationKey: $key)->id);
        $this->assertSame(1, ServiceNegotiation::count());
        $this->assertEquals(10, $account->fresh()->current_balance);
        [$entry, $installment] = $agreement->plan->installments;
        $this->assertSame(2000, $entry->balanceCents());

        $partial = $payments->recordInstallments($agreement->plan, [$entry->id => '5.00'], 'pix', '2026-11-20', $account->id, (string) Str::uuid(), $actor);
        $this->assertSame(1500, $entry->fresh()->balanceCents());
        $operation = (string) Str::uuid();
        $split = [$entry->id => '15.00', $installment->id => '40.00'];
        Storage::fake('local');
        $proof = UploadedFile::fake()->createWithContent('transferencia.pdf', "%PDF-1.4\n");
        $groupedRequest = Request::create('/', 'POST', [
            'operation_key' => $operation, 'amounts' => $split, 'payment_method' => 'pix',
            'payment_date' => '2026-11-20', 'bank_account_id' => $account->id, 'reference' => 'PIX 001',
        ], files: ['proof' => $proof]);
        $groupedRequest->setUserResolver(fn () => $actor);
        $this->travelTo(now()->parse('2026-11-21'));
        $controller->payAgreement($groupedRequest, $tenant, $agreement->id, $payments);
        $controller->payAgreement($groupedRequest, $tenant, $agreement->id, $payments);
        $this->travelBack();
        $this->assertSame(1, ServicePaymentEvent::where('operation_key', $operation)->count());
        $grouped = ServicePaymentEvent::where('operation_key', $operation)->with('allocations')->firstOrFail();
        $this->assertSame(1, $grouped->receiptProof()->count());
        Storage::disk('local')->assertExists($grouped->receiptProof->path);
        $download = $controller->paymentProof($groupedRequest, $tenant, $grouped->id);
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertSame('55.00', $grouped->amount);
        $this->assertSame(2, $grouped->allocations->count());
        $this->assertSame(1500, data_get($grouped->metadata, "installments.{$entry->id}.obligations.{$first->id}"));
        $this->assertSame(1000, data_get($grouped->metadata, "installments.{$installment->id}.obligations.{$second->id}"));
        $this->assertEquals(70, $account->fresh()->current_balance);

        $report = app(ServiceReportService::class)->summary(1, now()->parse('2026-11-01'), now()->parse('2026-11-30'));
        $this->assertEquals(70, $report['cash_received_period']);
        $this->assertEquals(50, $report['active_negotiated_balance']);

        foreach (['inline' => 1, 'download' => 0] as $copy => $inline) {
            $request = Request::create('/', 'GET', ['inline' => $inline]);
            $request->setUserResolver(fn () => $actor);
            $receipt = $controller->paymentReceipt($request, $tenant, $grouped->id, app(TemplatedPdfService::class));
            $this->assertStringStartsWith('%PDF', $receipt->getContent(), $copy);
            $this->assertStringContainsString('recibo-de-pagamento-rec-svc-', $receipt->headers->get('Content-Disposition'));
        }

        $payments->reverse($grouped, 'Conferência da transferência', (string) Str::uuid(), $actor, '2026-11-20');
        $this->assertSame(1500, $entry->fresh()->balanceCents());
        $this->assertSame(9000, $installment->fresh()->balanceCents());
        $newAgreement = $negotiations->create(1, [$first->id, $second->id], [
            ['kind' => 'installment', 'amount' => '105.00', 'due_date' => '2027-01-31'],
        ], $actor, $agreement);
        $this->assertSame($agreement->id, $newAgreement->supersedes_id);
        $this->assertSame('superseded', $agreement->plan->fresh()->status);
        $this->assertSame('cancelled', $entry->fresh()->status);
        $this->assertSame('confirmed', $partial->fresh()->status);
        $this->assertSame(500, $entry->fresh()->receivedCents());
        $this->assertEquals(15, $account->fresh()->current_balance);
    }

    public function test_offline_fieldwork_lists_only_editable_orders_inside_the_calendar_window(): void
    {
        [$template] = $this->execution(1, 100, null);
        $templateOrder = $template->order;
        $outside = [];
        $inside = [];
        foreach ([
            ['2026-10-23', null, 'scheduled', 'draft', false],
            ['2026-10-24', null, 'scheduled', 'draft', true],
            ['2026-09-01', '2026-10-31', 'in_progress', 'in_progress', true],
            ['2026-11-30', null, 'scheduled', 'draft', true],
            ['2026-12-01', null, 'scheduled', 'draft', false],
            ['2026-11-03', null, 'submitted', 'submitted', false],
            ['2026-11-03', null, 'validated', 'validated', false],
            ['2026-11-03', null, 'cancelled', 'cancelled', false],
            [null, null, 'scheduled', 'draft', false],
        ] as $index => [$scheduled, $executed, $operational, $state, $expected]) {
            $order = $templateOrder->replicate()->fill([
                'number' => 'OS-CAMPO-'.$index, 'scheduled_date' => $scheduled,
                'execution_date' => $executed, 'operational_status' => $operational,
            ]);
            $order->save();
            $template->replicate()->fill(['service_order_id' => $order->id, 'status' => $state])->save();
            if ($expected) {
                $inside[] = $order->id;
            } else {
                $outside[] = $order->id;
            }
        }

        $eligible = ServiceOrder::query()->where('tenant_id', 1)->forOfflineFieldwork(now()->parse('2026-10-31'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing($inside, $eligible);
        $this->assertSame([], array_intersect($outside, $eligible));
    }

    public function test_order_copies_and_live_charge_note_are_separate_pdfs_without_new_snapshots(): void
    {
        $this->negotiationSchema();
        [$execution] = $this->execution(1, 100, null);
        $order = $execution->order;
        $order->update(['beneficiary_snapshot' => ['name' => 'Beneficiário do teste'], 'execution_date' => '2026-11-21']);
        $this->receivableObligation($execution, 'REC-01', 100);
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('name')->nullable();
            $table->string('logo')->nullable();
            $table->string('cnpj')->nullable();
        });
        DB::table('tenants')->where('id', 1)->update([
            'name' => 'Associação Comunitária dos Produtores Panelinhenses',
            'logo' => 'assets/icons/campo-dark-192.png', 'cnpj' => '00.000.000/0000-00',
        ]);
        $tenant = Tenant::withoutGlobalScopes()->findOrFail(1);
        Schema::create('generated_documents', fn (Blueprint $table) => $this->base($table));
        DB::table('generated_documents')->insert(['id' => 42]);
        $actor = new class extends User {
            public function checkPermissionTo($permission, $guardName = null): bool { return true; }
        };
        $controller = app(ServiceManagementController::class);
        $documents = app(ServiceDocumentService::class);
        foreach ([
            ['type' => 'order', 'copy' => 'beneficiary', 'filename' => 'via-do-beneficiario'],
            ['type' => 'order', 'copy' => 'association', 'filename' => 'via-da-associacao'],
            ['type' => 'note', 'filename' => 'nota-interna-de-cobranca'],
            ['type' => 'note', 'filename' => 'nota-interna-de-cobranca'],
        ] as $data) {
            $request = Request::create('/', 'GET', $data);
            $request->setUserResolver(fn () => $actor);
            $response = $controller->generateOrderDocument($request, $tenant, $order->id, $documents);
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $this->assertStringContainsString($data['filename'], $response->headers->get('Content-Disposition'));
            $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', $response->getContent()));
        }
        foreach (['conference', 'accountability'] as $reportType) {
            $request = Request::create('/', 'GET', ['from' => '2026-11-01', 'to' => '2026-11-30', 'report_type' => $reportType]);
            $request->setUserResolver(fn () => $actor);
            $response = $controller->generateReport($request, $tenant, app(ServiceReportService::class));
            $this->assertSame(1, preg_match('~/MediaBox\s*\[0(?:\.0+)?\s+0(?:\.0+)?\s+([\d.]+)\s+([\d.]+)\]~', $response->getContent(), $size));
            $this->assertGreaterThan((float) $size[2], (float) $size[1]);
        }
        $this->assertSame([42], DB::table('generated_documents')->pluck('id')->all());
    }

    public function test_evidence_image_is_revalidated_and_uses_the_same_short_cache_for_both_portals(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('services/image.webp', 'first version');
        $document = new Document([
            'disk' => 'local', 'path' => 'services/image.webp', 'original_name' => 'Evidência.webp',
            'size' => 13, 'mime_type' => 'image/webp', 'extension' => 'webp',
        ]);
        $document->id = 555;
        $document->tenant_id = 1;
        $evidence = new ServiceExecutionEvidence(['sha256' => hash('sha256', 'first version')]);
        $evidence->id = 301;
        $evidence->tenant_id = 1;
        $evidence->setRelation('document', $document);
        $files = app(ServiceEvidenceService::class);

        $response = $files->view(Request::create('/gestao/evidencias/301'), $evidence);
        $this->assertSame('first version', $response->getContent());
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $etag = $response->getEtag();
        $this->assertNotNull($etag);
        $request = Request::create('/prestador/evidencias/301', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        $this->assertSame(304, $files->view($request, $evidence)->getStatusCode());

        Storage::disk('local')->put('services/image.webp', 'second version');
        $this->assertSame('first version', $files->view(Request::create('/prestador/evidencias/301'), $evidence)->getContent());
        $evidence->sha256 = hash('sha256', 'second version');
        $this->assertSame('second version', $files->view($request, $evidence)->getContent());

        $evidence->tenant_id = 2;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $files->view($request, $evidence);
    }

    private function negotiationSchema(): void
    {
        Schema::create('associates', function (Blueprint $table): void {
            $this->base($table);
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nickname')->nullable();
            $table->string('cpf_cnpj')->nullable();
            $table->softDeletes();
        });
        Schema::create('users', function (Blueprint $table): void {
            $this->base($table);
            $table->string('name');
        });
        Schema::create('expenses', function (Blueprint $table): void {
            $this->base($table);
            $table->unsignedBigInteger('tenant_id');
            $table->string('origin_module');
            $table->date('date');
            $table->softDeletes();
        });
        Schema::create('documents', function (Blueprint $table): void {
            $this->base($table);
            $table->unsignedBigInteger('tenant_id');
            $table->morphs('documentable');
            foreach (['name', 'original_name', 'path', 'disk', 'mime_type', 'extension', 'category'] as $field) {
                $table->string($field);
            }
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->softDeletes();
        });
        Schema::create('service_obligation_adjustments', function (Blueprint $table): void {
            $this->base($table);
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_obligation_id');
            $table->decimal('amount', 14, 2);
        });
        Schema::create('service_negotiations', function (Blueprint $table): void {
            $this->base($table);
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('payment_plan_id');
            $table->unsignedBigInteger('supersedes_id')->nullable();
            $table->unsignedBigInteger('generated_document_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('number');
            $table->string('status');
            $table->json('terms_snapshot');
            $table->decimal('original_amount', 14, 2);
            $table->decimal('negotiated_amount', 14, 2);
        });
        (require database_path('migrations/2026_11_21_210000_add_operation_key_to_service_negotiations.php'))->up();
        Schema::create('service_negotiation_sequences', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('year');
            $table->unsignedInteger('last_number');
            $table->timestamps();
            $table->primary(['tenant_id', 'year']);
        });
    }

    public function test_batch_rejects_all_rows_when_one_service_order_is_invalid(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->string('location')->nullable();
            $table->string('unit')->nullable();
            $table->json('order_data')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
        });
        Schema::create('service_order_sequences', function (Blueprint $table): void {
            $this->base($table);
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('year');
            $table->unsignedInteger('last_number');
            $table->unique(['tenant_id', 'year']);
        });
        [, , $version] = $this->execution(1, 100, null);
        Schema::getConnection()->table('service_versions')->where('id', $version->id)->update(['payable_enabled' => false]);
        $actor = \Mockery::mock(User::class)->makePartial();
        $actor->shouldReceive('checkPermissionTo')->with('create_service_order')->andReturn(true);
        $actor->shouldReceive('checkPermissionTo')->with('view_service_financials')->andReturn(true);
        $tenant = new \App\Models\Tenant;
        $tenant->forceFill(['id' => 1, 'slug' => 'teste']);
        $row = ['service_version_id' => $version->id, 'beneficiary_name' => 'Beneficiário avulso',
            'scheduled_at' => '2026-11-20T08:00', 'customer_rate' => '120.50'];
        $controller = app(\App\Http\Controllers\Services\ServiceManagementController::class);
        $creator = app(\App\Services\Services\CreateServiceOrder::class);
        $request = fn (array $rows) => \Illuminate\Http\Request::create('/teste/services-management/orders/batch', 'POST', ['rows' => $rows])
            ->setUserResolver(fn () => $actor);

        try {
            $controller->batch($request([$row, array_replace($row, ['service_version_id' => 999])]), $tenant, $creator);
            $this->fail('O lote inválido deveria ter sido rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows.1.service_version_id', $exception->errors());
        }
        $this->assertDatabaseCount('service_orders', 1);
        $this->assertDatabaseCount('service_order_sequences', 0);

        $controller->batch($request([$row]), $tenant, $creator);
        $order = \App\Models\ServiceOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('Beneficiário avulso', $order->beneficiary_snapshot['name']);
        $this->assertSame('120.50', $order->execution->catalog_snapshot['customer_rate']);
        $this->assertSame('draft', $order->execution->status);
        $corrected = $creator->handle(1, $version, [
            'beneficiary_name' => 'Nome corrigido', 'scheduled_at' => '2026-11-20T09:00', 'lock_version' => 0,
        ], $actor, $order);
        $this->assertSame($order->number, $corrected->number);
        $this->assertSame('Nome corrigido', $corrected->beneficiary_snapshot['name']);
        $this->assertSame('120.50', $corrected->execution->catalog_snapshot['customer_rate']);
        $this->assertSame(1, (int) $corrected->execution->lock_version);
    }

    public function test_version_changes_while_scheduled_and_is_blocked_after_start(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('unit')->nullable();
        });
        [$execution, , $original] = $this->execution(1, 150, 30);
        $order = $execution->order;
        $order->forceFill(['operational_status' => 'scheduled', 'scheduled_at' => '2026-11-24 08:30:00'])->saveQuietly();
        $execution->forceFill(['status' => 'draft', 'quantity' => null])->saveQuietly();

        $replacement = $original->replicate();
        $replacement->forceFill(['version' => 2, 'customer_rate' => 180, 'payable_enabled' => false])->save();
        $actor = new User;
        $actor->id = 1;
        $actor->exists = true;
        $creator = app(\App\Services\Services\CreateServiceOrder::class);
        $form = ['lock_version' => (int) $execution->lock_version, 'beneficiary_name' => 'Beneficiário de teste'];

        $updated = $creator->handle(1, $replacement, $form, $actor, $order->fresh());
        $this->assertSame($order->number, $updated->number);
        $this->assertSame('scheduled', $updated->operational_status);
        $this->assertSame('2026-11-24 08:30', $updated->scheduled_at->format('Y-m-d H:i'));
        $this->assertEquals($replacement->id, $updated->service_version_id);
        $this->assertEquals($replacement->id, $updated->execution->service_version_id);
        $this->assertEquals(180, data_get($updated->execution->catalog_snapshot, 'customer_rate'));

        $started = app(ServiceExecutionWorkflow::class)->start($updated->execution, [], (string) Str::uuid(), $actor);
        try {
            $creator->handle(1, $original, array_replace($form, ['lock_version' => $started->lock_version]), $actor, $updated);
            $this->fail('A versão de uma execução iniciada não deveria mudar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_version_id', $exception->errors());
        }
        $this->assertEquals($replacement->id, $updated->fresh()->service_version_id);
    }

    public function test_order_status_and_amounts_distinguish_customer_payment_from_provider_payment(): void
    {
        [$payable, $account, $actor] = $this->payable();
        $receivable = $this->receivableObligation($payable->execution, 'OB-TEST', 100);
        $order = $payable->execution->order;
        $resolver = app(\App\Services\Services\ServiceHumanStatusResolver::class);
        $payments = app(ServicePaymentService::class);

        $this->assertSame('A receber e a pagar', $resolver->resolve($order->fresh()));
        $payments->record($payable, 900, 'pix', '2026-09-09', $account->id, (string) Str::uuid(), $actor);
        $this->assertSame('Falta receber do cliente', $resolver->resolve($order->fresh()));
        $payments->record($receivable, 40, 'pix', '2026-09-09', $account->id, (string) Str::uuid(), $actor);
        $html = view('services._order-value', ['order' => $order->fresh(), 'valueDirection' => 'receivable'])->render();
        $this->assertStringContainsString('Recebido: R$ 40,00', $html);
        $this->assertStringContainsString('A receber: R$ 60,00', $html);
        $payments->record($receivable, 60, 'pix', '2026-09-09', $account->id, (string) Str::uuid(), $actor);
        $this->assertSame('Quitado', $resolver->resolve($order->fresh()));

        $extra = $payable->fresh();
        $extra->update(['status' => 'partially_paid', 'adjustment_amount' => 10]);
        $this->assertSame('Falta pagar o prestador', $resolver->resolve($order->fresh()));
    }

    public function test_correcting_receipt_account_preserves_the_payment_and_is_atomic(): void
    {
        [$payable, $originalAccount, $actor] = $this->payable();
        $receivable = $this->receivableObligation($payable->execution, 'OB-ACCOUNT', 100);
        $newAccount = new BankAccount(['name' => 'Conta correta', 'type' => 'corrente', 'initial_balance' => 0, 'current_balance' => 0, 'status' => true]);
        $newAccount->tenant_id = $originalAccount->tenant_id;
        $newAccount->save();
        $payments = app(ServicePaymentService::class);
        $original = $payments->record($receivable, 100, 'pix', '2026-09-09', $originalAccount->id, (string) Str::uuid(), $actor);

        try {
            $payments->correctReceiptAccount($original, 999999, 'Conta lançada por engano', (string) Str::uuid(), $actor);
            $this->fail('A correção não deve aceitar conta inexistente.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bank_account_id', $exception->errors());
        }
        $this->assertSame('confirmed', $original->fresh()->status);
        $this->assertSame(1000.0, (float) $originalAccount->fresh()->current_balance);
        $this->assertSame(1, ServicePaymentEvent::count());

        $key = (string) Str::uuid();
        $replacement = $payments->correctReceiptAccount($original, $newAccount->id, 'Conta lançada por engano', $key, $actor);
        $retry = $payments->correctReceiptAccount($original, $newAccount->id, 'Conta lançada por engano', $key, $actor);
        $this->assertSame($replacement->id, $retry->id);
        $this->assertSame('reversed', $original->fresh()->status);
        $this->assertEquals($newAccount->id, $replacement->bank_account_id);
        $this->assertSame('2026-09-09', $replacement->payment_date->toDateString());
        $reversal = ServicePaymentEvent::query()->where('reversal_of_id', $original->id)->sole();
        $this->assertSame('2026-09-09', $reversal->payment_date->toDateString());
        $this->assertSame(900.0, (float) $originalAccount->fresh()->current_balance);
        $this->assertSame(100.0, (float) $newAccount->fresh()->current_balance);
        $this->assertSame(100.0, $receivable->fresh()->paid_amount);
        $this->assertSame('paid', $receivable->fresh()->status);
        $this->assertSame(3, ServicePaymentEvent::count());
    }

    public function test_automatic_review_freezes_execution_and_generates_independent_obligations_once(): void
    {
        [$execution] = $this->execution(3, 150, 300);
        $execution->forceFill(['status' => 'draft', 'quantity' => null, 'catalog_snapshot' => ['review_mode' => 'automatic', 'execution_config' => ['quantity_field' => 'hours'], 'fields' => []]])->saveQuietly();
        $actor = new User;
        $actor->id = 1;
        $actor->exists = true;
        $workflow = app(ServiceExecutionWorkflow::class);
        $start = (string) Str::uuid();
        $finish = (string) Str::uuid();
        $workflow->start($execution, [], $start, $actor);
        $result = $workflow->submit($execution->fresh(), ['hours' => 3], $finish, $actor);
        $this->assertSame('validated', $result->status);
        $this->assertSame(2, $result->obligations()->count());
        $this->assertSame(450.0, (float) $result->obligations()->where('direction', 'receivable')->value('principal_amount'));
        $this->assertSame(900.0, (float) $result->obligations()->where('direction', 'payable')->value('principal_amount'));
        $retry = $workflow->submit($result, ['hours' => 3], $finish, $actor);
        $this->assertSame($result->id, $retry->id);
        $this->assertSame(2, ServiceObligation::count());
    }

    public function test_table_can_conclude_a_past_execution_without_replacing_its_date_with_the_approval_date(): void
    {
        [$execution] = $this->execution(3, 150, 300);
        $execution->forceFill(['status' => 'draft', 'quantity' => null, 'catalog_snapshot' => [
            'review_mode' => 'automatic', 'execution_config' => ['quantity_field' => 'hours'], 'fields' => [],
        ]])->saveQuietly();
        $actor = new User;
        $actor->id = 1;
        $actor->exists = true;
        $key = (string) Str::uuid();
        $workflow = app(ServiceExecutionWorkflow::class);
        $result = $workflow->updateFromTable($execution, ['hours' => '3'], '2025-10-09', 0, $key, $actor, ['start_time' => '08:00', 'end_time' => '11:00']);

        $this->assertSame('validated', $result->status);
        $this->assertSame('2025-10-09', $result->order->execution_date->toDateString());
        $this->assertSame('08:00', substr($result->order->start_time, 0, 5));
        $this->assertSame('11:00', substr($result->order->end_time, 0, 5));
        $this->assertSame(now()->toDateString(), $result->validated_at->toDateString());
        $this->assertSame($result->id, $workflow->updateFromTable($execution, ['hours' => '3'], '2025-10-09', 0, $key, $actor)->id);
        $this->assertSame(2, $result->obligations()->count());
    }

    public function test_meter_difference_is_the_quantity_used_for_customer_and_provider(): void
    {
        [$execution] = $this->execution(1, 150, 30);
        $execution->forceFill([
            'status' => 'draft',
            'quantity' => null,
            'values' => [],
            'catalog_snapshot' => [
                'review_mode' => 'automatic',
                'customer_pricing_method' => 'quantity_x_rate',
                'provider_pricing_method' => 'quantity_x_rate',
                'execution_config' => [
                    'quantity_mode' => 'meter_difference',
                    'meter_start_field' => 'hourmeter_start',
                    'meter_end_field' => 'hourmeter_end',
                ],
                'fields' => [
                    ['key' => 'hourmeter_start', 'label' => 'Horímetro inicial', 'type' => 'meter', 'phase' => 'start', 'required' => true],
                    ['key' => 'hourmeter_end', 'label' => 'Horímetro final', 'type' => 'meter', 'phase' => 'finish', 'required' => true],
                ],
            ],
        ])->saveQuietly();
        $actor = new User;
        $actor->id = 1;
        $actor->exists = true;

        $workflow = app(ServiceExecutionWorkflow::class);
        $workflow->start($execution, ['hourmeter_start' => 2000], (string) Str::uuid(), $actor);
        $result = $workflow->submit($execution->fresh(), ['hourmeter_end' => 2020], (string) Str::uuid(), $actor);

        $this->assertSame(20.0, (float) $result->quantity);
        $this->assertSame(20.0, (float) data_get($result->derived_values, 'customer_quantity'));
        $this->assertSame(20.0, (float) data_get($result->derived_values, 'provider_quantity'));
        $this->assertSame(3000.0, (float) $result->obligations()->where('direction', 'receivable')->value('principal_amount'));
        $this->assertSame(600.0, (float) $result->obligations()->where('direction', 'payable')->value('principal_amount'));
    }

    public function test_review_shows_net_meter_quantity_pauses_and_missing_fields_before_approval(): void
    {
        [$execution] = $this->execution(1, 150, 30);
        $execution->forceFill([
            'status' => 'draft',
            'quantity' => null,
            'catalog_snapshot' => [
                'review_mode' => 'manual',
                'execution_config' => ['quantity_mode' => 'meter_difference', 'meter_start_field' => 'meter_start', 'meter_end_field' => 'meter_end'],
                'fields' => [
                    ['key' => 'meter_start', 'label' => 'Horímetro na saída', 'unit' => 'h', 'type' => 'meter', 'phase' => 'start', 'visible_to_provider' => true],
                    ['key' => 'meter_end', 'label' => 'Horímetro na chegada', 'unit' => 'h', 'type' => 'meter', 'phase' => 'finish', 'visible_to_provider' => true],
                    ['key' => 'fuel_used', 'label' => 'Combustível utilizado', 'unit' => 'L', 'type' => 'number', 'phase' => 'finish', 'visible_to_provider' => true],
                    ['key' => 'internal_note', 'label' => 'Observação interna', 'type' => 'text', 'phase' => 'finish', 'visible_to_provider' => false],
                ],
            ],
        ])->saveQuietly();
        $actor = new User;
        $actor->id = 1;
        $actor->exists = true;
        $workflow = app(ServiceExecutionWorkflow::class);
        $started = $workflow->start($execution, ['meter_start' => 999], (string) Str::uuid(), $actor);
        $measurements = [
            'meter_start' => 1000,
            'meter_end' => 1017.4,
            'meter_pauses_meter_start' => json_encode([['stop' => 1005.6, 'resume' => 1010], ['stop' => 1012, 'resume' => 1014]]),
            'internal_note' => 'Informação do gestor',
        ];
        $live = $workflow->preview($started, $measurements);
        $this->assertSame(11.0, $live['primary_quantity']);
        $this->assertSame(1650.0, $live['receivable_total']);
        $this->assertSame(330.0, $live['payable_total']);
        $draft = $workflow->saveDraft($started, $measurements, $actor);
        $this->assertSame('in_progress', $draft->status);
        $this->assertSame(1000.0, (float) $draft->values['meter_start']);
        $this->assertSame(0, $draft->obligations()->count());
        $storedPreview = $workflow->preview($draft, []);
        $this->assertSame($live['receivable_total'], $storedPreview['receivable_total']);
        $this->assertSame($live['payable_total'], $storedPreview['payable_total']);
        $submitted = $workflow->submit($draft, [], (string) Str::uuid(), $actor);
        try {
            $workflow->saveDraft($draft, ['meter_start' => 999], $actor);
            $this->fail('Um rascunho antigo não deve alterar os dados enviados.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame(1000.0, (float) $submitted->fresh()->values['meter_start']);
        $preview = app(ServiceCompositionCalculator::class)->calculate($submitted, false);

        $this->assertSame('submitted', $submitted->status);
        $this->assertSame(11.0, (float) $submitted->quantity);
        $this->assertSame(1650.0, $preview['receivable_total']);
        $this->assertSame(330.0, $preview['payable_total']);
        $html = view('services._execution-review', ['execution' => $submitted, 'financialPreview' => $preview])->render();
        foreach (['Horas trabalhadas', '11 h', '6,4 h de pausa', 'Horímetro na saída', 'Horímetro na chegada',
            'Pausa 2', 'Quantidade da cobrança', 'Quantidade da remuneração', 'Combustível utilizado', 'Não informado'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['fuel_used', 'meter_start', 'Observação interna', 'Informação do gestor'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
        $manager = view('services._execution-review', ['execution' => $submitted, 'operator' => true])->render();
        $this->assertStringContainsString('Observação interna', $manager);

        $approved = $workflow->approve($submitted, (string) Str::uuid(), $actor);
        $this->assertSame(1650.0, (float) $approved->obligations()->where('direction', 'receivable')->value('principal_amount'));
        $this->assertSame(330.0, (float) $approved->obligations()->where('direction', 'payable')->value('principal_amount'));
    }

    public function test_customer_hours_and_provider_days_use_independent_quantities(): void
    {
        [$execution,, $version] = $this->execution(3, 150, 300);
        $execution->forceFill(['values' => ['hours' => 3, 'days' => 2]])->saveQuietly();
        $version->forceFill(['execution_config' => [
            'customer_quantity_field' => 'hours',
            'provider_quantity_field' => 'days',
            'provider_unit' => 'dia',
        ]])->saveQuietly();

        $result = app(ServiceCompositionCalculator::class)->calculate($execution->fresh());

        $this->assertSame(450.0, $result['receivable_total']);
        $this->assertSame(600.0, $result['payable_total']);
        $this->assertSame('dia', $result['payable'][0]['unit']);
    }

    public function test_customer_can_use_meter_difference_while_provider_uses_an_additional_field(): void
    {
        [$execution,, $version] = $this->execution(1, 150, 300);
        $execution->forceFill(['values' => [
            'hourmeter_start' => 2000,
            'hourmeter_end' => 2020,
            'worked_days' => 2,
        ]])->saveQuietly();
        $version->forceFill(['execution_config' => [
            'quantity_mode' => 'fixed_one',
            'customer_quantity_mode' => 'meter_difference',
            'customer_meter_start_field' => 'hourmeter_start',
            'customer_meter_end_field' => 'hourmeter_end',
            'customer_unit' => 'hora',
            'provider_quantity_mode' => 'field',
            'provider_quantity_field' => 'worked_days',
            'provider_unit' => 'dia',
        ]])->saveQuietly();

        $result = app(ServiceCompositionCalculator::class)->calculate($execution->fresh());

        $this->assertSame(3000.0, $result['receivable_total']);
        $this->assertSame(600.0, $result['payable_total']);
        $this->assertSame('20 × 150', $result['receivable'][0]['rule_snapshot']['formula']);
        $this->assertSame('2 × 300', $result['payable'][0]['rule_snapshot']['formula']);
        $this->assertSame('meter_difference', $result['receivable'][0]['rule_snapshot']['quantity_source']['mode']);
        $this->assertSame('field', $result['payable'][0]['rule_snapshot']['quantity_source']['mode']);
    }

    public function test_internal_service_can_pay_provider_without_charging_beneficiary(): void
    {
        [$execution,, $version] = $this->execution(1, 150, 200);
        $version->forceFill([
            'receivable_enabled' => false,
            'provider_pricing_method' => 'fixed',
            'default_provider_rate' => 200,
        ])->saveQuietly();

        $result = app(ServiceCompositionCalculator::class)->calculate($execution);

        $this->assertSame(0.0, $result['receivable_total']);
        $this->assertSame(200.0, $result['payable_total']);
        $this->assertCount(0, $result['receivable']);
    }

    public function test_controlled_fees_and_discounts_do_not_compound_each_other(): void
    {
        [$execution,, $version] = $this->execution(2, 100, 50);
        $version->forceFill(['financial_config' => ['rules' => [
            ['description' => 'Taxa administrativa', 'direction' => 'receivable', 'method' => 'percent_addition', 'percentage' => 10],
            ['description' => 'Desconto social', 'direction' => 'receivable', 'method' => 'percent_deduction', 'percentage' => 5],
            ['description' => 'Bônus', 'direction' => 'payable', 'method' => 'fixed_addition', 'value' => 25],
        ]]])->saveQuietly();

        $result = app(ServiceCompositionCalculator::class)->calculate($execution);

        $this->assertSame(210.0, $result['receivable_total']);
        $this->assertSame(125.0, $result['payable_total']);
        $this->assertSame(200.0, $result['receivable'][1]['rule_snapshot']['calculation_base']);
        $this->assertSame(200.0, $result['receivable'][2]['rule_snapshot']['calculation_base']);
    }

    public function test_financial_rule_injects_input_requires_evidence_and_uses_the_input(): void
    {
        [$execution,, $version] = $this->execution(2, 100, 50);
        $rule = [
            'description' => 'Desconto de óleo fornecido',
            'direction' => 'receivable',
            'method' => 'quantity_x_rate',
            'effect' => 'subtract',
            'value' => 5,
            'input_key' => 'oil_liters',
            'input_label' => 'Litros de óleo fornecido',
            'input_role' => 'quantity',
            'input_unit' => 'litro',
            'input_phase' => 'finish',
            'input_required' => true,
            'evidence_required' => true,
            'evidence_key' => 'oil_receipt',
            'evidence_label' => 'Nota ou foto do óleo',
        ];
        $version->forceFill(['financial_config' => ['rules' => [$rule]]])->saveQuietly();
        $execution->forceFill(['values' => ['oil_liters' => 10]])->saveQuietly();

        $snapshotFields = collect($version->snapshot()['fields']);
        $this->assertSame('quantity', $snapshotFields->firstWhere('key', 'oil_liters')['type']);
        $this->assertSame('file', $snapshotFields->firstWhere('key', 'oil_receipt')['type']);

        try {
            app(ServiceCompositionCalculator::class)->calculate($execution->fresh());
            $this->fail('A regra deveria exigir comprovante.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $evidence = new ServiceExecutionEvidence([
            'service_execution_id' => $execution->id,
            'document_id' => 1,
            'field_key' => 'oil_receipt',
        ]);
        $evidence->tenant_id = 1;
        $evidence->save();

        $result = app(ServiceCompositionCalculator::class)->calculate($execution->fresh());
        $this->assertSame(150.0, $result['receivable_total']);
        $this->assertSame(-50.0, $result['receivable'][1]['amount']);
    }

    public function test_automatic_field_can_supply_percentage_and_optional_empty_rule_is_ignored(): void
    {
        [$execution,, $version] = $this->execution(2, 100, 50);
        $version->forceFill(['financial_config' => ['rules' => [
            [
                'description' => 'Desconto informado', 'direction' => 'receivable',
                'method' => 'percent_deduction', 'input_key' => 'discount_percent',
                'input_label' => 'Percentual de desconto', 'input_role' => 'value',
                'input_required' => true, 'value' => 0, 'percentage' => 0,
            ],
            [
                'description' => 'Bônus opcional', 'direction' => 'payable',
                'method' => 'fixed_addition', 'input_key' => 'optional_bonus',
                'input_label' => 'Bônus', 'input_role' => 'value',
                'input_required' => false, 'value' => 0, 'percentage' => 0,
            ],
        ]]])->saveQuietly();
        $execution->forceFill(['values' => ['discount_percent' => 10]])->saveQuietly();

        $result = app(ServiceCompositionCalculator::class)->calculate($execution->fresh());

        $this->assertSame(180.0, $result['receivable_total']);
        $this->assertSame(100.0, $result['payable_total']);
        $this->assertSame('200 × 10%', $result['receivable'][1]['rule_snapshot']['formula']);
        $this->assertCount(1, $result['payable']);
    }

    public function test_optional_evidence_does_not_block_execution_field_validation(): void
    {
        [$execution] = $this->execution(1, 100, 50);
        $execution->forceFill(['catalog_snapshot' => ['fields' => [
            ['key' => 'notes', 'label' => 'Observação', 'type' => 'text', 'phase' => 'finish', 'required' => false],
            ['key' => 'optional_photo', 'label' => 'Foto opcional', 'type' => 'image', 'phase' => 'finish', 'required' => false],
        ]]])->saveQuietly();

        $validated = app(ServiceFieldValidator::class)->validate($execution->fresh(), 'finish', []);

        $this->assertSame([], $validated);
    }

    public function test_resource_cannot_create_receivable_for_internal_service(): void
    {
        [$execution,, $version] = $this->execution(1, 100, 50);
        $version->forceFill(['receivable_enabled' => false])->saveQuietly();
        $resource = new ServiceExecutionResource([
            'service_execution_id' => $execution->id,
            'resource_key' => 'fee',
            'description' => 'Taxa indevida',
            'effect' => 'add_to_receivable',
            'amount' => 10,
            'operation_key' => (string) Str::uuid(),
        ]);
        $resource->tenant_id = 1;
        $resource->save();

        $this->expectException(ValidationException::class);
        app(ServiceCompositionCalculator::class)->calculate($execution->fresh());
    }

    public function test_published_version_financial_rules_are_immutable(): void
    {
        [,, $version] = $this->execution(1, 100, 50);

        $this->expectException(ValidationException::class);
        $version->update(['customer_rate' => 999]);
    }

    public function test_published_version_cannot_be_deleted(): void
    {
        [,, $version] = $this->execution(1, 100, 50);

        $this->expectException(ValidationException::class);
        $version->delete();
    }

    public function test_payment_without_cash_or_bank_account_is_blocked(): void
    {
        [$obligation,, $actor] = $this->payable();

        $this->expectException(ValidationException::class);
        app(ServicePaymentService::class)->record($obligation, 100, 'pix', '2026-09-09', null, (string) Str::uuid(), $actor);
    }

    public function test_direct_provider_rate_changes_preserve_other_adjustments_and_are_idempotent(): void
    {
        [$obligation, , $actor] = $this->payable();
        $this->prepareRateAdjustment($obligation);
        $service = app(\App\Services\Services\ServiceObligationAdjustmentService::class);
        $service->add($obligation, 'increase', 20, 'Reembolso de combustível', (string) Str::uuid(), $actor);
        $key = (string) Str::uuid();
        $first = $service->changeProviderRate($obligation, 350, 920, 'Atualização da tarifa', $key, $actor);
        $retry = $service->changeProviderRate($obligation, 350, 920, 'Atualização da tarifa', $key, $actor);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1070.0, $obligation->fresh()->total_amount);
        $service->changeProviderRate($obligation, 400, 1070, 'Segunda atualização da tarifa', (string) Str::uuid(), $actor);
        $this->assertSame(1220.0, $obligation->fresh()->total_amount);
        $this->assertSame('900.00', $obligation->fresh()->principal_amount);
        $this->assertSame(3, $obligation->adjustments()->count());

        $service->add($obligation, 'decrease', 10, 'Desconto posterior', (string) Str::uuid(), $actor);
        $line = $obligation->fresh()->composition_snapshot['lines'][0];
        $composition = $obligation->execution->compositionLines()->make($line + [
            'direction' => 'payable', 'source_type' => 'rule', 'description' => 'Remuneração do prestador',
            'financial_effect' => 'increase',
        ]);
        $composition->tenant_id = $obligation->tenant_id;
        $composition->save();
        $order = $obligation->execution->order->load('execution.compositionLines', 'execution.obligations.latestProviderRateAdjustment');
        $html = view('services._order-value', ['order' => $order, 'valueDirection' => 'payable'])->render();

        $this->assertStringContainsString('hora × R$ 400,00', $html);
        $this->assertStringNotContainsString('hora × R$ 300,00', $html);
        $this->assertStringContainsString('A pagar: R$ 1.210,00', $html);
    }

    public function test_direct_provider_rate_cannot_reduce_total_below_paid_amount(): void
    {
        [$obligation, $account, $actor] = $this->payable();
        $this->prepareRateAdjustment($obligation);
        app(ServicePaymentService::class)->record($obligation, 850, 'pix', '2026-09-09', $account->id, (string) Str::uuid(), $actor);
        try {
            app(\App\Services\Services\ServiceObligationAdjustmentService::class)->changeProviderRate($obligation, 200, 900, 'Correção da tarifa', (string) Str::uuid(), $actor);
            $this->fail('A redução abaixo do total pago deveria ser bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
            $this->assertSame(900.0, $obligation->fresh()->total_amount);
            $this->assertSame(0, $obligation->adjustments()->count());
        }
    }

    public function test_direct_provider_rate_rejects_a_stale_total(): void
    {
        [$obligation, , $actor] = $this->payable();
        $this->prepareRateAdjustment($obligation);
        $this->expectException(ValidationException::class);
        app(\App\Services\Services\ServiceObligationAdjustmentService::class)->changeProviderRate($obligation, 350, 800, 'Correção da tarifa', (string) Str::uuid(), $actor);
    }

    public function test_resource_reimbursement_on_validated_order_updates_balance_once(): void
    {
        [$obligation, $account, $actor] = $this->payable();
        $this->prepareRateAdjustment($obligation);
        app(ServicePaymentService::class)->record($obligation, 900, 'pix', '2026-09-09', $account->id, (string) Str::uuid(), $actor);
        $authorized = \Mockery::mock(User::class)->makePartial();
        $authorized->id = $actor->id;
        $authorized->shouldReceive('checkPermissionTo')->with('manage_service_payables')->once()->andReturn(true);
        $service = app(\App\Services\Services\ServiceResourceService::class);
        $data = ['resource_key'=>'diesel', 'description'=>'Diesel do trator', 'provided_by'=>'provider', 'effect'=>'reimburse_provider', 'quantity'=>10, 'unit_price'=>6];
        $key = (string) Str::uuid();

        $resource = $service->add($obligation->execution, $data, $key, $authorized);
        $retry = $service->add($obligation->execution, $data, $key, $authorized);

        $this->assertSame($resource->id, $retry->id);
        $this->assertSame('900.00', $obligation->fresh()->principal_amount);
        $this->assertSame(900.0, $obligation->fresh()->paid_amount);
        $this->assertSame(60.0, $obligation->fresh()->balance);
        $this->assertSame('partially_paid', $obligation->fresh()->status);
        $this->assertSame(1, $obligation->adjustments()->count());
        $this->assertSame($resource->id, $obligation->adjustments()->first()->metadata['resource_id']);
    }

    public function test_validated_resource_discount_below_paid_amount_rolls_back_resource(): void
    {
        [$payable, $account, $payer] = $this->payable();
        $execution = $payable->execution;
        $obligation = $this->receivableObligation($execution, 'OB-TEST', 100);
        $this->prepareRateAdjustment($obligation);
        app(ServicePaymentService::class)->record($obligation, 80, 'pix', '2026-09-09', $account->id, (string) Str::uuid(), $payer);
        $actor = \Mockery::mock(User::class)->makePartial();
        $actor->id = 1;
        $actor->shouldReceive('checkPermissionTo')->with('manage_service_receivables')->once()->andReturn(true);

        try {
            app(\App\Services\Services\ServiceResourceService::class)->add($execution, [
                'resource_key'=>'diesel', 'description'=>'Diesel fornecido', 'provided_by'=>'customer',
                'effect'=>'deduct_from_receivable', 'amount'=>30,
            ], (string) Str::uuid(), $actor);
            $this->fail('Um desconto acima do saldo não deveria ser registrado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertSame(0, $execution->resources()->count());
        $this->assertSame(0, $obligation->adjustments()->count());
        $this->assertSame(100.0, $obligation->fresh()->total_amount);
    }

    public function test_catalog_financial_edit_preserves_operational_options_and_accepts_rate_without_unit(): void
    {
        [,, $source] = $this->execution(3, 150, 25);
        $catalog = app(ServiceCatalogService::class);
        $draft = $catalog->clone($source);
        $draft->update(['allow_provider_create_order' => true, 'members_only' => true]);
        $tenant = new \App\Models\Tenant;
        $tenant->id = 1;
        $request = $this->catalogRequest(['form_section' => 'financial', 'receivable_enabled' => 1, 'payable_enabled' => 1, 'default_provider_rate' => 30]);
        app(\App\Http\Controllers\Services\ServiceCatalogController::class)->update($request, $tenant, $draft->id, $catalog);
        $draft->refresh();
        $this->assertSame(30.0, (float) $draft->default_provider_rate);
        $this->assertSame('hora', $draft->unit);
        $this->assertTrue($draft->members_only);
        $this->assertTrue($draft->allow_provider_create_order);
        $this->assertSame(25.0, (float) $source->fresh()->default_provider_rate);
    }

    public function test_catalog_operational_edit_preserves_financial_options(): void
    {
        [,, $source] = $this->execution(3, 150, 25);
        $catalog = app(ServiceCatalogService::class);
        $draft = $catalog->clone($source);
        $tenant = new \App\Models\Tenant;
        $tenant->id = 1;
        $request = $this->catalogRequest(['form_section' => 'operational', 'unit' => 'hora', 'review_mode' => 'manual']);
        app(\App\Http\Controllers\Services\ServiceCatalogController::class)->update($request, $tenant, $draft->id, $catalog);
        $draft->refresh();
        $this->assertTrue($draft->receivable_enabled);
        $this->assertTrue($draft->payable_enabled);
        $this->assertSame(25.0, (float) $draft->default_provider_rate);
    }

    private function catalogRequest(array $data): \Illuminate\Http\Request
    {
        $actor = \Mockery::mock(User::class)->makePartial();
        $actor->id = 1;
        $actor->exists = true;
        $actor->shouldReceive('checkPermissionTo')->with('manage_service_catalog')->andReturnTrue();
        $request = \Illuminate\Http\Request::create('/catalog', 'PUT', $data);
        $request->setUserResolver(fn () => $actor);
        $request->setLaravelSession(app('session.store'));
        return $request;
    }

    public function test_catalog_can_remove_the_last_financial_rule(): void
    {
        [,, $source] = $this->execution(3, 150, 25);
        $catalog = app(ServiceCatalogService::class);
        $draft = $catalog->clone($source);
        $draft->update(['financial_config' => ['rules' => [['description' => 'Adicional', 'direction' => 'payable', 'method' => 'fixed_addition', 'effect' => 'add', 'value' => 10]]]]);
        $tenant = new \App\Models\Tenant;
        $tenant->id = 1;
        $request = $this->catalogRequest(['form_section' => 'financial', 'rules_present' => 1, 'receivable_enabled' => 1, 'payable_enabled' => 1]);
        app(\App\Http\Controllers\Services\ServiceCatalogController::class)->update($request, $tenant, $draft->id, $catalog);
        $this->assertSame([], data_get($draft->fresh()->financial_config, 'rules'));
    }

    private function prepareRateAdjustment(ServiceObligation $obligation): void
    {
        Schema::dropIfExists('service_obligation_adjustments');
        Schema::create('service_obligation_adjustments', function (Blueprint $table) {
            $this->base($table);
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_obligation_id');
            $table->uuid('operation_key');
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
        });
        Schema::dropIfExists('service_payout_request_items');
        Schema::dropIfExists('service_payout_requests');
        Schema::create('service_payout_requests', function (Blueprint $table) {
            $this->base($table); $table->unsignedBigInteger('tenant_id'); $table->string('status');
        });
        Schema::create('service_payout_request_items', function (Blueprint $table) {
            $this->base($table); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('service_obligation_id'); $table->unsignedBigInteger('service_payout_request_id');
        });
        Schema::getConnection()->table('service_obligations')->where('id', $obligation->id)->update(['composition_snapshot' => json_encode(['lines' => [[
            'type' => 'base', 'source_key' => 'provider_compensation', 'quantity' => 3, 'unit' => 'hora', 'unit_price' => 300, 'amount' => 900, 'rule_snapshot' => ['method' => 'quantity_x_rate'],
        ]]])]);
    }

    public function test_details_of_an_unpaid_validated_order_can_change_without_modifying_financial_data(): void
    {
        [$obligation, , $actor] = $this->payable();
        $order = $obligation->execution->order;
        $details = app(\App\Services\Services\ServiceOrderDetails::class);
        $updated = $details->update($order, [
            'details_token' => $details::token($order), 'beneficiary_name' => 'Nome completo corrigido',
            'execution_date' => '2025-10-09', 'location' => 'Comunidade Periperi', 'notes' => 'Correção do registro antigo',
            'reason' => 'Data confirmada no caderno', 'quantity' => 99, 'principal_amount' => 1,
        ], $actor);
        $this->assertSame('2025-10-09', $updated->execution_date->toDateString());
        $this->assertSame('Comunidade Periperi', $updated->location);
        $this->assertSame('Nome completo corrigido', data_get($updated->beneficiary_snapshot, 'name'));
        $this->assertSame('validated', $updated->execution->status);
        $this->assertSame(3.0, (float) $updated->execution->quantity);
        $this->assertSame(900.0, $obligation->fresh()->total_amount);
    }

    public function test_stale_order_details_cannot_overwrite_a_previous_correction(): void
    {
        [$obligation, , $actor] = $this->payable();
        $order = $obligation->execution->order;
        $details = app(\App\Services\Services\ServiceOrderDetails::class);
        $data = ['details_token' => $details::token($order), 'beneficiary_name' => 'Nome corrigido', 'reason' => 'Correção do beneficiário'];
        $details->update($order, $data, $actor);
        $this->expectException(ValidationException::class);
        $details->update($order, $data + ['location' => 'Local desatualizado'], $actor);
    }

    public function test_correction_of_submitted_hours_preserves_review_status_and_updates_calculation_units(): void
    {
        [$execution] = $this->execution(3, 150, 25);
        $execution->forceFill(['status' => 'submitted', 'values' => ['hours' => 3], 'catalog_snapshot' => [
            'review_mode' => 'manual', 'execution_config' => ['quantity_mode' => 'field', 'quantity_field' => 'hours'],
            'fields' => [['key' => 'hours', 'label' => 'Horas trabalhadas', 'type' => 'decimal', 'phase' => 'execution', 'required' => true]],
        ]])->saveQuietly();
        $execution->order->update(['operational_status' => 'submitted']);
        $actor = new User; $actor->id = 1; $actor->exists = true;
        $result = app(ServiceExecutionWorkflow::class)->updateFromTable($execution, ['hours' => '4.5'], '2025-10-09', 0, null, $actor, [], null, 'Horas corrigidas no caderno');
        $this->assertSame('submitted', $result->status);
        $this->assertSame('submitted', $result->order->operational_status);
        $this->assertSame(4.5, (float) $result->quantity);
        $preview = app(ServiceCompositionCalculator::class)->calculate($result, false);
        $this->assertSame(675.0, $preview['receivable_total']);
        $this->assertSame(112.5, $preview['payable_total']);
        $this->assertSame('hora', $preview['payable'][0]['unit']);
        $this->assertSame(4.5, $preview['payable'][0]['quantity']);
        $this->assertSame(0, $result->obligations()->count());
        $this->expectException(ValidationException::class);
        app(ServiceExecutionWorkflow::class)->approve($result, (string) Str::uuid(), $actor, 0);
    }

    public function test_validated_order_can_be_reopened_with_its_measurements_and_number_until_paid(): void
    {
        [$obligation, , $actor] = $this->payable();
        $this->prepareRateAdjustment($obligation);
        $this->prepareOrderCorrection();
        $execution = ServiceExecution::findOrFail($obligation->service_execution_id);
        $order = $execution->order;
        $database = Schema::getConnection();
        $database->table('service_executions')->where('id', $execution->id)->update([
            'started_at' => now(), 'values' => json_encode(['meter_start' => 10, 'meter_end' => 13]),
        ]);
        $database->table('service_order_payments')->insert(['service_order_id' => $order->id, 'tenant_id' => 1]);
        $correction = app(\App\Services\Services\ServiceOrderCorrection::class);

        try {
            $correction->handle($order, 'reopen', 'Corrigir horímetro', $actor);
            $this->fail('A recorded payment must block reopening.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
        $this->assertSame('validated', $execution->fresh()->status);
        $database->table('service_order_payments')->delete();

        $correction->handle($order, 'reopen', 'Corrigir horímetro', $actor);

        $this->assertSame($order->number, $order->fresh()->number);
        $this->assertSame('in_progress', $execution->fresh()->status);
        $this->assertSame(['meter_start' => 10, 'meter_end' => 13], $execution->fresh()->values);
        $this->assertDatabaseMissing('service_obligations', ['id' => $obligation->id]);
    }

    public function test_permanent_removal_of_a_cancelled_order_releases_its_number_within_the_same_year(): void
    {
        [$obligation, , $actor] = $this->payable();
        $this->prepareRateAdjustment($obligation);
        $this->prepareOrderCorrection();
        $execution = ServiceExecution::findOrFail($obligation->service_execution_id);
        $order = $execution->order;
        $database = Schema::getConnection();
        $database->table('service_executions')->where('id', $execution->id)->update(['status' => 'cancelled']);
        $order->delete();
        $database->table('service_order_sequences')->insert(['tenant_id' => 1, 'year' => 2026, 'last_number' => 20]);

        app(\App\Services\Services\ServiceOrderCorrection::class)->handle($order, 'purge', 'Cancelar duplicidade', $actor);

        $this->assertDatabaseMissing('service_orders', ['id' => $order->id]);
        $numbers = app(\App\Services\Services\ServiceOrderNumberService::class);
        $database->transaction(function () use ($numbers, $order): void {
            $this->assertSame('OS-2027-000001', $numbers->next(1, 2027));
            $this->assertSame($order->number, $numbers->next(1, 2026));
            $this->assertSame('OS-2026-000021', $numbers->next(1, 2026));
        });
    }

    public function test_an_order_in_progress_can_be_excluded_from_the_list_and_keep_its_number(): void
    {
        [$obligation, , $actor] = $this->payable();
        $this->prepareRateAdjustment($obligation);
        $this->prepareOrderCorrection();
        $execution = ServiceExecution::findOrFail($obligation->service_execution_id);
        $order = $execution->order;
        $database = Schema::getConnection();
        $database->table('service_obligations')->where('id', $obligation->id)->delete();
        $database->table('service_executions')->where('id', $execution->id)->update(['status' => 'in_progress', 'started_at' => now()]);
        $database->table('service_orders')->where('id', $order->id)->update(['operational_status' => 'in_progress']);
        $manager = \Mockery::mock($actor);
        $manager->shouldReceive('checkPermissionTo')->with('edit_service_order')->andReturn(true);
        $tenant = new \App\Models\Tenant(['slug' => 'cooperativa']);
        $tenant->id = 1;
        $request = \Illuminate\Http\Request::create('/', 'DELETE', ['action' => 'delete', 'reason' => 'Cadastro duplicado']);
        $request->setUserResolver(fn () => $manager);

        app(\App\Http\Controllers\Services\ServiceManagementController::class)->remove(
            $request, $tenant, $order->id, app(\App\Services\Services\ServiceOrderNumberService::class),
            app(\App\Services\FinancialDocumentIdentityService::class),
        );

        $this->assertSoftDeleted('service_orders', ['id' => $order->id, 'number' => $order->number]);
        $this->assertSame('cancelled', $execution->fresh()->status);
        $this->assertDatabaseCount('released_service_order_numbers', 0);
    }

    public function test_an_order_in_progress_can_be_permanently_deleted_after_checking_linked_records(): void
    {
        [$obligation, , $actor] = $this->payable();
        $this->prepareRateAdjustment($obligation);
        $this->prepareOrderCorrection();
        $execution = ServiceExecution::findOrFail($obligation->service_execution_id);
        $order = $execution->order;
        $database = Schema::getConnection();
        $database->table('service_obligations')->where('id', $obligation->id)->delete();
        $database->table('service_executions')->where('id', $execution->id)->update(['status' => 'in_progress', 'started_at' => now()]);
        $database->table('expenses')->insert(['tenant_id' => 1, 'expenseable_type' => $order->getMorphClass(), 'expenseable_id' => $order->id]);
        $correction = app(\App\Services\Services\ServiceOrderCorrection::class);

        try {
            $correction->handle($order, 'purge', 'Cadastro duplicado', $actor);
            $this->fail('A linked expense must block permanent removal.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
        $this->assertSame('in_progress', $execution->fresh()->status);
        $database->table('expenses')->delete();

        $correction->handle($order, 'purge', 'Cadastro duplicado', $actor);

        $this->assertDatabaseMissing('service_executions', ['id' => $execution->id]);
        $this->assertDatabaseMissing('service_orders', ['id' => $order->id]);
        $this->assertDatabaseHas('released_service_order_numbers', ['tenant_id' => 1, 'year' => 2026, 'number' => 1]);
    }

    private function prepareOrderCorrection(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
        });
        foreach (['provider_payment_requests', 'service_order_payments', 'service_order_additions', 'service_provider_works'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('service_order_id');
                $table->unsignedBigInteger('tenant_id');
                $table->softDeletes();
            });
        }
        Schema::create('associate_ledgers', function (Blueprint $table): void {
            $table->morphs('reference');
            $table->unsignedBigInteger('tenant_id');
            $table->softDeletes();
        });
        Schema::create('expenses', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id');
            $table->morphs('expenseable');
            $table->softDeletes();
        });
        foreach (['generated_documents', 'cloud_documents'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id');
                $table->morphs('documentable');
                $table->softDeletes();
            });
        }
        Schema::create('service_order_sequences', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number');
            $table->timestamps();
            $table->primary(['tenant_id', 'year']);
        });
        (require database_path('migrations/2026_11_22_130000_create_released_service_order_numbers.php'))->up();
    }

    private function execution(float $quantity, float $customerRate, ?float $providerRate): array
    {
        Schema::getConnection()->table('tenants')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $serviceId = Schema::getConnection()->table('services')->insertGetId(['tenant_id' => 1, 'name' => 'Aração', 'unit' => 'hora', 'base_price' => 0, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $provider = new ServiceProvider(['user_id' => null, 'name' => 'José', 'type' => 'tratorista', 'status' => true]);
        $provider->tenant_id = 1;
        $provider->save();
        $version = new ServiceVersion(['service_id' => $serviceId, 'version' => 1, 'status' => 'published', 'unit' => 'hora', 'review_mode' => 'automatic', 'customer_pricing_method' => 'quantity_x_rate', 'customer_rate' => $customerRate, 'receivable_enabled' => true, 'provider_pricing_method' => 'quantity_x_rate', 'default_provider_rate' => $providerRate, 'payable_enabled' => true]);
        $version->tenant_id = 1;
        $version->save();
        $orderId = Schema::getConnection()->table('service_orders')->insertGetId(['tenant_id' => 1, 'number' => 'OS-2026-000001', 'service_id' => $serviceId, 'service_version_id' => $version->id, 'service_provider_id' => $provider->id, 'provider_snapshot' => json_encode(['name' => 'José']), 'operational_status' => 'validated', 'status' => 'scheduled', 'total_price' => 0, 'final_price' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $execution = new ServiceExecution(['service_order_id' => $orderId, 'service_version_id' => $version->id, 'service_provider_id' => $provider->id, 'status' => 'validated', 'quantity' => $quantity, 'unit' => 'hora', 'values' => [], 'catalog_snapshot' => []]);
        $execution->tenant_id = 1;
        $execution->save();

        return [$execution, $provider, $version];
    }

    private function payable(): array
    {
        [$execution,$provider] = $this->execution(3, 150, 300);
        $obligation = new ServiceObligation(['number' => 'OB-2026-000001', 'service_execution_id' => $execution->id, 'direction' => 'payable', 'service_provider_id' => $provider->id, 'principal_amount' => 900, 'adjustment_amount' => 0, 'status' => 'open', 'party_snapshot' => ['name' => 'José'], 'composition_snapshot' => [], 'snapshot_hash' => str_repeat('a', 64), 'operation_key' => (string) Str::uuid(), 'frozen_at' => now()]);
        $obligation->tenant_id = 1;
        $obligation->save();
        $account = new BankAccount(['name' => 'Conta principal', 'type' => 'corrente', 'initial_balance' => 900, 'current_balance' => 900, 'status' => true]);
        $account->tenant_id = 1;
        $account->save();
        $actor = new User;
        $actor->id = 1;
        $actor->exists = true;

        return [$obligation, $account, $actor];
    }

    private function receivableObligation(ServiceExecution $execution, string $number, float $amount, ?int $associateId = null, int $tenantId = 1): ServiceObligation
    {
        $obligation = new ServiceObligation([
            'number' => $number,
            'service_execution_id' => $execution->id,
            'direction' => 'receivable',
            'associate_id' => $associateId,
            'principal_amount' => $amount,
            'adjustment_amount' => 0,
            'status' => 'open',
            'party_snapshot' => ['name' => 'Beneficiário'],
            'composition_snapshot' => [],
            'snapshot_hash' => hash('sha256', $number),
            'operation_key' => (string) Str::uuid(),
            'frozen_at' => now(),
        ]);
        $obligation->tenant_id = $tenantId;
        $obligation->save();

        return $obligation;
    }

    private function base(Blueprint $table): void
    {
        $table->id();
        $table->timestamps();
    }
}
