<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\User;
use App\Services\ExpensePaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpensePaymentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['activity_log', 'cash_movements', 'expenses', 'bank_accounts', 'tenants'] as $table) Schema::dropIfExists($table);
        Schema::create('tenants', function (Blueprint $table): void { $table->id(); $table->string('name')->nullable(); $table->timestamps(); $table->softDeletes(); });
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('name'); $table->string('type')->nullable();
            $table->decimal('initial_balance', 14, 2)->default(0); $table->decimal('current_balance', 14, 2)->default(0);
            $table->date('balance_date')->nullable(); $table->boolean('is_default')->default(false); $table->boolean('status')->default(true);
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('description'); $table->string('document_number')->nullable();
            $table->decimal('amount', 12, 2); $table->decimal('discount', 10, 2)->default(0); $table->decimal('interest', 10, 2)->default(0); $table->decimal('fine', 10, 2)->default(0); $table->decimal('paid_amount', 12, 2)->nullable();
            $table->date('date')->nullable(); $table->date('due_date')->nullable(); $table->date('paid_date')->nullable();
            $table->unsignedBigInteger('chart_account_id')->nullable(); $table->unsignedBigInteger('bank_account_id')->nullable(); $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('expenseable_type')->nullable(); $table->unsignedBigInteger('expenseable_id')->nullable(); $table->string('origin_module')->nullable();
            $table->string('status'); $table->string('payment_method')->nullable(); $table->boolean('is_recurring')->default(false);
            $table->integer('installment_number')->nullable(); $table->integer('total_installments')->nullable(); $table->unsignedBigInteger('parent_expense_id')->nullable();
            $table->string('document_path')->nullable(); $table->text('notes')->nullable(); $table->unsignedBigInteger('created_by')->nullable(); $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('type'); $table->decimal('amount', 14, 2); $table->decimal('balance_after', 14, 2)->nullable();
            $table->string('description'); $table->date('movement_date'); $table->unsignedBigInteger('bank_account_id')->nullable(); $table->unsignedBigInteger('transfer_to_account_id')->nullable();
            $table->string('reference_type')->nullable(); $table->unsignedBigInteger('reference_id')->nullable(); $table->unsignedBigInteger('chart_account_id')->nullable();
            $table->string('payment_method')->nullable(); $table->string('document_number')->nullable(); $table->text('notes')->nullable(); $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id(); $table->string('log_name')->nullable(); $table->text('description'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable(); $table->unsignedBigInteger('causer_id')->nullable(); $table->string('event')->nullable(); $table->json('properties')->nullable(); $table->uuid('batch_uuid')->nullable(); $table->unsignedBigInteger('tenant_id')->nullable(); $table->timestamps();
        });
        DB::table('tenants')->insert(['id' => 1, 'name' => 'Teste', 'created_at' => now(), 'updated_at' => now()]);
        session(['tenant_id' => 1]);
    }

    public function test_expense_payment_debits_the_bank_account_once_and_cannot_be_repeated(): void
    {
        $account = new BankAccount(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 1000, 'current_balance' => 1000, 'status' => true]);
        $account->tenant_id = 1; $account->save();
        $expense = new Expense(['description' => 'Combustível', 'amount' => 100, 'discount' => 0, 'interest' => 0, 'fine' => 0, 'date' => now(), 'due_date' => now(), 'status' => ExpenseStatus::PENDING, 'origin_module' => 'services']);
        $expense->tenant_id = 1; $expense->save();
        $actor = new User(); $actor->id = 1; $actor->exists = true;
        $data = ['payment_date' => now()->toDateString(), 'bank_account_id' => $account->id, 'payment_method' => 'pix', 'paid_amount' => 100];

        app(ExpensePaymentService::class)->pay($expense, $data, $actor);

        $this->assertSame(900.0, (float) $account->fresh()->current_balance);
        $this->assertDatabaseCount('cash_movements', 1);
        $this->expectException(ValidationException::class);
        app(ExpensePaymentService::class)->pay($expense->fresh(), $data, $actor);
    }
}
