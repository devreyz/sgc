<?php

namespace Tests\Feature;

use App\Models\AssociateLedger;
use App\Models\AssociateReceipt;
use App\Models\AssociateReceiptPayment;
use App\Models\CustomerBillingReceipt;
use App\Services\AssociateReceiptService;
use App\Services\CustomerBillingReceiptService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialPaymentIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'activity_log', 'cash_movements', 'bank_accounts', 'associate_ledgers',
            'associate_receipt_payments', 'customer_receipt_payments', 'production_deliveries',
            'associate_receipts', 'customer_billing_receipts', 'associates', 'customers', 'sales_projects', 'tenant_user', 'tenants',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_admin')->default(false);
            $table->json('roles')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('sales_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('associates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('cpf_cnpj')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('associate_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->string('receipt_label')->nullable();
            $table->json('delivery_ids')->nullable();
            $table->string('status');
            $table->decimal('total_net', 14, 4);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('document_number')->nullable();
            $table->text('payment_notes')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_billing_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('receipt_label')->nullable();
            $table->json('delivery_ids')->nullable();
            $table->string('status');
            $table->decimal('total_net', 14, 4);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('document_number')->nullable();
            $table->text('payment_notes')->nullable();
            $table->timestamps();
        });

        foreach ([
            'associate_receipt_payments' => 'associate_receipt_id',
            'customer_receipt_payments' => 'customer_billing_receipt_id',
        ] as $tableName => $foreignKey) {
            Schema::create($tableName, function (Blueprint $table) use ($foreignKey): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger($foreignKey);
                $table->uuid('operation_key')->nullable();
                $table->decimal('amount', 10, 2);
                $table->date('payment_date');
                $table->string('payment_method')->nullable();
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->string('document_number')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'operation_key']);
            });
        }

        Schema::create('associate_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('associate_id');
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description');
            $table->text('notes')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('category')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('transaction_date');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('description');
            $table->date('movement_date');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('document_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('production_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_receipt_id')->nullable();
            $table->unsignedBigInteger('billing_receipt_id')->nullable();
            $table->boolean('paid')->default(false);
            $table->date('paid_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('event')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        DB::table('tenants')->insert([
            ['id' => 1, 'slug' => 'tenant-um', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'slug' => 'tenant-dois', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('sales_projects')->insert(['id' => 20, 'tenant_id' => 1, 'title' => 'Projeto', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('associates')->insert(['id' => 30, 'tenant_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customers')->insert(['id' => 40, 'tenant_id' => 1, 'name' => 'Cliente', 'created_at' => now(), 'updated_at' => now()]);
        Queue::fake();
        session(['tenant_id' => 1]);
    }

    public function test_producer_payments_are_idempotent_recalculate_from_rows_and_use_fk_truth(): void
    {
        $receipt = $this->associateReceipt();
        DB::table('production_deliveries')->insert([
            ['id' => 1, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_receipt_id' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 999, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_receipt_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(AssociateReceiptService::class);
        $first = $this->payment(40, 'PAG-1', '11111111-1111-4111-8111-111111111111');
        $service->addPayment($receipt, $first);
        $service->addPayment($receipt->fresh(), $first);

        $this->assertSame('40.00', $receipt->fresh()->amount_paid);
        $this->assertSame(AssociateReceiptPayment::class, AssociateLedger::firstOrFail()->reference_type);
        $this->assertDatabaseCount('associate_receipt_payments', 1);
        $this->assertDatabaseCount('associate_ledgers', 1);

        try {
            $service->addPayment($receipt->fresh(), $this->payment(10, 'OUTRO', '11111111-1111-4111-8111-111111111111'));
            $this->fail('A mesma chave com outro valor deveria ser rejeitada.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('chave tecnica', $exception->getMessage());
        }

        $last = $this->payment(60, 'PAG-2', '22222222-2222-4222-8222-222222222222');
        $service->addPayment($receipt->fresh(), $last);
        $service->addPayment($receipt->fresh(), $last);
        $this->assertSame('100.00', $receipt->fresh()->amount_paid);
        $this->assertTrue((bool) DB::table('production_deliveries')->where('id', 1)->value('paid'));
        $this->assertFalse((bool) DB::table('production_deliveries')->where('id', 999)->value('paid'));
        $this->assertDatabaseCount('associate_receipt_payments', 2);
    }

    public function test_invalid_account_rolls_back_producer_payment(): void
    {
        $receipt = $this->associateReceipt();

        try {
            app(AssociateReceiptService::class)->addPayment($receipt, array_merge($this->payment(25, 'PAG-ROLLBACK'), [
                'bank_account_id' => 999,
            ]));
            $this->fail('A conta inexistente deveria cancelar toda a operacao.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('nao pertence', $exception->getMessage());
        }

        $this->assertDatabaseCount('associate_receipt_payments', 0);
        $this->assertDatabaseCount('associate_ledgers', 0);
        $this->assertSame('0.00', $receipt->fresh()->amount_paid);
    }

    public function test_failure_before_ledger_rolls_back_producer_payment(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('A injecao desta falha usa trigger SQLite; o cenario MySQL possui suite dedicada.');
        }

        $receipt = $this->associateReceipt();
        DB::unprepared("CREATE TRIGGER force_ledger_failure BEFORE INSERT ON associate_ledgers BEGIN SELECT RAISE(ABORT, 'forced ledger failure'); END");

        try {
            app(AssociateReceiptService::class)->addPayment(
                $receipt,
                $this->payment(25, 'PAG-LEDGER', '55555555-5555-4555-8555-555555555555')
            );
            $this->fail('A falha injetada deveria cancelar toda a operacao.');
        } catch (\Throwable) {
            // A assercao relevante e a ausencia de efeitos parciais abaixo.
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS force_ledger_failure');
        }

        $this->assertDatabaseCount('associate_receipt_payments', 0);
        $this->assertDatabaseCount('associate_ledgers', 0);
        $this->assertSame('0.00', $receipt->fresh()->amount_paid);
    }

    public function test_customer_receipt_is_idempotent_and_recalculates_paid_total(): void
    {
        $receipt = $this->customerReceipt();
        $service = app(CustomerBillingReceiptService::class);
        $first = $this->payment(30, 'REC-1', '33333333-3333-4333-8333-333333333333');
        $service->addPayment($receipt, $first);
        $service->addPayment($receipt->fresh(), $first);

        try {
            $service->addPayment($receipt->fresh(), $this->payment(10, 'REC-2', '33333333-3333-4333-8333-333333333333'));
            $this->fail('A mesma chave com outro valor deveria ser rejeitada.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('chave tecnica', $exception->getMessage());
        }

        $last = $this->payment(70, 'REC-2', '44444444-4444-4444-8444-444444444444');
        $service->addPayment($receipt->fresh(), $last);
        $service->addPayment($receipt->fresh(), $last);
        $this->assertSame('100.00', $receipt->fresh()->amount_paid);
        $this->assertDatabaseCount('customer_receipt_payments', 2);
    }

    public function test_receipts_settle_using_the_same_cent_amount_shown_to_the_user(): void
    {
        $associateReceipt = $this->associateReceipt();
        $associateReceipt->update(['total_net' => '100.0050']);
        $this->assertSame(100.01, $associateReceipt->fresh()->remaining_amount);
        app(AssociateReceiptService::class)->addPayment(
            $associateReceipt->fresh(),
            $this->payment(100.01, 'ARRED-ASSOC', '88888888-8888-4888-8888-888888888888'),
        );
        $this->assertSame('100.01', $associateReceipt->fresh()->amount_paid);
        $this->assertSame('paid', $associateReceipt->fresh()->status->value);

        $customerReceipt = $this->customerReceipt();
        $customerReceipt->update(['total_net' => '100.0050']);
        $this->assertSame(100.01, $customerReceipt->fresh()->remaining_amount);
        app(CustomerBillingReceiptService::class)->addPayment(
            $customerReceipt->fresh(),
            $this->payment(100.01, 'ARRED-CLIENTE', '99999999-9999-4999-8999-999999999999'),
        );
        $this->assertSame('100.01', $customerReceipt->fresh()->amount_paid);
        $this->assertSame('paid', $customerReceipt->fresh()->status->value);
    }

    public function test_invalid_account_rolls_back_customer_receipt(): void
    {
        $receipt = $this->customerReceipt();

        try {
            app(CustomerBillingReceiptService::class)->addPayment($receipt, array_merge(
                $this->payment(25, 'REC-ROLLBACK', '66666666-6666-4666-8666-666666666666'),
                ['bank_account_id' => 999],
            ));
            $this->fail('A conta inexistente deveria cancelar toda a operacao.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('nao pertence', $exception->getMessage());
        }

        $this->assertDatabaseCount('customer_receipt_payments', 0);
        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertSame('0.00', $receipt->fresh()->amount_paid);
    }

    public function test_operation_key_is_tenant_scoped_and_cannot_bypass_receipt_tenant(): void
    {
        $key = '77777777-7777-4777-8777-777777777777';
        $tenantOne = $this->associateReceipt();
        app(AssociateReceiptService::class)->addPayment($tenantOne, $this->payment(10, 'A', $key));

        DB::table('sales_projects')->insert(['id' => 21, 'tenant_id' => 2, 'title' => 'Projeto B', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('associates')->insert(['id' => 31, 'tenant_id' => 2, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('associate_receipts')->insert([
            'id' => 11, 'tenant_id' => 2, 'sales_project_id' => 21, 'associate_id' => 31,
            'receipt_label' => '0001/2026', 'status' => 'pending_payment', 'total_net' => 100,
            'amount_paid' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tenantTwo = AssociateReceipt::withoutGlobalScopes()->findOrFail(11);

        try {
            app(AssociateReceiptService::class)->addPayment($tenantTwo, $this->payment(10, 'B', $key));
            $this->fail('O tenant ativo nao poderia pagar documento de outro tenant.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('tenant atual', $exception->getMessage());
        }

        session(['tenant_id' => 2]);
        app(AssociateReceiptService::class)->addPayment($tenantTwo, $this->payment(10, 'B', $key));

        $this->assertSame(2, AssociateReceiptPayment::withoutGlobalScopes()->where('operation_key', $key)->count());
    }

    private function associateReceipt(): AssociateReceipt
    {
        DB::table('associate_receipts')->insert([
            'id' => 10, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_id' => 30,
            'receipt_label' => '0001/2026', 'delivery_ids' => json_encode([999]), 'status' => 'pending_payment',
            'total_net' => 100, 'amount_paid' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return AssociateReceipt::findOrFail(10);
    }

    private function customerReceipt(): CustomerBillingReceipt
    {
        DB::table('customer_billing_receipts')->insert([
            'id' => 20, 'tenant_id' => 1, 'sales_project_id' => 20, 'customer_id' => 40,
            'receipt_label' => 'COM-0001/2026', 'status' => 'pending_payment',
            'total_net' => 100, 'amount_paid' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return CustomerBillingReceipt::findOrFail(20);
    }

    private function payment(float $amount, string $document, string $operationKey = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'): array
    {
        return [
            'amount' => $amount,
            'operation_key' => $operationKey,
            'payment_date' => '2026-08-19',
            'payment_method' => 'pix',
            'document_number' => $document,
            'bank_account_id' => null,
            'notes' => null,
        ];
    }
}
