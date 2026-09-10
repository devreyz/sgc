<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\ServiceExecution;
use App\Models\ServiceExecutionResource;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentEvent;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderVersionRate;
use App\Models\ServiceVersion;
use App\Models\User;
use App\Services\Services\ServiceCompositionCalculator;
use App\Services\Services\ServiceExecutionWorkflow;
use App\Services\Services\ServicePaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceProviderCompensationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['activity_log', 'service_payment_allocations', 'service_payment_events', 'cash_movements', 'bank_accounts', 'service_obligations', 'service_obligation_sequences', 'service_execution_resources', 'service_execution_evidences', 'service_composition_lines', 'service_executions', 'service_provider_version_rates', 'service_version_fields', 'service_versions', 'service_orders', 'service_providers', 'services', 'tenants'] as $table) {
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
            $t->string('unit')->default('hora');
            $t->string('review_mode')->default('automatic');
            $t->boolean('allow_provider_create_order')->default(false);
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
            $t->boolean('required')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
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
            $t->decimal('actual_quantity', 18, 4)->nullable();
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

    private function base(Blueprint $table): void
    {
        $table->id();
        $table->timestamps();
    }
}
