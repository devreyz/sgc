<?php

namespace Tests\Feature;

use App\Enums\FinancialReceiptStatus;
use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Models\FinancialReceipt;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FinancialReceiptService;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinancialReceiptServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['activity_log', 'financial_receipt_counters', 'financial_receipt_items', 'financial_receipts', 'cash_movements', 'chart_accounts', 'bank_accounts', 'model_has_permissions', 'role_has_permissions', 'model_has_roles', 'permissions', 'roles', 'tenant_user', 'tenants', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('status')->default(true);
            $t->string('webauthn_user_handle')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->boolean('active')->default(true);
            $t->json('settings')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->boolean('is_admin')->default(false);
            $t->json('roles')->nullable();
            $t->string('tenant_name')->nullable();
            $t->string('tenant_password')->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
        });
        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('name');
            $t->string('type');
            $t->string('bank_name')->nullable();
            $t->string('agency')->nullable();
            $t->string('account_number')->nullable();
            $t->decimal('initial_balance', 14, 2)->default(0);
            $t->decimal('current_balance', 14, 2)->default(0);
            $t->date('balance_date')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('status')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('chart_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('code');
            $t->string('name');
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('type')->nullable();
            $t->string('nature')->nullable();
            $t->boolean('allows_entries')->default(true);
            $t->boolean('status')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('cash_movements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('type');
            $t->decimal('amount', 15, 2);
            $t->decimal('balance_after', 15, 2)->nullable();
            $t->text('description');
            $t->date('movement_date');
            $t->unsignedBigInteger('bank_account_id');
            $t->unsignedBigInteger('transfer_to_account_id')->nullable();
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->unsignedBigInteger('chart_account_id')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('document_number')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('financial_receipts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedSmallInteger('receipt_year')->nullable();
            $t->unsignedBigInteger('receipt_number')->nullable();
            $t->string('status')->default('draft');
            $t->string('payer_type');
            $t->string('payer_name');
            $t->string('payer_document')->nullable();
            $t->string('payer_contact')->nullable();
            $t->date('received_on');
            $t->unsignedBigInteger('bank_account_id');
            $t->unsignedBigInteger('chart_account_id')->nullable();
            $t->string('payment_method');
            $t->string('payment_reference')->nullable();
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->decimal('manual_amount', 15, 2)->nullable();
            $t->text('purpose')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('issued_by')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->unsignedBigInteger('cancelled_by')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->unsignedBigInteger('cash_movement_id')->nullable();
            $t->unsignedBigInteger('reversal_movement_id')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'receipt_year', 'receipt_number']);
        });
        Schema::create('financial_receipt_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('financial_receipt_id');
            $t->unsignedSmallInteger('position')->default(1);
            $t->text('description');
            $t->decimal('quantity', 15, 4);
            $t->string('unit');
            $t->decimal('unit_price', 15, 4);
            $t->decimal('total_amount', 15, 2);
            $t->string('reference')->nullable();
            $t->timestamps();
        });
        Schema::create('financial_receipt_counters', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedSmallInteger('year');
            $t->unsignedBigInteger('last_number')->default(0);
            $t->primary(['tenant_id', 'year']);
        });
        activity()->disableLogging();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Gate::define('financial_receipt.issue', fn (User $user) => true);
        Gate::define('financial_receipt.cancel', fn (User $user) => true);
        Gate::define('view_financial::receipt', fn (User $user) => true);
    }

    public function test_issue_and_cancel_are_atomic_and_keep_an_auditable_reversal(): void
    {
        [$tenant, $user, $account] = $this->context();
        $receipt = $this->draftReceipt($tenant, $user, $account);

        $issued = app(FinancialReceiptService::class)->issue($receipt, $user);

        $this->assertSame(FinancialReceiptStatus::ISSUED, $issued->status);
        $this->assertSame('150.00', $issued->total_amount);
        $this->assertSame(1, $issued->receipt_number);
        $this->assertNotNull($issued->cash_movement_id);
        $this->assertSame('250.00', $account->fresh()->current_balance);
        $this->assertDatabaseHas('cash_movements', [
            'id' => $issued->cash_movement_id,
            'tenant_id' => $tenant->id,
            'type' => 'income',
            'amount' => 150,
        ]);
        $this->get(route('financial-receipts.print', $issued))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $cancelled = app(FinancialReceiptService::class)->cancel($issued, $user, 'Pagamento devolvido ao pagador por duplicidade.');

        $this->assertSame(FinancialReceiptStatus::CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->reversal_movement_id);
        $this->assertSame('100.00', $account->fresh()->current_balance);
        $this->assertDatabaseHas('cash_movements', [
            'id' => $cancelled->reversal_movement_id,
            'tenant_id' => $tenant->id,
            'type' => 'expense',
            'amount' => 150,
        ]);
    }

    public function test_user_cannot_issue_a_receipt_from_another_tenant(): void
    {
        [$tenant, $user] = $this->context();
        $other = Tenant::create(['name' => 'Outra organização', 'slug' => 'outra-organizacao', 'active' => true]);
        $account = new BankAccount(['name' => 'Caixa externo', 'type' => 'caixa', 'initial_balance' => 0, 'current_balance' => 0, 'status' => true]);
        $account->tenant_id = $other->id;
        $account->save();
        $receipt = $this->draftReceipt($other, $user, $account);

        session(['tenant_id' => $tenant->id]);

        $this->expectException(AuthorizationException::class);
        app(FinancialReceiptService::class)->issue($receipt, $user);
    }

    public function test_reference_only_receipt_can_be_saved_and_issued(): void
    {
        [$tenant, $user, $account] = $this->context();
        $this->grantFinancialPortalRole($user, $tenant);

        $receipt = app(FinancialReceiptService::class)->createDraft($tenant->id, [
            'payer_type' => 'other',
            'payer_name' => 'Pagador por referencia',
            'received_on' => now()->toDateString(),
            'bank_account_id' => $account->id,
            'payment_method' => PaymentMethod::PIX->value,
            'payment_reference' => 'PIX-123',
            'purpose' => 'Quitacao de referencia interna',
            'manual_amount' => 84.75,
        ], $user);

        $this->assertTrue($receipt->items->isEmpty());
        $this->assertSame('84.75', $receipt->total_amount);

        $issued = app(FinancialReceiptService::class)->issue($receipt, $user);

        $this->assertSame(FinancialReceiptStatus::ISSUED, $issued->status);
        $this->assertSame('184.75', $account->fresh()->current_balance);
        $this->get(route('financial-receipts.print', $issued))->assertOk();
    }

    public function test_financial_portal_accepts_a_reference_only_receipt(): void
    {
        [$tenant, $user, $account] = $this->context();
        $this->grantFinancialPortalRole($user, $tenant);

        $response = $this->post(route('finance.receipts.store', ['tenant' => $tenant->slug]), [
            'payer_type' => 'other',
            'payer_name' => 'Pagador sem itens',
            'received_on' => now()->toDateString(),
            'bank_account_id' => $account->id,
            'payment_method' => 'dinheiro',
            'payment_reference' => 'REC-REF-01',
            'purpose' => 'Recebimento por referencia',
            'manual_amount' => '36.40',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('financial_receipts', [
            'tenant_id' => $tenant->id,
            'payer_name' => 'Pagador sem itens',
            'manual_amount' => 36.40,
            'total_amount' => 36.40,
        ]);
    }

    public function test_treasurer_membership_opens_filament_and_resolves_role_permissions_only_in_its_tenant(): void
    {
        [$tenant, $user] = $this->context();
        $permission = Permission::create(['name' => 'view_any_financial::receipt', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'tesoureiro', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->syncRolesForTenant(['tesoureiro'], $tenant->id);

        $this->assertTrue($user->hasRoleInTenant('tesoureiro', $tenant->id));
        $this->assertTrue($user->checkPermissionTo('view_any_financial::receipt'));
        $this->assertTrue($user->can('view_any_financial::receipt'));
        $this->assertTrue($user->canAccessPanel(Panel::make()->id('admin')));

        $other = Tenant::create(['name' => 'Tenant sem permissão', 'slug' => 'tenant-sem-permissao', 'active' => true]);
        $other->users()->attach($user->id, ['tenant_name' => 'Membro comum', 'roles' => json_encode(['associado']), 'status' => true]);
        session(['tenant_id' => $other->id]);

        $this->assertFalse($user->checkPermissionTo('view_any_financial::receipt'));
        $this->assertFalse($user->can('view_any_financial::receipt'));
        $this->assertFalse($user->canAccessPanel(Panel::make()->id('admin')));
    }

    public function test_financial_portal_creates_server_calculated_draft_inside_current_tenant(): void
    {
        [$tenant, $user, $account] = $this->context();
        $this->grantFinancialPortalRole($user, $tenant);

        $this->get(route('finance.index', ['tenant' => $tenant->slug]))->assertOk();
        $this->get(route('finance.receipts.index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Recebimentos');
        $this->get(route('finance.receipts.create', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Novo recebimento');
        $response = $this->post(route('finance.receipts.store', ['tenant' => $tenant->slug]), [
            'payer_type' => 'other', 'payer_name' => 'Pagador Teste', 'received_on' => now()->toDateString(),
            'bank_account_id' => $account->id, 'payment_method' => 'pix', 'total_amount' => 999999,
            'items' => [['description' => 'Mensalidade', 'quantity' => 2, 'unit' => 'un', 'unit_price' => 12.50, 'reference' => 'REF-1']],
        ]);

        $receipt = FinancialReceipt::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('finance.receipts.show', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt]));
        $this->assertSame('25.00', $receipt->total_amount);
        $this->assertSame($tenant->id, $receipt->tenant_id);
        $this->assertSame('25.00', $receipt->items()->firstOrFail()->total_amount);
        $this->get(route('finance.receipts.show', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt]))
            ->assertOk()
            ->assertSee('Pagador Teste');
        $this->get(route('finance.receipts.edit', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt]))
            ->assertOk()
            ->assertSee('Editar rascunho');
    }

    public function test_financial_portal_rejects_cross_tenant_account_and_cross_tenant_url(): void
    {
        [$tenant, $user] = $this->context();
        $this->grantFinancialPortalRole($user, $tenant);
        $other = Tenant::create(['name' => 'Tenant externo', 'slug' => 'tenant-externo-'.uniqid(), 'active' => true]);
        $externalAccount = new BankAccount(['name' => 'Conta externa', 'type' => 'caixa', 'initial_balance' => 0, 'current_balance' => 0, 'status' => true]);
        $externalAccount->tenant_id = $other->id;
        $externalAccount->save();

        $this->post(route('finance.receipts.store', ['tenant' => $tenant->slug]), [
            'payer_type' => 'other', 'payer_name' => 'Pagador', 'received_on' => now()->toDateString(),
            'bank_account_id' => $externalAccount->id, 'payment_method' => 'dinheiro',
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit' => 'un', 'unit_price' => 10]],
        ])->assertSessionHasErrors('bank_account_id');

        $this->getJson(route('finance.index', ['tenant' => $other->slug]))->assertForbidden();
        $this->assertDatabaseCount('financial_receipts', 0);
    }

    public function test_finance_management_api_is_tenant_scoped_and_permission_protected(): void
    {
        [$tenant, $user] = $this->context();
        $this->grantFinancialPortalRole($user, $tenant);

        $this->getJson(route('finance.management.data', ['tenant' => $tenant->slug, 'module' => 'accounts']))
            ->assertForbidden();

        $permissions = collect(['view_any_bank::account', 'view_bank::account', 'create_bank::account', 'update_bank::account', 'delete_bank::account'])
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        Role::query()->where('name', 'tesoureiro')->firstOrFail()->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $other = Tenant::create(['name' => 'Outro tenant', 'slug' => 'outro-financeiro-'.uniqid(), 'active' => true]);
        $response = $this->postJson(route('finance.management.store', ['tenant' => $tenant->slug, 'module' => 'accounts']), [
            'tenant_id' => $other->id,
            'name' => 'Caixa secundario',
            'type' => 'caixa',
            'initial_balance' => 75,
            'is_default' => false,
            'status' => true,
        ])->assertCreated();

        $accountId = $response->json('data.id');
        $this->assertDatabaseHas('bank_accounts', [
            'id' => $accountId,
            'tenant_id' => $tenant->id,
            'current_balance' => 75,
        ]);
        $this->getJson(route('finance.management.data', ['tenant' => $tenant->slug, 'module' => 'accounts']))
            ->assertOk()->assertJsonPath('meta.total', 2);
        $this->putJson(route('finance.management.update', ['tenant' => $tenant->slug, 'module' => 'accounts', 'record' => 999999]), [
            'name' => 'Tentativa', 'type' => 'caixa', 'initial_balance' => 0,
        ])->assertNotFound();
        $this->getJson(route('finance.management.data', ['tenant' => $other->slug, 'module' => 'accounts']))
            ->assertForbidden();
        $this->get(route('finance.management.show', ['tenant' => $tenant->slug, 'module' => 'accounts', 'record' => $accountId]))
            ->assertOk()
            ->assertSee('Caixa secundario');
        $this->deleteJson(route('finance.management.destroy', ['tenant' => $tenant->slug, 'module' => 'accounts', 'record' => $accountId]))
            ->assertStatus(405);
        $this->assertDatabaseHas('bank_accounts', ['id' => $accountId, 'tenant_id' => $tenant->id]);
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'Cooperativa Teste', 'slug' => 'cooperativa-teste-'.uniqid(), 'active' => true]);
        $user = User::create(['name' => 'Tesoureiro', 'email' => uniqid().'@example.test', 'password' => 'secret-value', 'status' => true]);
        $tenant->users()->attach($user->id, ['tenant_name' => 'Tesoureiro Teste', 'status' => true]);
        $this->actingAs($user);
        session(['tenant_id' => $tenant->id]);

        $account = new BankAccount([
            'name' => 'Caixa principal', 'type' => 'caixa', 'initial_balance' => 100,
            'current_balance' => 100, 'status' => true, 'is_default' => true,
        ]);
        $account->tenant_id = $tenant->id;
        $account->save();

        return [$tenant, $user, $account];
    }

    private function grantFinancialPortalRole(User $user, Tenant $tenant): void
    {
        $permissions = collect([
            'view_any_financial::receipt', 'view_financial::receipt', 'create_financial::receipt',
            'update_financial::receipt', 'financial_receipt.issue', 'financial_receipt.cancel',
        ])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        $role = Role::firstOrCreate(['name' => 'tesoureiro', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        $user->syncRolesForTenant(['tesoureiro'], $tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function draftReceipt(Tenant $tenant, User $user, BankAccount $account): FinancialReceipt
    {
        $receipt = new FinancialReceipt([
            'payer_type' => 'other', 'payer_name' => 'Pagador Teste', 'received_on' => '2026-08-01',
            'bank_account_id' => $account->id, 'payment_method' => PaymentMethod::DINHEIRO,
            'purpose' => 'Produtos diversos', 'created_by' => $user->id,
        ]);
        $receipt->tenant_id = $tenant->id;
        $receipt->status = FinancialReceiptStatus::DRAFT;
        $receipt->save();
        $receipt->items()->create([
            'position' => 1, 'description' => 'Produto A', 'quantity' => 3,
            'unit' => 'kg', 'unit_price' => 50, 'reference' => 'LOTE-01',
        ]);

        return $receipt->fresh();
    }
}
