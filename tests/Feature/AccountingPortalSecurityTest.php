<?php

namespace Tests\Feature;

use App\Enums\BillingAuthorizationStatus;
use App\Enums\CustomerReceiptStatus;
use App\Models\BillingAuthorization;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\User;
use App\Services\Accounting\AccountingBillingService;
use App\Services\Accounting\AccountingProcessIntegrityService;
use App\Services\Accounting\BillingAuthorizationNotificationService;
use App\Services\Accounting\BillingAuthorizationSnapshotService;
use App\Services\Accounting\BillingAuthorizationValidityService;
use App\Services\Accounting\FiscalGateService;
use App\Services\Accounting\FiscalProfileService;
use App\Services\DeletedDistributionService;
use App\Services\DeliveryParentRecoveryService;
use App\Services\TenantNotificationDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountingPortalSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'activity_log', 'documents', 'cloud_documents', 'tenant_cloud_storage_connections', 'accounting_access_scopes', 'customer_project_fees', 'customer_receipt_payments', 'associate_receipts',
            'financial_document_identities',
            'fiscal_profiles', 'billing_authorizations', 'organization_authorized_emails', 'sales_project_organizations',
            'production_deliveries', 'products', 'associates', 'customer_billing_receipts',
            'customers', 'organizations', 'sales_projects', 'bank_accounts',
            'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'permissions',
            'roles', 'tenant_user', 'tenants', 'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $this->createIdentitySchema();
        $this->createAccountingSchema();
        $this->seedAccountingScenario();
        $this->app->instance(BillingAuthorizationNotificationService::class, Mockery::mock(BillingAuthorizationNotificationService::class)->shouldIgnoreMissing());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_accountant_can_open_portal_and_receives_only_tenant_processes(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->get('/tenant-a/accounting')
            ->assertOk()
            ->assertSee('Portal Contábil');

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'processes.data')
            ->assertJsonPath('processes.data.0.number', 'COM-A-0001')
            ->assertJsonPath('processes.data.0.net', 180)
            ->assertJsonPath('processes.data.0.distributions', 1);
    }

    public function test_reverse_receipts_screen_requires_create_permission_and_is_tenant_scoped(): void
    {
        $user = User::query()->findOrFail(1);
        $this->actingAs($user)->get('/tenant-a/accounting/billings/10/member-receipts')->assertForbidden();
        $permission = \Spatie\Permission\Models\Permission::findOrCreate('create_associate::receipt', 'web');
        $user->givePermissionTo($permission);
        $this->get('/tenant-a/accounting/billings/10/member-receipts')->assertOk()
            ->assertSee('Comprovantes dos membros')->assertSee('Bruto R$');
        $this->get('/tenant-a/accounting/billings/20/member-receipts')->assertNotFound();
    }

    public function test_producer_can_inspect_paid_distributions_and_both_document_links_without_mutating_them(): void
    {
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)
            ->update(['roles' => json_encode(['registrador_entregas'])]);
        DB::table('associate_receipts')->insert([
            'id' => 40, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'receipt_year' => 2026, 'receipt_number' => 6, 'receipt_label' => 'MEM-0006',
            'issued_at' => '2026-08-19', 'status' => 'paid', 'total_net' => 180,
            'amount_paid' => 180, 'delivery_ids' => json_encode([301]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->where('id', 301)->update(['associate_receipt_id' => 40, 'paid' => true, 'billing_status' => 'paid']);
        $before = (array) DB::table('production_deliveries')->where('id', 301)->first();
        $this->actingAs(User::query()->findOrFail(1))
            ->getJson('/tenant-a/delivery/projects/10/associates/1/deliveries?inspect=1&receipt_id=40')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1)->assertJsonPath('0.id', 301)
            ->assertJsonPath('0.associate_receipt_number', 'MEM-0006')
            ->assertJsonPath('0.billing_receipt_number', 'COM-A-0001')
            ->assertJsonPath('0.paid', true)->assertJsonPath('0.available_for_receipt', false);
        self::assertSame($before, (array) DB::table('production_deliveries')->where('id', 301)->first());
    }

    public function test_producer_inspection_includes_billed_uncovered_items_and_rejects_another_members_receipt(): void
    {
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)
            ->update(['roles' => json_encode(['registrador_entregas'])]);
        DB::table('associate_receipts')->insert([
            'id' => 40, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 99,
            'receipt_year' => 2026, 'receipt_number' => 6, 'issued_at' => '2026-08-19', 'status' => 'paid',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::query()->findOrFail(1))
            ->getJson('/tenant-a/delivery/projects/10/associates/1/deliveries?inspect=1')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.billing_receipt_id', 10)
            ->assertJsonPath('0.associate_receipt_id', null)->assertJsonPath('0.available_for_receipt', true);
        $this->getJson('/tenant-a/delivery/projects/10/associates/1/deliveries?inspect=1&receipt_id=40')->assertNotFound();
        $this->getJson('/tenant-a/delivery/projects/20/associates/1/deliveries?inspect=1')->assertNotFound();
    }

    public function test_producer_edit_selection_displays_draft_billing_saved_without_a_distribution_foreign_key(): void
    {
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)
            ->update(['roles' => json_encode(['registrador_entregas'])]);
        DB::table('associate_receipts')->insert([
            'id' => 40, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'receipt_year' => 2026, 'receipt_number' => 6, 'issued_at' => '2026-08-19',
            'status' => 'pending_payment', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->where('id', 301)->update(['billing_receipt_id' => null, 'associate_receipt_id' => 40]);
        DB::table('customer_billing_receipts')->where('id', 10)->update(['status' => 'draft', 'delivery_ids' => json_encode([301])]);
        // Um snapshot de outro tenant não deve vazar nem gerar falso conflito.
        DB::table('customer_billing_receipts')->where('id', 20)->update(['delivery_ids' => json_encode([301])]);

        $this->actingAs(User::query()->findOrFail(1))
            ->getJson('/tenant-a/delivery/projects/10/associates/1/deliveries?approved_only=1&receipt_id=40')
            ->assertOk()->assertJsonPath('0.in_current_receipt', true)
            ->assertJsonPath('0.billing_receipt_id', null)
            ->assertJsonCount(1, '0.billing_references')
            ->assertJsonPath('0.billing_references.0.id', 10)
            ->assertJsonPath('0.billing_references.0.number', 'COM-A-0001')
            ->assertJsonPath('0.billing_references.0.source', 'draft');
        self::assertNull(DB::table('production_deliveries')->where('id', 301)->value('billing_receipt_id'));
    }

    public function test_producer_distinguishes_historical_billing_snapshot_from_a_current_link(): void
    {
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)
            ->update(['roles' => json_encode(['registrador_entregas'])]);
        DB::table('production_deliveries')->where('id', 301)->update(['billing_receipt_id' => null]);
        DB::table('customer_billing_receipts')->where('id', 10)->update(['delivery_ids' => json_encode([301])]);
        $this->actingAs(User::query()->findOrFail(1))
            ->getJson('/tenant-a/delivery/projects/10/associates/1/deliveries?inspect=1')
            ->assertOk()->assertJsonPath('0.billing_references.0.source', 'snapshot')
            ->assertJsonPath('0.billing_receipt_id', null);
        DB::table('production_deliveries')->where('id', 301)->update(['billing_receipt_id' => 10]);
        $this->getJson('/tenant-a/delivery/projects/10/associates/1/deliveries?inspect=1')
            ->assertOk()->assertJsonCount(1, '0.billing_references')
            ->assertJsonPath('0.billing_references.0.source', 'linked');
    }

    public function test_reverse_receipts_uses_only_the_billings_current_distributions_and_draft_selection(): void
    {
        $service = app(\App\Services\Accounting\BillingAssociateReceiptService::class);
        $billing = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        self::assertSame([301], $service->distributions($billing)->pluck('id')->all());

        $billing->forceFill(['status' => CustomerReceiptStatus::DRAFT, 'delivery_ids' => []])->saveQuietly();
        self::assertSame([], $service->distributions($billing)->pluck('id')->all());

        $billing->forceFill(['delivery_ids' => [300, 301, 401]])->saveQuietly();
        self::assertSame([301], $service->distributions($billing)->pluck('id')->all());
    }

    public function test_reverse_receipts_creates_member_snapshot_without_changing_customer_billing_and_rejects_replay(): void
    {
        $this->prepareReverseReceiptSchema();
        $billing = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        $before = $billing->getRawOriginal();
        $service = app(\App\Services\Accounting\BillingAssociateReceiptService::class);
        $receipts = \App\Models\AssociateReceipt::withoutEvents(fn () => $service->create($billing, [301], '2026-10-21'));

        self::assertCount(1, $receipts);
        self::assertSame('pending_payment', $receipts[0]->status->value);
        self::assertSame([301], $receipts[0]->delivery_ids);
        self::assertSame('200.0000', $receipts[0]->total_net);
        self::assertSame($receipts[0]->id, ProductionDelivery::withoutGlobalScopes()->findOrFail(301)->associate_receipt_id);
        self::assertSame(10, ProductionDelivery::withoutGlobalScopes()->findOrFail(301)->billing_receipt_id);
        self::assertSame($before, $billing->fresh()->getRawOriginal());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Já pertence ao comprovante');
        $service->create($billing, [301], '2026-10-21');
    }

    public function test_reverse_receipts_rejects_foreign_selection_without_creating_any_receipt(): void
    {
        $service = app(\App\Services\Accounting\BillingAssociateReceiptService::class);
        try {
            $service->create(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10), [301, 401], '2026-10-21');
            self::fail('A seleção de outro tenant deve ser rejeitada.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('seleção do faturamento mudou', $exception->getMessage());
        }
        self::assertSame(0, DB::table('associate_receipts')->count());
        self::assertNull(DB::table('production_deliveries')->where('id', 301)->value('associate_receipt_id'));
    }

    public function test_reverse_receipts_groups_by_member_and_rolls_back_all_groups_on_invalid_parent(): void
    {
        $this->prepareReverseReceiptSchema();
        $parent = (array) DB::table('production_deliveries')->where('id', 300)->first();
        $row = (array) DB::table('production_deliveries')->where('id', 301)->first();
        DB::table('associates')->insert(['id' => 2, 'tenant_id' => 1, 'user_id' => 2, 'nickname' => 'Outro produtor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('production_deliveries')->insert(array_replace($parent, ['id' => 302, 'associate_id' => 2]));
        DB::table('production_deliveries')->insert(array_replace($row, ['id' => 303, 'associate_id' => 2, 'parent_delivery_id' => 302, 'product_id' => 999]));
        $billing = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        $service = app(\App\Services\Accounting\BillingAssociateReceiptService::class);
        try {
            \App\Models\AssociateReceipt::withoutEvents(fn () => $service->create($billing, [301, 303], '2026-10-21'));
            self::fail('A entrega-pai incompatível deve reverter todo o lote.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('entrega-pai', $exception->getMessage());
        }
        self::assertSame(0, DB::table('associate_receipts')->count());
        self::assertNull(DB::table('production_deliveries')->where('id', 301)->value('associate_receipt_id'));

        DB::table('production_deliveries')->where('id', 303)->update(['product_id' => 1]);
        $receipts = \App\Models\AssociateReceipt::withoutEvents(fn () => $service->create($billing, [301, 303], '2026-10-21'));
        self::assertCount(2, $receipts);
        self::assertSame([1, 2], $receipts->pluck('associate_id')->all());
        self::assertSame([[301], [303]], $receipts->pluck('delivery_ids')->all());
        self::assertSame(2, $receipts->pluck('receipt_number')->unique()->count());
    }

    private function prepareReverseReceiptSchema(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Schema::table('associate_receipts', function (Blueprint $table): void {
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->decimal('total_gross', 14, 4)->default(0);
            $table->decimal('total_fees', 14, 4)->default(0);
            $table->json('fee_snapshot')->nullable();
            $table->timestamp('obsolete_at')->nullable();
            $table->unsignedBigInteger('obsolete_by')->nullable();
            $table->text('obsolete_reason')->nullable();
        });
        Schema::create('project_fees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
        });
    }

    public function test_billing_context_excludes_soft_deleted_projects_and_rejects_their_ids(): void
    {
        DB::table('sales_projects')->insert([
            'id' => 11,
            'tenant_id' => 1,
            'title' => 'Projeto A',
            'code' => 'PA-REMOVIDO',
            'status' => 'active',
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/billings/context')
            ->assertOk()
            ->assertJsonCount(1, 'projects')
            ->assertJsonPath('projects.0.id', 10);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/billings/context?project_ids[]=11')
            ->assertUnprocessable();
    }

    public function test_accounting_portal_lists_source_receipts_and_shows_their_distributions(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 40, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'receipt_year' => 2026, 'receipt_number' => 8, 'receipt_label' => 'SRC-A-0008',
            'issued_at' => '2026-08-19', 'status' => 'paid', 'total_net' => 180,
            'amount_paid' => 180, 'delivery_ids' => json_encode([301]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->where('id', 301)->update(['associate_receipt_id' => 40]);

        $user = User::query()->findOrFail(1);
        $this->actingAs($user)->get('/tenant-a/accounting/source-receipts')
            ->assertOk()->assertSee('Localizar comprovantes e entregas');
        $this->actingAs($user)->getJson('/tenant-a/accounting/data/source-receipts?search=SRC-A-0008')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'receipts.data')
            ->assertJsonPath('receipts.data.0.id', 40)
            ->assertJsonPath('receipts.data.0.distribution_count', 1);
        $this->actingAs($user)->getJson('/tenant-a/accounting/data/source-receipts/40')
            ->assertOk()->assertJsonCount(1, 'distributions')
            ->assertJsonPath('distributions.0.id', 301)
            ->assertJsonPath('distributions.0.parent.id', 300)
            ->assertJsonPath('distributions.0.parent.quantity', 20)
            ->assertJsonPath('distributions.0.fees', 20)
            ->assertJsonPath('distributions.0.net', 180)
            ->assertJsonPath('distributions.0.billing_references.0.number', 'COM-A-0001')
            ->assertJsonPath('distributions.0.billing_references.0.source', 'linked')
            ->assertJsonPath('processes.0.id', 10);
    }

    public function test_source_receipt_detail_includes_draft_billings_and_delivery_observations(): void
    {
        Schema::table('production_deliveries', fn (Blueprint $table) => $table->text('notes')->nullable());
        DB::table('associate_receipts')->insert([
            'id' => 40, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'receipt_year' => 2026, 'receipt_number' => 8, 'issued_at' => '2026-08-19',
            'status' => 'pending_payment', 'total_net' => 180, 'delivery_ids' => json_encode([301]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->where('id', 300)->update(['notes' => 'Conferir acondicionamento.']);
        DB::table('production_deliveries')->where('id', 301)->update(['associate_receipt_id' => 40, 'billing_receipt_id' => null]);
        DB::table('customer_billing_receipts')->where('id', 10)->update(['status' => 'draft', 'delivery_ids' => json_encode([301])]);
        $this->actingAs(User::query()->findOrFail(1))->getJson('/tenant-a/accounting/data/source-receipts/40')
            ->assertOk()->assertJsonPath('distributions.0.notes', 'Conferir acondicionamento.')
            ->assertJsonPath('distributions.0.billing_references.0.source', 'draft')
            ->assertJsonPath('distributions.0.billing_references.0.number', 'COM-A-0001')
            ->assertJsonPath('processes.0.id', 10);
    }

    public function test_source_receipt_detail_is_tenant_scoped(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 41, 'tenant_id' => 2, 'sales_project_id' => 20, 'associate_id' => 999,
            'receipt_year' => 2026, 'receipt_number' => 1, 'receipt_label' => 'SRC-B-0001',
            'issued_at' => '2026-08-19', 'status' => 'paid', 'total_net' => 900,
            'amount_paid' => 900, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1))
            ->getJson('/tenant-a/accounting/data/source-receipts/41')
            ->assertNotFound();
    }

    public function test_treasurer_can_authorize_the_organization_email_inside_the_accounting_flow(): void
    {
        DB::table('organization_authorized_emails')->where('tenant_id', 1)->delete();
        DB::table('organizations')->where('id', 1)->update([
            'email' => 'responsavel@prefeitura.test',
            'responsible_name' => 'Responsável Financeira',
        ]);

        $this->actingAs(User::query()->findOrFail(1))
            ->postJson('/tenant-a/accounting/data/processes/10/authorization/access', [
                'email' => 'RESPONSAVEL@PREFEITURA.TEST',
                'name' => 'Responsável Financeira',
            ])->assertOk()
            ->assertJsonPath('access.configured', true)
            ->assertJsonPath('access.recipients.0.email', 'responsavel@prefeitura.test');

        $this->assertDatabaseHas('organization_authorized_emails', [
            'tenant_id' => 1,
            'organization_id' => 1,
            'email' => 'responsavel@prefeitura.test',
            'active' => true,
        ]);
    }

    public function test_receipt_from_another_tenant_is_not_addressable_by_id(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->get('/tenant-a/accounting/processes/20')
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes/20')
            ->assertNotFound();
    }

    public function test_user_without_accounting_role_cannot_access_portal_or_api(): void
    {
        DB::table('tenant_user')->where('user_id', 1)->where('tenant_id', 1)
            ->update(['roles' => json_encode(['visualizador_entregas'])]);
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)->get('/tenant-a/accounting')->assertForbidden();
        $this->actingAs($user)->getJson('/tenant-a/accounting/data/processes')->assertForbidden();
    }

    public function test_dossier_uses_distribution_as_financial_line_and_parent_only_as_traceability(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes/10')
            ->assertOk()
            ->assertJsonCount(1, 'distributions.data')
            ->assertJsonPath('distributions.data.0.id', 301)
            ->assertJsonPath('distributions.data.0.gross_value', 200)
            ->assertJsonPath('distributions.data.0.parent.id', 300)
            ->assertJsonPath('process.financial.net', 180)
            ->assertJsonPath('process.pdf_url', route('accounting.fiscal.billing-sheet', ['tenant' => 'tenant-a', 'receipt' => 10]))
            ->assertJsonPath('process.integrity.critical_count', 0);
    }

    private function createReportFeeSchema(): void
    {
        Schema::dropIfExists('customer_project_fees');
        Schema::create('customer_project_fees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->string('name');
            $table->string('type')->default('percentage');
            $table->string('nature')->default('discount');
            $table->decimal('value', 10, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_draft_dossier_exposes_and_renders_a_printable_preview(): void
    {
        $this->createReportFeeSchema();
        DB::table('customer_billing_receipts')->where('id', 10)->update(['status' => 'draft']);
        $user = User::query()->findOrFail(1);
        $url = route('accounting.fiscal.billing-sheet', ['tenant' => 'tenant-a', 'receipt' => 10]);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes/10')
            ->assertOk()
            ->assertJsonPath('process.pdf_url', $url);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-SGC-Document-Origin', 'generated')
            ->assertHeader('X-SGC-Document-Title', 'Prévia do faturamento COM-A-0001');

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => 10,
            'description' => 'Prévia do faturamento consultada no Portal Contábil',
        ]);
    }

    public function test_empty_draft_selection_clears_previous_financial_snapshot(): void
    {
        DB::table('customer_billing_receipts')->where('id', 10)->update(['status' => 'draft']);
        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);

        $saved = app(AccountingBillingService::class)->saveDraft(1, [
            'project_ids' => [10],
            'organization_id' => 1,
            'customer_id' => null,
            'issued_at' => '2026-08-20',
            'distribution_ids' => [],
        ], User::query()->findOrFail(1), $receipt);

        self::assertSame([], $saved->delivery_ids);
        self::assertSame(0, bccomp('0.0000', (string) $saved->total_gross, 4));
        self::assertSame(0, bccomp('0.0000', (string) $saved->total_fees, 4));
        self::assertSame(0, bccomp('0.0000', (string) $saved->total_net, 4));
        self::assertSame([], data_get($saved->fee_snapshot, 'document_lines'));
    }

    public function test_parent_delivery_with_active_distribution_cannot_be_soft_deleted(): void
    {
        $parent = ProductionDelivery::withoutGlobalScopes()->findOrFail(300);

        try {
            $parent->delete();
            self::fail('A entrega-pai com distribuições deveria ter sido protegida.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('delivery', $exception->errors());
        }

        self::assertFalse(ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(300)->trashed());
        self::assertSame(300, (int) ProductionDelivery::withoutGlobalScopes()->findOrFail(301)->parent_delivery_id);
    }

    public function test_removed_distribution_no_longer_blocks_parent_delivery_deletion(): void
    {
        DB::table('production_deliveries')->where('id', 301)->update(['deleted_at' => now()]);

        ProductionDelivery::withoutGlobalScopes()->findOrFail(300)->delete();

        self::assertTrue(ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(300)->trashed());
        self::assertTrue(ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(301)->trashed());
    }

    public function test_unused_removed_distribution_can_be_permanently_deleted_but_billed_one_cannot(): void
    {
        DB::table('production_deliveries')->insert([
            'id' => 302, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'customer_id' => 1, 'product_id' => 1, 'parent_delivery_id' => 300,
            'associate_receipt_id' => null, 'billing_receipt_id' => null, 'status' => 'approved',
            'quantity' => 1, 'unit_price' => 10, 'gross_value' => 10, 'admin_fee_amount' => 1,
            'net_value' => 9, 'billing_status' => 'unbilled', 'paid' => false,
            'delivery_date' => '2026-08-19', 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->where('id', 301)->update(['deleted_at' => now()]);
        $service = app(DeletedDistributionService::class);
        $unused = ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(302);
        $billed = ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(301);

        self::assertTrue($service->permanentDeletionStatus($unused)['allowed']);
        self::assertFalse($service->permanentDeletionStatus($billed)['allowed']);

        $service->forceDelete($unused, User::query()->findOrFail(1));
        self::assertNull(ProductionDelivery::withoutGlobalScopes()->withTrashed()->find(302));
        self::assertNotNull(ProductionDelivery::withoutGlobalScopes()->withTrashed()->find(301));
    }

    public function test_legacy_soft_deleted_parent_can_be_restored_without_changing_financial_distribution(): void
    {
        DB::table('production_deliveries')->where('id', 300)->update(['deleted_at' => now()]);
        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        $before = ProductionDelivery::withoutGlobalScopes()->findOrFail(301)
            ->only(['quantity', 'unit_price', 'gross_value', 'net_value', 'billing_receipt_id']);

        $diagnosis = app(DeliveryParentRecoveryService::class)->diagnosisForCustomerReceipt($receipt);
        self::assertSame(1, $diagnosis['recoverable']);
        self::assertSame(0, $diagnosis['unrecoverable']);
        self::assertContains(
            'deleted_parent_delivery',
            collect(app(AccountingProcessIntegrityService::class)->inspect($receipt)['issues'])->pluck('code')->all(),
        );

        $user = User::query()->findOrFail(1);
        $this->actingAs($user)->getJson('/tenant-a/accounting/data/processes/10')
            ->assertOk()
            ->assertJsonPath('process.integrity.repair_url', route('accounting.data.processes.integrity.repair', ['tenant' => 'tenant-a', 'receipt' => 10]));
        $this->actingAs($user)->postJson('/tenant-a/accounting/data/processes/10/integrity/repair')
            ->assertOk()
            ->assertJsonPath('restored.0', 300)
            ->assertJsonPath('unresolved', []);

        self::assertFalse(ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(300)->trashed());
        self::assertSame($before, ProductionDelivery::withoutGlobalScopes()->findOrFail(301)
            ->only(['quantity', 'unit_price', 'gross_value', 'net_value', 'billing_receipt_id']));
    }

    public function test_billed_distribution_cannot_be_soft_deleted(): void
    {
        $distribution = ProductionDelivery::withoutGlobalScopes()->findOrFail(301);

        try {
            $distribution->delete();
            self::fail('Uma distribuição faturada deveria estar protegida contra exclusão.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('delivery', $exception->errors());
        }

        self::assertFalse(ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(301)->trashed());
    }

    public function test_receipt_integrity_never_restores_a_deleted_distribution_automatically(): void
    {
        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        DB::table('production_deliveries')->where('id', 301)->update(['deleted_at' => now()]);

        $diagnosis = app(DeliveryParentRecoveryService::class)->diagnosisForCustomerReceipt($receipt);
        self::assertSame(0, $diagnosis['recoverable']);
        self::assertSame(0, $diagnosis['unrecoverable']);
        self::assertSame(1, $diagnosis['archived_distributions']);

        $result = app(DeliveryParentRecoveryService::class)
            ->restoreForCustomerReceipt($receipt, User::query()->findOrFail(1));

        self::assertSame([], $result['restored']);
        self::assertSame([], $result['unresolved']);
        self::assertTrue(ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(301)->trashed());
    }

    public function test_deleted_distribution_requires_manual_restore_and_revalidates_parent_capacity(): void
    {
        DB::table('production_deliveries')->where('id', 301)->update(['deleted_at' => now()]);
        $distribution = ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(301);
        $service = app(DeletedDistributionService::class);

        self::assertTrue($service->restorationStatus($distribution)['allowed']);
        DB::table('production_deliveries')->insert([
            'id' => 302, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'customer_id' => 1, 'product_id' => 1, 'parent_delivery_id' => 300,
            'associate_receipt_id' => null, 'billing_receipt_id' => null, 'status' => 'approved',
            'quantity' => 1, 'unit_price' => 10, 'gross_value' => 10, 'admin_fee_amount' => 1,
            'net_value' => 9, 'billing_status' => 'unbilled', 'paid' => false,
            'delivery_date' => '2026-08-19', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $distribution = ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(301);
        $status = $service->restorationStatus($distribution);

        self::assertFalse($status['allowed']);
        self::assertStringContainsString('ultrapassaria o recebimento', $status['reason']);
    }

    public function test_receipt_repair_does_not_restore_distribution_from_another_project_context(): void
    {
        DB::table('production_deliveries')->where('id', 300)->update(['deleted_at' => now()]);
        DB::table('production_deliveries')->where('id', 301)->update(['sales_project_id' => 999]);
        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);

        $diagnosis = app(DeliveryParentRecoveryService::class)->diagnosisForCustomerReceipt($receipt);
        self::assertSame(0, $diagnosis['recoverable']);
        self::assertSame(1, $diagnosis['unrecoverable']);

        $result = app(DeliveryParentRecoveryService::class)
            ->restoreForCustomerReceipt($receipt, User::query()->findOrFail(1));

        self::assertSame([], $result['restored']);
        self::assertSame([301], $result['unresolved']);
        self::assertTrue(ProductionDelivery::withoutGlobalScopes()->withTrashed()->findOrFail(300)->trashed());
    }

    public function test_process_page_size_is_bounded(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes?per_page=100')
            ->assertUnprocessable();
    }

    public function test_work_queue_hides_zero_count_categories_and_uses_snapshot_balance(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/queue')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'queue')
            ->assertJsonPath('queue.0.key', 'closed')
            ->assertJsonPath('queue.0.count', 1)
            ->assertJsonPath('summary.open_amount', 180);
    }

    public function test_work_queue_warns_about_approved_distributions_without_customer_billing(): void
    {
        DB::table('production_deliveries')->insert([
            'id' => 302, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'customer_id' => 1, 'product_id' => 1, 'parent_delivery_id' => 300,
            'associate_receipt_id' => null, 'billing_receipt_id' => null, 'status' => 'approved',
            'quantity' => 1, 'unit_price' => 10, 'gross_value' => 10, 'admin_fee_amount' => 1,
            'net_value' => 9, 'billing_status' => 'unbilled', 'paid' => false,
            'delivery_date' => '2026-08-19', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $queue = $this->actingAs(User::query()->findOrFail(1))
            ->getJson('/tenant-a/accounting/data/queue')
            ->assertOk();

        $item = collect($queue->json('queue'))->firstWhere('key', 'unbilled_distributions');
        self::assertNotNull($item);
        self::assertSame(1, $item['count']);
        self::assertSame(route('accounting.billings.create', ['tenant' => 'tenant-a']), $item['url']);
    }

    public function test_incomplete_draft_is_preparation_work_and_not_a_critical_inconsistency(): void
    {
        DB::table('customer_billing_receipts')->where('id', 10)->update([
            'status' => 'draft',
            'total_gross' => 0,
            'total_fees' => 0,
            'total_net' => 0,
        ]);
        DB::table('production_deliveries')->where('id', 301)->update(['billing_receipt_id' => null]);

        $user = User::query()->findOrFail(1);
        $queue = $this->actingAs($user)->getJson('/tenant-a/accounting/data/queue')->assertOk();

        self::assertSame(['drafts'], collect($queue->json('queue'))->pluck('key')->all());
        $queue->assertJsonPath('queue.0.count', 1);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes?financial_status=draft')
            ->assertOk()
            ->assertJsonPath('processes.data.0.critical_issues', 0)
            ->assertJsonPath('processes.data.0.preparation_issues', 1)
            ->assertJsonPath('processes.data.0.state.label', 'Dados a completar');

        $inspection = app(AccountingProcessIntegrityService::class)
            ->inspect(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10));
        self::assertSame(0, $inspection['critical_count']);
        self::assertSame(1, $inspection['preparation_count']);
        self::assertSame(1, $inspection['blocking_count']);
    }

    public function test_draft_uses_delivery_ids_until_freeze_in_integrity_and_dossier(): void
    {
        DB::table('customer_billing_receipts')->where('id', 10)->update([
            'status' => 'draft',
            'delivery_ids' => json_encode([301]),
        ]);
        DB::table('production_deliveries')->where('id', 301)->update(['billing_receipt_id' => null]);

        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        $inspection = app(AccountingProcessIntegrityService::class)->inspect($receipt);
        self::assertNotContains('missing_distributions', collect($inspection['issues'])->pluck('code')->all());

        $user = User::query()->findOrFail(1);
        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes/10')
            ->assertOk()
            ->assertJsonPath('process.summary.distributions', 1)
            ->assertJsonPath('distributions.total', 1)
            ->assertJsonPath('distributions.data.0.id', 301);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes?financial_status=draft')
            ->assertOk()
            ->assertJsonPath('processes.data.0.distributions', 1)
            ->assertJsonPath('processes.data.0.preparation_issues', 0);
    }

    public function test_closed_billing_reports_distributions_added_or_removed_after_snapshot(): void
    {
        DB::table('production_deliveries')->insert([
            'id' => 302, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'customer_id' => 1, 'product_id' => 1, 'parent_delivery_id' => 300,
            'associate_receipt_id' => null, 'billing_receipt_id' => 10, 'status' => 'approved',
            'quantity' => 1, 'unit_price' => 10, 'gross_value' => 10, 'admin_fee_amount' => 1,
            'net_value' => 9, 'billing_status' => 'billed', 'paid' => false,
            'delivery_date' => '2026-08-19', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        $inspection = app(AccountingProcessIntegrityService::class)->inspect($receipt);

        self::assertTrue($inspection['snapshot_changed']);
        self::assertSame([302], $inspection['snapshot_changes']['added_ids']);
        self::assertSame([], $inspection['snapshot_changes']['removed_ids']);
        self::assertContains(
            'billing_snapshot_added_distributions',
            collect($inspection['issues'])->pluck('code')->all(),
        );

        DB::table('production_deliveries')->where('id', 301)->update(['billing_receipt_id' => null]);
        $inspection = app(AccountingProcessIntegrityService::class)->inspect($receipt->fresh());
        self::assertSame([302], $inspection['snapshot_changes']['added_ids']);
        self::assertSame([301], $inspection['snapshot_changes']['removed_ids']);

        $this->actingAs(User::query()->findOrFail(1))
            ->getJson('/tenant-a/accounting/data/processes/10')
            ->assertOk()
            ->assertJsonPath('process.integrity.snapshot_changed', true)
            ->assertJsonPath('process.integrity.snapshot_changes.added_ids.0', 302)
            ->assertJsonPath('process.integrity.snapshot_changes.removed_ids.0', 301)
            ->assertJsonPath('process.reopen_url', route('accounting.billings.reopen', ['tenant' => 'tenant-a', 'receipt' => 10]));
    }

    public function test_snapshot_change_can_reopen_unpaid_billing_using_current_links(): void
    {
        DB::table('production_deliveries')->where('id', 301)->update(['billing_receipt_id' => null]);
        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);

        $draft = app(AccountingBillingService::class)
            ->reopenForCorrection($receipt, User::query()->findOrFail(1));

        self::assertSame(CustomerReceiptStatus::DRAFT, $draft->status);
        self::assertSame([], $draft->delivery_ids);
    }

    public function test_filters_cannot_expand_results_to_another_tenant(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->getJson('/tenant-a/accounting/data/processes?project=20')
            ->assertOk()
            ->assertJsonCount(0, 'processes.data');
    }

    public function test_permission_granted_to_custom_tenant_role_can_open_the_portal(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'auditor_contabil', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionIds = DB::table('permissions')->whereIn('name', [
            'view_accounting_portal', 'view_accounting_processes',
        ])->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        DB::table('tenant_user')->where('user_id', 1)->where('tenant_id', 1)
            ->update(['roles' => json_encode(['auditor_contabil'])]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs(User::query()->findOrFail(1))
            ->get('/tenant-a/accounting')
            ->assertOk();
    }

    public function test_treasurer_sends_an_immutable_snapshot_and_retry_is_idempotent(): void
    {
        $user = User::query()->findOrFail(1);
        $operationKey = (string) Str::uuid();

        $this->actingAs($user)->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => $operationKey,
        ])->assertOk()->assertJsonPath('authorization.state', 'sent');
        $this->actingAs($user)->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => $operationKey,
        ])->assertOk();

        $this->assertDatabaseCount('billing_authorizations', 1);
        $round = BillingAuthorization::withoutGlobalScopes()->firstOrFail();
        self::assertSame(1, $round->sequence);
        self::assertSame(301, data_get($round->snapshot, 'lines.0.distribution_id'));
        self::assertSame('10.0000', data_get($round->snapshot, 'lines.0.unit_price'));
        self::assertSame('180.0000', data_get($round->snapshot, 'lines.0.net'));
        self::assertCount(1, data_get($round->snapshot, 'lines'));
        self::assertSame(1, DB::table('activity_log')->where('description', 'Cobrança enviada para autorização')->count());
    }

    public function test_buyer_sees_snapshot_and_can_request_correction_then_receive_round_two(): void
    {
        $treasurer = User::query()->findOrFail(1);
        $this->actingAs($treasurer)->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertOk();
        $round = BillingAuthorization::withoutGlobalScopes()->firstOrFail();

        $buyer = User::query()->findOrFail(2);
        $this->actingAs($buyer)->get('/tenant-a/buyer/authorizations/'.$round->id)
            ->assertOk()->assertSee('COM-A-0001')->assertSee('Banana');
        $this->actingAs($buyer)->post('/tenant-a/buyer/authorizations/'.$round->id.'/request-correction', [
            'reason' => 'Corrigir o período informado.',
        ])->assertRedirect();

        self::assertSame(BillingAuthorizationStatus::CORRECTION_REQUESTED, $round->fresh()->status);
        self::assertSame(1, DB::table('activity_log')->where('description', 'Correção solicitada pela organização compradora')->count());
        $this->actingAs($treasurer)->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertOk();
        $this->assertDatabaseHas('billing_authorizations', ['customer_billing_receipt_id' => 10, 'sequence' => 2, 'status' => 'sent']);
        self::assertSame('20.0000', data_get($round->fresh()->snapshot, 'lines.0.quantity'));
    }

    public function test_only_the_target_organization_can_open_or_answer_a_round(): void
    {
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertOk();
        $round = BillingAuthorization::withoutGlobalScopes()->firstOrFail();

        $this->actingAs(User::query()->findOrFail(3))
            ->get('/tenant-b/buyer/authorizations/'.$round->id)
            ->assertNotFound();
        $this->actingAs(User::query()->findOrFail(3))
            ->post('/tenant-b/buyer/authorizations/'.$round->id.'/authorize')
            ->assertNotFound();
    }

    public function test_buyer_authorization_is_idempotent_and_material_change_invalidates_it(): void
    {
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertOk();
        $round = BillingAuthorization::withoutGlobalScopes()->firstOrFail();
        $buyer = User::query()->findOrFail(2);

        $this->actingAs($buyer)->post('/tenant-a/buyer/authorizations/'.$round->id.'/authorize')->assertRedirect();
        $this->actingAs($buyer)->post('/tenant-a/buyer/authorizations/'.$round->id.'/authorize')->assertRedirect();
        self::assertSame(BillingAuthorizationStatus::AUTHORIZED, $round->fresh()->status);
        self::assertSame(1, DB::table('activity_log')->where('description', 'Faturamento autorizado pela organização compradora')->count());

        DB::table('production_deliveries')->where('id', 301)->update(['quantity' => 15, 'gross_value' => 150]);
        app(BillingAuthorizationValidityService::class)->invalidateIfChanged(
            CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10)
        );
        $round->refresh();
        self::assertSame(BillingAuthorizationStatus::INVALIDATED, $round->status);
        self::assertSame(BillingAuthorizationStatus::AUTHORIZED->value, $round->response_decision);
        self::assertNull($round->active_marker);
        self::assertSame(1, DB::table('activity_log')->where('description', 'Autorização da organização invalidada')->count());
    }

    public function test_snapshot_hash_is_deterministic_and_changes_with_material_values(): void
    {
        session(['tenant_id' => 1, 'tenant_slug' => 'tenant-a']);
        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        $service = app(BillingAuthorizationSnapshotService::class);
        $ascending = ProductionDelivery::withoutGlobalScopes()->where('billing_receipt_id', 10)->orderBy('id')->get();
        $descending = ProductionDelivery::withoutGlobalScopes()->where('billing_receipt_id', 10)->orderByDesc('id')->get();
        $hashA = $service->hash($service->build($receipt, $ascending));
        $hashB = $service->hash($service->build($receipt, $descending));
        self::assertSame($hashA, $hashB);

        DB::table('production_deliveries')->where('id', 301)->update(['unit_price' => 11, 'gross_value' => 220]);
        $changed = ProductionDelivery::withoutGlobalScopes()->where('billing_receipt_id', 10)->get();
        self::assertNotSame($hashA, $service->hash($service->build($receipt->fresh(), $changed)));
    }

    public function test_user_with_view_permission_but_without_send_permission_cannot_send(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'accounting_reader', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table('permissions')->whereIn('name', ['view_accounting_portal', 'view_accounting_processes'])->pluck('id') as $permissionId) {
            DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)->update(['roles' => json_encode(['accounting_reader'])]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertDatabaseCount('billing_authorizations', 0);
    }

    public function test_receipt_from_another_tenant_cannot_be_sent(): void
    {
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/20/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_distribution_without_price_blocks_send_with_structured_issue(): void
    {
        DB::table('production_deliveries')->where('id', 301)->update(['unit_price' => 0]);
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonPath('issues.0.code', 'invalid_price');
    }

    public function test_totals_that_diverge_from_distribution_lines_block_send(): void
    {
        DB::table('customer_billing_receipts')->where('id', 10)->update(['total_net' => 170]);
        $response = $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertUnprocessable();
        self::assertContains('snapshot_net_mismatch', collect($response->json('issues'))->pluck('code')->all());
    }

    public function test_different_retry_key_does_not_create_second_active_round(): void
    {
        $this->sendRound();
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonPath('issues.0.code', 'active_round_exists');
        $this->assertDatabaseCount('billing_authorizations', 1);
    }

    public function test_snapshot_freezes_fee_definition_and_calculated_amount(): void
    {
        $round = $this->sendRound();
        self::assertSame('10.0000', data_get($round->snapshot, 'fees.calculated.0.rate'));
        self::assertSame('20.0000', data_get($round->snapshot, 'fees.calculated.0.amount'));
        self::assertSame('20.0000', data_get($round->snapshot, 'totals.fees'));
    }

    public function test_historical_snapshot_does_not_change_with_current_distribution(): void
    {
        $round = $this->sendRound();
        DB::table('production_deliveries')->where('id', 301)->update(['quantity' => 99, 'unit_price' => 50, 'gross_value' => 4950]);
        $round->refresh();
        self::assertSame('20.0000', data_get($round->snapshot, 'lines.0.quantity'));
        self::assertSame('10.0000', data_get($round->snapshot, 'lines.0.unit_price'));
        self::assertSame('200.0000', data_get($round->snapshot, 'totals.gross'));
    }

    public function test_correction_reason_is_required(): void
    {
        $round = $this->sendRound();
        $this->actingAs(User::query()->findOrFail(2))
            ->post('/tenant-a/buyer/authorizations/'.$round->id.'/request-correction', ['reason' => ''])
            ->assertSessionHasErrors('reason');
        self::assertSame(BillingAuthorizationStatus::SENT, $round->fresh()->status);
    }

    public function test_answered_round_cannot_receive_an_opposite_response(): void
    {
        $round = $this->sendRound();
        $buyer = User::query()->findOrFail(2);
        $this->actingAs($buyer)->post('/tenant-a/buyer/authorizations/'.$round->id.'/authorize')->assertRedirect();
        $this->actingAs($buyer)->post('/tenant-a/buyer/authorizations/'.$round->id.'/request-correction', [
            'reason' => 'Quero trocar a decisão anterior.',
        ])->assertSessionHasErrors('authorization');
        self::assertSame(BillingAuthorizationStatus::AUTHORIZED, $round->fresh()->status);
    }

    public function test_user_without_active_authorized_email_does_not_see_buyer_round(): void
    {
        $round = $this->sendRound();
        DB::table('organization_authorized_emails')->where('tenant_id', 1)->update(['active' => false]);
        $this->actingAs(User::query()->findOrFail(2))
            ->get('/tenant-a/buyer/authorizations/'.$round->id)
            ->assertForbidden();
    }

    public function test_legacy_receipt_does_not_receive_a_fictitious_authorization(): void
    {
        $this->assertDatabaseCount('billing_authorizations', 0);
        $this->actingAs(User::query()->findOrFail(1))->getJson('/tenant-a/accounting/data/processes/10')
            ->assertOk()->assertJsonPath('process.workflow.authorization.state', 'legacy_unsubmitted');
    }

    public function test_fee_change_invalidates_authorized_round(): void
    {
        $round = $this->authorizeRound();
        DB::table('customer_billing_receipts')->where('id', 10)->update([
            'total_fees' => 10,
            'total_net' => 190,
            'fee_snapshot' => json_encode(['fees' => [['id' => 1, 'name' => 'Taxa', 'type' => 'percentage', 'nature' => 'discount', 'rate' => '5']], 'total_fee' => '10']),
        ]);
        app(BillingAuthorizationValidityService::class)->invalidateIfChanged(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10));
        self::assertSame(BillingAuthorizationStatus::INVALIDATED, $round->fresh()->status);
    }

    public function test_recipient_change_invalidates_authorized_round(): void
    {
        $round = $this->authorizeRound();
        DB::table('organizations')->insert(['id' => 3, 'tenant_id' => 1, 'name' => 'Prefeitura C', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customer_billing_receipts')->where('id', 10)->update(['organization_id' => 3]);
        app(BillingAuthorizationValidityService::class)->invalidateIfChanged(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10));
        self::assertSame(BillingAuthorizationStatus::INVALIDATED, $round->fresh()->status);
    }

    public function test_incompatible_financial_status_invalidates_authorization_even_with_same_hash(): void
    {
        $round = $this->authorizeRound();
        DB::table('customer_billing_receipts')->where('id', 10)->update(['status' => 'draft']);
        app(BillingAuthorizationValidityService::class)->invalidateIfChanged(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10));
        self::assertSame(BillingAuthorizationStatus::INVALIDATED, $round->fresh()->status);
        self::assertStringContainsString('situação financeira', (string) $round->fresh()->invalidation_reason);
    }

    public function test_material_note_change_invalidates_authorized_round(): void
    {
        $round = $this->authorizeRound();
        DB::table('customer_billing_receipts')->where('id', 10)->update(['notes' => 'Condição apresentada à organização.']);
        app(BillingAuthorizationValidityService::class)->invalidateIfChanged(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10));
        self::assertSame(BillingAuthorizationStatus::INVALIDATED, $round->fresh()->status);
    }

    public function test_pure_timestamp_change_is_not_material(): void
    {
        $round = $this->authorizeRound();
        DB::table('customer_billing_receipts')->where('id', 10)->update(['updated_at' => now()->addMinute()]);
        $result = app(BillingAuthorizationValidityService::class)->invalidateIfChanged(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10));
        self::assertNull($result);
        self::assertSame(BillingAuthorizationStatus::AUTHORIZED, $round->fresh()->status);
    }

    public function test_cancel_requires_a_reason(): void
    {
        $round = $this->sendRound();
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorizations/'.$round->id.'/cancel', [
            'reason' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_authorized_user_can_cancel_an_active_round_without_deleting_history(): void
    {
        $round = $this->sendRound();
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorizations/'.$round->id.'/cancel', [
            'reason' => 'Envio feito para a organização incorreta.',
        ])->assertOk();
        $round->refresh();
        self::assertSame(BillingAuthorizationStatus::CANCELLED, $round->status);
        self::assertNull($round->active_marker);
        $this->assertDatabaseCount('billing_authorizations', 1);
    }

    public function test_cancelled_round_can_be_followed_by_sequence_two(): void
    {
        $round = $this->sendRound();
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorizations/'.$round->id.'/cancel', [
            'reason' => 'Será gerada uma nova versão corrigida.',
        ])->assertOk();
        $newRound = $this->sendRound();
        self::assertSame(2, $newRound->sequence);
        self::assertSame(BillingAuthorizationStatus::CANCELLED, $round->fresh()->status);
    }

    public function test_queue_moves_sent_receipt_out_of_ready_and_into_waiting(): void
    {
        $this->sendRound();
        $response = $this->actingAs(User::query()->findOrFail(1))->getJson('/tenant-a/accounting/data/queue')->assertOk();
        self::assertSame(['awaiting_authorization'], collect($response->json('queue'))->pluck('key')->all());
    }

    public function test_queue_shows_correction_requested_state(): void
    {
        $round = $this->sendRound();
        $this->actingAs(User::query()->findOrFail(2))->post('/tenant-a/buyer/authorizations/'.$round->id.'/request-correction', [
            'reason' => 'Corrigir os valores apresentados.',
        ])->assertRedirect();
        $response = $this->actingAs(User::query()->findOrFail(1))->getJson('/tenant-a/accounting/data/queue')->assertOk();
        self::assertSame(['correction_requested'], collect($response->json('queue'))->pluck('key')->all());
    }

    public function test_queue_shows_authorized_state(): void
    {
        $this->authorizeRound();
        $response = $this->actingAs(User::query()->findOrFail(1))->getJson('/tenant-a/accounting/data/queue')->assertOk();
        self::assertSame(['authorized'], collect($response->json('queue'))->pluck('key')->all());
    }

    public function test_organization_without_project_participation_cannot_receive_round(): void
    {
        DB::table('sales_project_organizations')->where('sales_project_id', 10)->delete();
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonPath('issues.0.code', 'organization_not_participant');
    }

    public function test_snapshot_does_not_expose_producer_identity(): void
    {
        $round = $this->sendRound();
        self::assertArrayNotHasKey('associate', data_get($round->snapshot, 'lines.0'));
        self::assertStringNotContainsString('Produtora', json_encode($round->snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_send_notification_targets_only_users_with_active_email_for_the_organization(): void
    {
        $round = $this->sendRound()->fresh(['receipt.tenant']);
        $dispatcher = Mockery::mock(TenantNotificationDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->with(
            'billing.authorization.requested',
            1,
            Mockery::on(fn ($users): bool => collect($users)->pluck('id')->all() === [2]),
            Mockery::on(fn (array $message): bool => str_contains($message['url'], '/tenant-a/buyer/authorizations/'.$round->id)),
        )->andReturn(1);

        (new BillingAuthorizationNotificationService($dispatcher))->sent($round, false);
    }

    public function test_valid_authorization_and_complete_profile_are_ready_for_fiscal_preparation(): void
    {
        $this->authorizeRound();
        $this->createFiscalProfile();

        $gate = app(FiscalGateService::class)->evaluate(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10), 1);

        self::assertTrue($gate['ready']);
        self::assertSame('ready', $gate['status']);
        self::assertSame('180.0000', $gate['expected_fiscal_amount']);
        self::assertSame('nfse', $gate['document_type']);
        self::assertSame([], $gate['blocks']);
    }

    public function test_fiscal_gate_treats_buyer_authorization_as_optional_but_requires_profile(): void
    {
        $gate = app(FiscalGateService::class)->evaluate(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10), 1);

        self::assertFalse($gate['ready']);
        self::assertNotContains('authorization_missing', collect($gate['blocks'])->pluck('code')->all());
        self::assertContains('buyer_authorization_not_sent', collect($gate['warnings'])->pluck('code')->all());
        self::assertContains('fiscal_profile_missing', collect($gate['blocks'])->pluck('code')->all());
    }

    public function test_fiscal_gate_warns_but_does_not_block_on_optional_outdated_authorizations(): void
    {
        $round = $this->sendRound();
        $this->createFiscalProfile();
        $sent = app(FiscalGateService::class)->evaluate(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10), 1);
        self::assertNotContains('authorization_invalid', collect($sent['blocks'])->pluck('code')->all());
        self::assertContains('buyer_authorization_outdated', collect($sent['warnings'])->pluck('code')->all());

        $round->forceFill(['status' => BillingAuthorizationStatus::INVALIDATED, 'active_marker' => true])->save();
        $invalidated = app(FiscalGateService::class)->evaluate(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10), 1);
        self::assertNotContains('authorization_invalid', collect($invalidated['blocks'])->pluck('code')->all());
        self::assertContains('buyer_authorization_outdated', collect($invalidated['warnings'])->pluck('code')->all());

        $round->forceFill(['status' => BillingAuthorizationStatus::AUTHORIZED, 'snapshot_hash' => str_repeat('0', 64)])->save();
        $outdated = app(FiscalGateService::class)->evaluate(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10), 1);
        self::assertNotContains('authorization_invalid', collect($outdated['blocks'])->pluck('code')->all());
        self::assertContains('buyer_authorization_outdated', collect($outdated['warnings'])->pluck('code')->all());
    }

    public function test_inactive_or_incomplete_fiscal_profile_blocks_preparation(): void
    {
        $this->authorizeRound();
        $this->createFiscalProfile(['status' => 'draft', 'active_marker' => null, 'document_type' => null, 'amount_source' => null]);
        $gate = app(FiscalGateService::class)->evaluate(CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10), 1);
        $codes = collect($gate['blocks'])->pluck('code')->all();

        self::assertFalse($gate['ready']);
        self::assertContains('fiscal_profile_inactive', $codes);
        self::assertContains('document_type_missing', $codes);
        self::assertContains('fiscal_amount_source_missing', $codes);
        self::assertContains('fiscal_amount_unresolved', $codes);
    }

    public function test_outdated_optional_authorization_is_ignored_in_favor_of_current_frozen_billing(): void
    {
        $round = $this->authorizeRound();
        $this->createFiscalProfile(['amount_source' => 'authorized_gross']);
        DB::table('customer_billing_receipts')->where('id', 10)->update(['total_gross' => 999]);

        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(10);
        $gate = app(FiscalGateService::class)->evaluate($receipt, 1);

        self::assertTrue($gate['ready']);
        self::assertSame('200.0000', data_get($round->snapshot, 'totals.gross'));
        self::assertSame('999.0000', $gate['expected_fiscal_amount']);
        self::assertContains('buyer_authorization_outdated', collect($gate['warnings'])->pluck('code')->all());
    }

    public function test_fiscal_queue_and_prepare_flow_are_permission_and_tenant_aware(): void
    {
        $this->authorizeRound();
        $this->createFiscalProfile();
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)->get('/tenant-a/accounting/fiscal')->assertOk();
        $this->actingAs($user)->get('/tenant-a/accounting/fiscal/settings?receipt=10&project=10')
            ->assertOk()->assertSee('Salvar e abrir faturamento completo');
        $this->actingAs($user)->getJson('/tenant-a/accounting/data/fiscal')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'processes.data')
            ->assertJsonPath('processes.data.0.gate', 'ready')
            ->assertJsonPath('processes.data.0.action', 'Imprimir faturamento completo')
            ->assertJsonMissingPath('processes.data.0.authorization.snapshot');
        $this->actingAs($user)->get('/tenant-a/accounting/fiscal/10')->assertOk()->assertSee('Abrir faturamento completo');
        $this->actingAs($user)->get('/tenant-a/accounting/fiscal/10/billing-sheet')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($user)->postJson('/tenant-a/accounting/fiscal/10/prepare')->assertOk();
        $this->actingAs($user)->get('/tenant-a/accounting/fiscal/20')->assertNotFound();
        $this->actingAs($user)->get('/tenant-a/accounting/fiscal/20/billing-sheet')->assertNotFound();
        $this->assertDatabaseHas('activity_log', ['subject_id' => 10, 'description' => 'Preparação fiscal iniciada']);
    }

    public function test_saving_active_configuration_from_billing_opens_printable_sheet_without_buyer_access(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)->post('/tenant-a/accounting/fiscal/settings', [
            'project_id' => 10,
            'receipt_id' => 10,
            'document_type' => 'nfse',
            'amount_source' => 'authorized_final',
            'active' => 1,
        ])->assertRedirect('/tenant-a/accounting/fiscal/10/billing-sheet');

        $this->assertDatabaseHas('fiscal_profiles', [
            'tenant_id' => 1,
            'sales_project_id' => 10,
            'status' => 'active',
            'active_marker' => 1,
        ]);
        $this->assertDatabaseCount('billing_authorizations', 0);
    }

    public function test_user_without_fiscal_permission_receives_forbidden(): void
    {
        DB::table('tenant_user')->where('user_id', 1)->where('tenant_id', 1)->update(['roles' => json_encode(['visualizador_entregas'])]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)->get('/tenant-a/accounting/fiscal')->assertForbidden();
        $this->actingAs($user)->getJson('/tenant-a/accounting/data/fiscal')->assertForbidden();
        $this->actingAs($user)->get('/tenant-a/accounting/fiscal/10/billing-sheet')->assertForbidden();
        $this->actingAs($user)->postJson('/tenant-a/accounting/fiscal/10/prepare')->assertForbidden();
    }

    public function test_project_profile_overrides_tenant_profile_without_mutating_old_version(): void
    {
        $this->createFiscalProfile(['id' => 1, 'scope_key' => 'tenant', 'sales_project_id' => null, 'document_type' => 'nfe']);
        $this->createFiscalProfile(['id' => 2, 'scope_key' => 'project:10', 'sales_project_id' => 10, 'document_type' => 'nfse']);

        $resolved = app(FiscalProfileService::class)->resolve(1, 10);

        self::assertSame(2, $resolved->id);
        self::assertSame('nfse', $resolved->document_type->value);
        self::assertSame('nfe', DB::table('fiscal_profiles')->where('id', 1)->value('document_type'));
    }

    private function reconciliationScenario(): void
    {
        Schema::table('associate_receipts', function (Blueprint $table): void {
            $table->decimal('total_gross', 14, 4)->nullable();
        });
        DB::table('customer_billing_receipts')->where('id', 10)->update([
            'delivery_ids' => json_encode([301]),
            'fee_snapshot' => json_encode(['document_lines' => [[
                'project_id' => 10, 'product_id' => 1, 'product' => 'Banana', 'unit' => 'kg',
                'quantity' => '20', 'unit_price' => '10', 'distribution_ids' => [301],
                'raw_amount' => '200', 'document_gross' => '200', 'document_amount' => '180',
            ]]]),
        ]);
        DB::table('associate_receipts')->insert([
            'id' => 40, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1,
            'receipt_year' => 2026, 'receipt_number' => 6, 'receipt_label' => 'MEM-0006',
            'issued_at' => '2026-08-19', 'status' => 'pending_payment', 'total_gross' => 200,
            // Different member/customer fees are legitimate.
            'total_net' => 160, 'delivery_ids' => json_encode([301]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->where('id', 301)->update(['associate_receipt_id' => 40]);
    }

    public function test_dossier_reports_capture_alphabetical_members_group_products_and_keep_units_separate_without_writes(): void
    {
        $this->createReportFeeSchema();
        $this->reconciliationScenario();
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)->update(['tenant_name' => 'Zélia Souza']);
        $user = (array) DB::table('users')->where('id', 1)->first();
        DB::table('users')->insert(array_merge($user, ['id' => 9, 'email' => 'ana@example.test']));
        $membership = (array) DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)->first();
        unset($membership['id']);
        DB::table('tenant_user')->insert(array_merge($membership, ['user_id' => 9, 'tenant_name' => 'Ána Silva']));
        DB::table('associates')->insert(['id' => 2, 'tenant_id' => 1, 'user_id' => 9, 'nickname' => 'Ana']);
        DB::table('products')->insert(['id' => 2, 'tenant_id' => 1, 'name' => 'Milho Verde', 'unit' => 'un']);
        $row = (array) DB::table('production_deliveries')->where('id', 301)->first();
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 302, 'quantity' => '0.1', 'unit_price' => 10]));
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 303, 'quantity' => '0.2', 'unit_price' => 20]));
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 304, 'quantity' => 5, 'product_id' => 2, 'associate_id' => 2, 'associate_receipt_id' => null]));
        $before = DB::table('production_deliveries')->orderBy('id')->get()->toJson();
        $receiptBefore = DB::table('customer_billing_receipts')->where('id', 10)->first();
        $report = app(\App\Services\Accounting\BillingDossierReportService::class)->capture(CustomerBillingReceipt::findOrFail(10));
        self::assertSame(['Ána Silva', 'Zélia Souza'], array_column($report['members'], 'name'));
        self::assertCount(1, $report['members'][1]['products']);
        self::assertSame('20.30000000', $report['members'][1]['products'][0]['quantity']);
        self::assertSame('205.00000000', $report['members'][1]['products'][0]['gross']);
        $commercial = collect($report['commercial_products'])->where('name', 'Banana')->values();
        self::assertCount(2, $commercial);
        self::assertSame(['10.0000', '20.0000'], $commercial->pluck('unit_price')->sort()->values()->all());
        self::assertSame('20.30000000', collect($report['units'])->keyBy('unit')['kg']['quantity']);
        self::assertSame('5.00000000', collect($report['units'])->keyBy('unit')['un']['quantity']);
        self::assertSame($before, DB::table('production_deliveries')->orderBy('id')->get()->toJson());
        self::assertEquals($receiptBefore, DB::table('customer_billing_receipts')->where('id', 10)->first());
    }

    public function test_dossier_report_separates_clients_and_prices_without_duplicating_fixed_customer_fees(): void
    {
        $this->createReportFeeSchema();
        $this->reconciliationScenario();
        DB::table('customers')->insert(['id' => 3, 'tenant_id' => 1, 'organization_id' => 1, 'name' => 'Escola B']);
        $row = (array) DB::table('production_deliveries')->where('id', 301)->first();
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 302, 'customer_id' => 3, 'quantity' => 10]));
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 303, 'customer_id' => 3, 'quantity' => 2, 'unit_price' => 15]));
        DB::table('customer_project_fees')->insert([
            ['tenant_id' => 1, 'sales_project_id' => 10, 'name' => 'Frete', 'type' => 'fixed', 'nature' => 'discount', 'value' => 9, 'active' => true],
            ['tenant_id' => 1, 'sales_project_id' => 10, 'name' => 'Bônus', 'type' => 'percentage', 'nature' => 'accrual', 'value' => 10, 'active' => true],
        ]);
        $report = app(\App\Services\Accounting\BillingDossierReportService::class)->capture(CustomerBillingReceipt::findOrFail(10));
        self::assertSame(['Escola A', 'Escola B'], array_column($report['customers'], 'name'));
        self::assertCount(2, $report['customers'][1]['members'][0]['products']);
        self::assertSame('200.00', $report['customers'][0]['totals']['gross']);
        self::assertSame('6.00', $report['customers'][0]['totals']['discounts']);
        self::assertSame('130.00', $report['customers'][1]['totals']['gross']);
        self::assertSame('12.00', $report['customers'][1]['totals']['discounts']);
        self::assertSame('13.00', $report['customers'][1]['totals']['accruals']);
        self::assertSame('131.00', $report['customers'][1]['totals']['net']);
    }

    public function test_dossier_reports_distinguish_invoice_selection_from_recipient_project_period_and_ignore_other_contexts(): void
    {
        $this->createReportFeeSchema();
        $this->reconciliationScenario();
        $row = (array) DB::table('production_deliveries')->where('id', 301)->first();
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 302, 'quantity' => 5, 'billing_receipt_id' => null]));
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 303, 'tenant_id' => 2, 'quantity' => 999]));
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 304, 'delivery_date' => '2025-01-01', 'billing_receipt_id' => null]));
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 305, 'status' => 'pending', 'billing_receipt_id' => null]));
        DB::table('production_deliveries')->insert(array_merge($row, ['id' => 306, 'customer_id' => 2, 'billing_receipt_id' => null]));
        $service = app(\App\Services\Accounting\BillingDossierReportService::class);
        $billing = CustomerBillingReceipt::findOrFail(10);
        self::assertSame(1, $service->capture($billing)['distribution_count']);
        self::assertSame(2, $service->capture($billing, 'project_period')['distribution_count']);
        DB::table('customer_billing_receipts')->where('id', 10)->update(['status' => 'draft', 'delivery_ids' => json_encode([302])]);
        self::assertSame('5.00000000', $service->capture($billing->fresh())['units'][0]['quantity']);
    }

    public function test_dossier_document_register_includes_all_linked_files_and_correct_owners_without_creating_identities(): void
    {
        $this->createReportFeeSchema();
        $this->reconciliationScenario();
        DB::table('documents')->insert([
            'tenant_id' => 1, 'name' => 'Anexo do membro', 'documentable_type' => \App\Models\AssociateReceipt::class,
            'documentable_id' => 40, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('documents')->insert([
            'tenant_id' => 2, 'name' => 'SECRET', 'documentable_type' => CustomerBillingReceipt::class,
            'documentable_id' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $before = DB::table('financial_document_identities')->count();
        $report = app(\App\Services\Accounting\BillingDossierReportService::class)->capture(CustomerBillingReceipt::findOrFail(10));
        self::assertCount(3, $report['documents']);
        self::assertSame('MEM-0006', $report['documents'][1]['number']);
        self::assertSame('180.00', $report['documents'][0]['amount']);
        self::assertSame('160.00', $report['documents'][1]['amount']);
        self::assertNull($report['documents'][2]['amount']);
        $totals = collect($report['document_totals'])->keyBy('type');
        self::assertSame('180.00', $totals['Folha de faturamento']['amount']);
        self::assertSame('160.00', $totals['Comprovante do associado']['amount']);
        self::assertNull($totals['Anexo']['amount']);
        self::assertSame(1, $totals['Anexo']['missing']);
        self::assertSame($report['documents'][1]['owner'], $report['documents'][2]['owner']);
        self::assertStringNotContainsString('SECRET', json_encode($report));
        self::assertSame($before, DB::table('financial_document_identities')->count());
    }

    public function test_dossier_report_download_is_tenant_scoped_and_never_issues_or_persists_a_document(): void
    {
        $this->createReportFeeSchema();
        $this->reconciliationScenario();
        $before = DB::table('financial_document_identities')->count();
        $pdfService = Mockery::mock(\App\Services\TemplatedPdfService::class);
        foreach (\App\Services\Accounting\BillingDossierReportService::TYPES as $type => $definition) {
            $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdf->shouldReceive('output')->times(3)->andReturn('%PDF-report-'.$type, '%PDF-preview-1-'.$type, '%PDF-preview-2-'.$type);
            $pdfService->shouldReceive('generateSystemPdf')->times(3)->withArgs(fn ($view, $data, $options) =>
                $view === $definition['view'] && $data['report']['distribution_count'] === 1 && $options['report_context']['sales_project_id'] === 10)->andReturn($pdf);
        }
        $this->app->instance(\App\Services\TemplatedPdfService::class, $pdfService);
        $this->actingAs(User::findOrFail(1));
        foreach (array_keys(\App\Services\Accounting\BillingDossierReportService::TYPES) as $type) {
            $this->get('/tenant-a/accounting/processes/10/reports/'.$type.'?download=1')->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Pragma', 'no-cache')->assertContent('%PDF-report-'.$type);
            foreach ([1, 2] as $version) {
                $response = $this->get('/tenant-a/accounting/processes/10/reports/'.$type.'?download=0&_sgc_report='.$version)
                    ->assertOk()->assertContent('%PDF-preview-'.$version.'-'.$type)->assertHeader('Pragma', 'no-cache')
                    ->assertHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
                self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                self::assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
                self::assertStringContainsString('inline;', $response->headers->get('Content-Disposition'));
            }
        }
        $this->get('/tenant-a/accounting/processes/20/reports/members')->assertNotFound();
        $this->get('/tenant-a/accounting/processes/10/reports/members?project=20')->assertNotFound();
        self::assertSame($before, DB::table('financial_document_identities')->count());
    }

    public function test_dossier_reports_cannot_expand_a_receipt_only_grant_into_project_period_access(): void
    {
        $this->reconciliationScenario();
        Schema::create('accounting_access_scopes', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('user_id');
            $table->string('scope_type'); $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('scope_value')->nullable(); $table->boolean('active')->default(true);
        });
        DB::table('accounting_access_scopes')->insert(['tenant_id' => 1, 'user_id' => 1, 'scope_type' => 'customer_billing_receipt', 'scope_id' => 10]);
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)->update(['roles' => json_encode(['contador'])]);
        $user = User::findOrFail(1);
        $user->givePermissionTo('view_accounting_processes');
        $this->actingAs($user);
        $this->getJson('/tenant-a/accounting/processes/10/reports/members?scope=project_period')->assertForbidden();
        $this->getJson('/tenant-a/accounting/processes/20/reports/members?scope=billing')->assertNotFound();
        self::assertSame(10, app(\App\Services\Accounting\AccountingAccessService::class)->scopeBillings(CustomerBillingReceipt::query(), $user, 1)->first()?->id);
    }

    private function accountingDriveScenario(): void
    {
        $this->reconciliationScenario();
        config(['queue.default' => 'database']);
        \Illuminate\Support\Facades\Queue::fake();
        Schema::create('tenant_cloud_storage_connections', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('provider'); $table->string('status');
        });
        Schema::create('cloud_documents', function (Blueprint $table): void {
            $table->string('id')->primary(); $table->unsignedBigInteger('tenant_id');
            $table->string('provider'); $table->string('document_type'); $table->string('documentable_type');
            $table->string('remote_path')->nullable();
            $table->unsignedBigInteger('documentable_id'); $table->string('status');
            $table->json('metadata')->nullable(); $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable(); $table->timestamps();
        });
        DB::table('tenant_cloud_storage_connections')->insert(['tenant_id' => 1, 'provider' => 'google_drive', 'status' => 'active']);
        DB::table('cloud_documents')->insert([
            'id' => 'member-document', 'tenant_id' => 1, 'provider' => 'google_drive', 'document_type' => 'associate_receipt',
            'documentable_type' => (new \App\Models\AssociateReceipt)->getMorphClass(), 'documentable_id' => 40,
            'status' => 'synced', 'synced_at' => now(), 'metadata' => json_encode(['source_fingerprint' => 'same-version']),
        ]);
    }

    public function test_accounting_can_queue_pdf_refresh_without_changing_financial_data_and_observe_drive_status(): void
    {
        $this->accountingDriveScenario();
        $before = (array) DB::table('associate_receipts')->where('id', 40)->first();
        $this->actingAs(User::findOrFail(1))->postJson('/tenant-a/accounting/source-receipts/40/document/refresh')
            ->assertStatus(202)->assertSee('fila documents');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncAssociateReceiptToDrive::class, fn ($job) => $job->receiptId === 40);
        self::assertSame('pending', DB::table('cloud_documents')->where('id', 'member-document')->value('status'));
        self::assertSame($before, (array) DB::table('associate_receipts')->where('id', 40)->first());
        $this->getJson('/tenant-a/accounting/source-receipts/40/document/status')->assertOk()
            ->assertJsonPath('drive_status', 'pending')->assertJsonMissing(['source_fingerprint' => 'same-version']);
        $this->postJson('/tenant-a/accounting/processes/10/document/refresh')->assertStatus(202);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncCustomerBillingReceiptToDrive::class, fn ($job) => $job->receiptId === 10);
        $this->getJson('/tenant-a/accounting/processes/10/document/status')->assertOk()->assertJsonPath('drive_status', 'pending');
        app(\App\Services\Accounting\AccountingReceiptDocumentService::class)->recordFailure(CustomerBillingReceipt::findOrFail(10));
        $this->getJson('/tenant-a/accounting/processes/10/document/status')->assertOk()->assertJsonPath('drive_status', 'failed');
        $this->postJson('/tenant-a/accounting/processes/20/document/refresh')->assertNotFound();
        $this->getJson('/tenant-a/accounting/processes/20/document/status')->assertNotFound();
    }

    public function test_accounting_pdf_refresh_requires_review_permission_and_an_async_connected_drive(): void
    {
        $this->accountingDriveScenario();
        $user = User::findOrFail(1);
        $this->actingAs($user);
        DB::table('role_has_permissions')->whereIn('permission_id', DB::table('permissions')->where('name', 'review_accounting_processes')->pluck('id'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->postJson('/tenant-a/accounting/processes/10/document/refresh')->assertForbidden();
        $permission = \Spatie\Permission\Models\Permission::findOrCreate('review_accounting_processes', 'web');
        $user->givePermissionTo($permission);
        config(['queue.default' => 'sync']);
        $this->postJson('/tenant-a/accounting/processes/10/document/refresh')->assertUnprocessable();
        config(['queue.default' => 'database']);
        DB::table('tenant_cloud_storage_connections')->update(['status' => 'inactive']);
        $this->postJson('/tenant-a/accounting/processes/10/document/refresh')->assertUnprocessable();
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    public function test_accounting_never_refreshes_cancelled_or_obsolete_member_documents_and_restricts_download_scope(): void
    {
        $this->accountingDriveScenario();
        $this->actingAs(User::findOrFail(1));
        foreach (['cancelled', 'obsolete'] as $status) {
            DB::table('associate_receipts')->where('id', 40)->update(['status' => $status]);
            $this->postJson('/tenant-a/accounting/source-receipts/40/document/refresh')->assertUnprocessable();
            $this->getJson('/tenant-a/accounting/source-receipts/40/document?download=1')->assertStatus(409);
        }
        DB::table('associate_receipts')->where('id', 40)->update(['tenant_id' => 2, 'status' => 'paid']);
        $this->postJson('/tenant-a/accounting/source-receipts/40/document/refresh')->assertNotFound();
        $this->getJson('/tenant-a/accounting/source-receipts/40/document?download=1')->assertNotFound();
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    public function test_accounting_download_renders_a_fresh_member_pdf_instead_of_reading_the_drive_archive(): void
    {
        $this->accountingDriveScenario();
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('output')->once()->andReturn('%PDF-fresh-document');
        $archive = Mockery::mock(\App\Services\AssociateReceiptArchiveService::class);
        $archive->shouldReceive('generatePdf')->once()->withArgs(fn ($receipt) => $receipt->id === 40)->andReturn($pdf);
        $archive->shouldNotReceive('sync');
        $this->app->instance(\App\Services\AssociateReceiptArchiveService::class, $archive);
        $this->actingAs(User::findOrFail(1))->get('/tenant-a/accounting/source-receipts/40/document?download=1')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-SGC-Document-Origin', 'generated')
            ->assertHeader('Content-Disposition', 'attachment; filename="comprovante-MEM-0006.pdf"')
            ->assertContent('%PDF-fresh-document');
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    public function test_reconciliation_matches_quantities_and_gross_without_blocking_or_mutating_documents(): void
    {
        $this->reconciliationScenario();
        $before = (array) DB::table('customer_billing_receipts')->where('id', 10)->first();
        $response = $this->actingAs(User::findOrFail(1))->getJson('/tenant-a/accounting/data/processes/10');
        $response->assertOk()->assertJsonPath('reconciliation.advisory_only', true)
            ->assertJsonPath('reconciliation.warning_count', 0)
            ->assertJsonPath('reconciliation.summary.covered', 1)
            ->assertJsonPath('reconciliation.units.0.unit', 'kg')
            ->assertJsonPath('reconciliation.units.0.covered_quantity', '20.00000000')
            ->assertJsonPath('reconciliation.summary.all_members_net', '160.00000000');
        self::assertSame($before, (array) DB::table('customer_billing_receipts')->where('id', 10)->first());
        self::assertSame(0, (int) $response->json('process.integrity.critical_count'));
        $this->getJson('/tenant-a/accounting/data/processes/20')->assertNotFound();
    }

    public function test_reconciliation_reports_missing_rows_cancelled_documents_and_broken_origins(): void
    {
        $this->reconciliationScenario();
        DB::table('associate_receipts')->where('id', 40)->update(['status' => 'cancelled']);
        DB::table('customer_billing_receipts')->where('id', 10)->update(['delivery_ids' => json_encode([301, 9999])]);
        DB::table('production_deliveries')->where('id', 300)->update(['deleted_at' => now()]);
        $report = app(\App\Services\Accounting\BillingDocumentReconciliationService::class)->inspect(CustomerBillingReceipt::findOrFail(10));
        $codes = collect($report['alerts'])->pluck('code');
        foreach (['missing_distribution', 'cancelled_member_link', 'invalid_parent', 'billing_only_distributions'] as $code) {
            self::assertContains($code, $codes);
        }
        self::assertSame(0, $report['summary']['member_documents']);
        self::assertSame(2, $report['summary']['uncovered']);
        foreach ($report['alerts'] as $alert) self::assertNotEmpty($alert['solution']);
    }

    public function test_reconciliation_distinguishes_whole_member_document_from_the_current_billing_scope(): void
    {
        $this->reconciliationScenario();
        DB::table('customers')->insert(['id' => 3, 'tenant_id' => 1, 'name' => 'Outro cliente']);
        $billing = (array) DB::table('customer_billing_receipts')->where('id', 10)->first();
        DB::table('customer_billing_receipts')->insert(array_merge($billing, [
            'id' => 11, 'receipt_number' => 2, 'receipt_label' => 'FOLHA-MESMO-CLIENTE',
            'delivery_ids' => json_encode([302]),
        ]));
        DB::table('customer_billing_receipts')->insert(array_merge($billing, [
            'id' => 12, 'receipt_number' => 3, 'receipt_label' => 'FOLHA-OUTRO-CLIENTE',
            'customer_id' => 3, 'organization_id' => null, 'delivery_ids' => json_encode([303]),
        ]));
        $row = (array) DB::table('production_deliveries')->where('id', 301)->first();
        DB::table('production_deliveries')->insert(array_merge($row, [
            'id' => 302, 'quantity' => '5', 'gross_value' => '50', 'billing_receipt_id' => 11,
        ]));
        DB::table('production_deliveries')->insert(array_merge($row, [
            'id' => 303, 'quantity' => '5', 'gross_value' => '50', 'billing_receipt_id' => 12, 'customer_id' => 3,
        ]));
        DB::table('production_deliveries')->where('id', 300)->update(['quantity' => 30]);
        DB::table('associate_receipts')->where('id', 40)->update([
            'delivery_ids' => json_encode([301, 302, 303]), 'total_gross' => 300,
        ]);
        $report = app(\App\Services\Accounting\BillingDocumentReconciliationService::class)->inspect(CustomerBillingReceipt::findOrFail(10));
        self::assertSame(0, $report['warning_count']);
        self::assertSame('member_only_distributions', $report['alerts'][0]['code']);
        self::assertSame('info', $report['alerts'][0]['level']);
        self::assertSame('200.00000000', $report['summary']['covered_gross']);
        self::assertSame('300.00000000', $report['summary']['all_members_gross']);
        self::assertSame(2, $report['members'][0]['outside_billing']);
        self::assertStringContainsString('nenhuma correção é necessária', $report['alerts'][0]['solution']);
        $this->actingAs(User::findOrFail(1))->getJson('/tenant-a/accounting/data/processes/10')
            ->assertOk()->assertJsonPath('process.integrity.blocking_count', 0)
            ->assertJsonPath('reconciliation.warning_count', 0)
            ->assertJsonPath('reconciliation.info_count', 1);
    }

    public function test_reconciliation_finds_snapshot_only_documents_duplicates_and_stale_values(): void
    {
        $this->reconciliationScenario();
        $member = (array) DB::table('associate_receipts')->where('id', 40)->first();
        DB::table('associate_receipts')->insert(array_merge($member, [
            'id' => 41, 'receipt_label' => 'MEM-0007', 'receipt_number' => 7,
        ]));
        // No current link to either receipt: snapshot intersection must still find both.
        DB::table('production_deliveries')->where('id', 301)->update([
            'associate_receipt_id' => null, 'quantity' => 21,
        ]);
        $report = app(\App\Services\Accounting\BillingDocumentReconciliationService::class)->inspect(CustomerBillingReceipt::findOrFail(10));
        $codes = collect($report['alerts'])->pluck('code');
        foreach (['duplicate_member_distributions', 'member_links_changed', 'product_totals_changed', 'member_gross_changed', 'overallocated_parent', 'distribution_gross_mismatch'] as $code) {
            self::assertContains($code, $codes);
        }
        self::assertSame(2, $report['summary']['member_documents']);
        self::assertSame('21.00000000', $report['units'][0]['covered_quantity']);
        self::assertSame('42.00000000', $report['summary']['all_members_quantities'][0]['quantity']);
    }

    public function test_reconciliation_keeps_units_separate_and_includes_more_than_one_page(): void
    {
        $this->reconciliationScenario();
        DB::table('products')->insert(['id' => 2, 'tenant_id' => 1, 'name' => 'Ovos', 'unit' => 'un']);
        $row = (array) DB::table('production_deliveries')->where('id', 301)->first();
        $ids = [301];
        for ($index = 0; $index < 26; $index++) {
            $id = 400 + $index;
            $ids[] = $id;
            DB::table('production_deliveries')->insert(array_merge($row, [
                'id' => $id, 'product_id' => 2, 'parent_delivery_id' => 700,
                'quantity' => '0.1', 'gross_value' => 1,
            ]));
        }
        DB::table('production_deliveries')->insert(array_merge($row, [
            'id' => 700, 'product_id' => 2, 'parent_delivery_id' => null, 'quantity' => '2.6',
            'associate_receipt_id' => null, 'billing_receipt_id' => null,
        ]));
        DB::table('associate_receipts')->where('id', 40)->update(['delivery_ids' => json_encode($ids), 'total_gross' => 226]);
        DB::table('customer_billing_receipts')->where('id', 10)->update(['delivery_ids' => json_encode($ids), 'status' => 'draft']);
        $report = app(\App\Services\Accounting\BillingDocumentReconciliationService::class)->inspect(CustomerBillingReceipt::findOrFail(10));
        self::assertSame(27, $report['summary']['distributions']);
        $units = collect($report['units'])->keyBy('unit');
        self::assertSame('20.00000000', $units['kg']['current_quantity']);
        self::assertSame('2.60000000', $units['un']['covered_quantity']);
    }

    public function test_reconciliation_never_uses_other_tenant_snapshots_or_deleted_rows_as_valid_coverage(): void
    {
        $this->reconciliationScenario();
        $member = (array) DB::table('associate_receipts')->where('id', 40)->first();
        DB::table('associate_receipts')->where('id', 40)->delete();
        DB::table('associate_receipts')->insert(array_merge($member, [
            'id' => 41, 'tenant_id' => 2, 'receipt_label' => 'SECRET-MEMBER',
        ]));
        DB::table('production_deliveries')->where('id', 301)->update(['deleted_at' => now()]);
        $report = app(\App\Services\Accounting\BillingDocumentReconciliationService::class)->inspect(CustomerBillingReceipt::findOrFail(10));
        self::assertSame(0, $report['summary']['member_documents']);
        self::assertSame(1, $report['summary']['uncovered']);
        self::assertContains('deleted_distribution', collect($report['alerts'])->pluck('code'));
        self::assertStringNotContainsString('SECRET-MEMBER', json_encode($report));
    }

    public function test_reconciliation_missing_member_warning_does_not_change_workflow_or_blocking_counts(): void
    {
        $this->reconciliationScenario();
        $this->actingAs(User::findOrFail(1));
        $before = $this->getJson('/tenant-a/accounting/data/processes/10')->assertOk();
        DB::table('production_deliveries')->where('id', 301)->update(['associate_receipt_id' => null]);
        DB::table('associate_receipts')->where('id', 40)->delete();
        $after = $this->getJson('/tenant-a/accounting/data/processes/10')->assertOk()
            ->assertJsonPath('reconciliation.warning_count', 1)
            ->assertJsonPath('reconciliation.summary.uncovered', 1);
        self::assertSame($before->json('process.state'), $after->json('process.state'));
        self::assertSame($before->json('process.integrity.blocking_count'), $after->json('process.integrity.blocking_count'));
    }

    public function test_reconciliation_ignores_cancelled_history_without_a_current_link(): void
    {
        $this->reconciliationScenario();
        $member = (array) DB::table('associate_receipts')->where('id', 40)->first();
        DB::table('associate_receipts')->insert(array_merge($member, [
            'id' => 41, 'status' => 'cancelled', 'delivery_ids' => json_encode([301, 99999]),
        ]));
        $report = app(\App\Services\Accounting\BillingDocumentReconciliationService::class)->inspect(CustomerBillingReceipt::findOrFail(10));
        self::assertSame(0, $report['warning_count']);
        self::assertSame(1, $report['summary']['member_documents']);
        self::assertSame([], $report['alerts']);
    }

    private function createFiscalProfile(array $overrides = []): void
    {
        $attributes = array_merge([
            'id' => 1,
            'tenant_id' => 1,
            'sales_project_id' => null,
            'scope_key' => 'tenant',
            'version' => 1,
            'status' => 'active',
            'active_marker' => true,
            'document_type' => 'nfse',
            'amount_source' => 'authorized_final',
            'require_issuer_tax_id' => true,
            'require_issuer_address' => true,
            'require_recipient_tax_id' => true,
            'require_xml' => true,
            'require_pdf' => true,
            'standard_notes' => null,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
        $attributes['profile_hash'] = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));

        DB::table('fiscal_profiles')->insert($attributes);
    }

    private function sendRound(): BillingAuthorization
    {
        $this->actingAs(User::query()->findOrFail(1))->postJson('/tenant-a/accounting/data/processes/10/authorization/send', [
            'operation_key' => (string) Str::uuid(),
        ])->assertOk();

        return BillingAuthorization::withoutGlobalScopes()->latest('sequence')->firstOrFail();
    }

    private function authorizeRound(): BillingAuthorization
    {
        $round = $this->sendRound();
        $this->actingAs(User::query()->findOrFail(2))->post('/tenant-a/buyer/authorizations/'.$round->id.'/authorize')->assertRedirect();

        return $round->fresh();
    }

    private function createIdentitySchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->string('locale')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
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
            $table->string('tenant_name')->nullable();
            $table->string('tenant_password')->nullable();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
    }

    private function createAccountingSchema(): void
    {
        Schema::create('sales_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('receipt_numbering_scope')->nullable();
            $table->string('receipt_number_format')->nullable();
            $table->string('receipt_project_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('sales_project_organizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('organization_id');
            $table->timestamps();
        });
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('responsible_name')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('organization_authorized_emails', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id');
            $table->string('email');
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('cnpj')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('type')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_billing_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('receipt_number');
            $table->string('receipt_label')->nullable();
            $table->unsignedSmallInteger('tenant_receipt_year')->nullable();
            $table->unsignedInteger('tenant_receipt_number')->nullable();
            $table->unsignedSmallInteger('project_receipt_year')->nullable();
            $table->unsignedInteger('project_receipt_number')->nullable();
            $table->date('issued_at');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('status');
            $table->decimal('total_gross', 14, 4)->nullable();
            $table->decimal('total_fees', 14, 4)->nullable();
            $table->decimal('total_net', 14, 4)->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->json('fee_snapshot')->nullable();
            $table->json('delivery_ids')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->timestamps();
        });
        Schema::create('billing_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_billing_receipt_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedInteger('sequence');
            $table->string('status');
            $table->boolean('active_marker')->nullable()->default(true);
            $table->unsignedSmallInteger('snapshot_version')->default(1);
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->char('current_hash', 64)->nullable();
            $table->uuid('operation_key');
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->string('sent_by_name')->nullable();
            $table->timestamp('sent_at');
            $table->string('response_decision')->nullable();
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
            $table->unique(['tenant_id', 'operation_key']);
            $table->unique(['tenant_id', 'customer_billing_receipt_id', 'organization_id', 'sequence']);
            $table->unique(['tenant_id', 'customer_billing_receipt_id', 'organization_id', 'active_marker']);
        });
        Schema::create('fiscal_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id')->nullable();
            $table->string('scope_key');
            $table->unsignedInteger('version');
            $table->string('status');
            $table->boolean('active_marker')->nullable();
            $table->string('document_type')->nullable();
            $table->string('amount_source')->nullable();
            $table->boolean('require_issuer_tax_id')->default(true);
            $table->boolean('require_issuer_address')->default(true);
            $table->boolean('require_recipient_tax_id')->default(true);
            $table->boolean('require_xml')->default(true);
            $table->boolean('require_pdf')->default(true);
            $table->text('standard_notes')->nullable();
            $table->char('profile_hash', 64);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('associates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nickname')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('unit')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('production_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            $table->unsignedBigInteger('associate_receipt_id')->nullable();
            $table->unsignedBigInteger('billing_receipt_id')->nullable();
            $table->unsignedBigInteger('distribution_billing_id')->nullable();
            $table->unsignedBigInteger('project_payment_id')->nullable();
            $table->string('status');
            $table->string('billing_status')->default('unbilled');
            $table->boolean('paid')->default(false);
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('gross_value', 14, 4)->default(0);
            $table->decimal('admin_fee_amount', 14, 4)->default(0);
            $table->decimal('net_value', 14, 4)->default(0);
            $table->date('delivery_date');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('associate_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('receipt_number');
            $table->string('receipt_label')->nullable();
            $table->date('issued_at');
            $table->string('status');
            $table->decimal('total_net', 14, 4)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->json('delivery_ids')->nullable();
            $table->timestamps();
        });
        Schema::create('financial_document_identities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('public_id')->nullable();
            $table->string('reference_code')->nullable();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_receipt_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_billing_receipt_id');
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('document_number')->nullable();
            $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name')->nullable();
            $table->string('original_name')->nullable();
            $table->string('category')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->date('document_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('description');
            $table->string('event')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    private function seedAccountingScenario(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Conta global', 'email' => 'accountant@example.test', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Conta compradora', 'email' => 'buyer@example.test', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Outra conta', 'email' => 'other@example.test', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('tenants')->insert([
            ['id' => 1, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'active' => true, 'legal_name' => 'Cooperativa A', 'cnpj' => '12345678000100', 'address' => 'Rua A, 1', 'city' => 'Cidade A', 'state' => 'MG', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true, 'legal_name' => null, 'cnpj' => null, 'address' => null, 'city' => null, 'state' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $roleId = DB::table('roles')->insertGetId(['name' => 'tesoureiro', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['view_accounting_portal', 'view_accounting_processes', 'review_accounting_processes', 'request_accounting_corrections', 'send_accounting_authorizations', 'cancel_accounting_authorizations', 'view_accounting_fiscal_queue', 'prepare_accounting_fiscal', 'view_accounting_fiscal_settings', 'manage_accounting_fiscal_settings'] as $permission) {
            $permissionId = DB::table('permissions')->insertGetId(['name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        DB::table('tenant_user')->insert([
            'tenant_id' => 1, 'user_id' => 1, 'tenant_name' => 'Contadora do Tenant A',
            'roles' => json_encode(['tesoureiro']), 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales_projects')->insert([
            ['id' => 10, 'tenant_id' => 1, 'title' => 'Projeto A', 'code' => 'PA-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'tenant_id' => 2, 'title' => 'Projeto B', 'code' => 'PB-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('organizations')->insert([
            ['id' => 1, 'tenant_id' => 1, 'name' => 'Prefeitura A', 'cnpj' => '00987654000100', 'address' => 'Praça A, 1', 'city' => 'Cidade A', 'state' => 'MG', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'tenant_id' => 2, 'name' => 'Prefeitura B', 'cnpj' => null, 'address' => null, 'city' => null, 'state' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('sales_project_organizations')->insert([
            ['sales_project_id' => 10, 'organization_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['sales_project_id' => 20, 'organization_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('organization_authorized_emails')->insert([
            ['tenant_id' => 1, 'organization_id' => 1, 'email' => 'buyer@example.test', 'name' => 'Representante A', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => 2, 'organization_id' => 2, 'email' => 'other@example.test', 'name' => 'Representante B', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('customers')->insert([
            ['id' => 1, 'tenant_id' => 1, 'organization_id' => 1, 'name' => 'Escola A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'tenant_id' => 2, 'organization_id' => 2, 'name' => 'Escola B', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('customer_billing_receipts')->insert([
            ['id' => 10, 'tenant_id' => 1, 'sales_project_id' => 10, 'organization_id' => 1, 'receipt_year' => 2026, 'receipt_number' => 1, 'receipt_label' => 'COM-A-0001', 'issued_at' => '2026-08-20', 'status' => 'pending_payment', 'total_gross' => 200, 'total_fees' => 20, 'total_net' => 180, 'fee_snapshot' => json_encode(['fees' => [['id' => 1, 'name' => 'Taxa', 'type' => 'percentage', 'nature' => 'discount', 'rate' => '10']], 'total_fee' => '20']), 'amount_paid' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'tenant_id' => 2, 'sales_project_id' => 20, 'organization_id' => 2, 'receipt_year' => 2026, 'receipt_number' => 1, 'receipt_label' => 'COM-B-0001', 'issued_at' => '2026-08-20', 'status' => 'pending_payment', 'total_gross' => 900, 'total_fees' => 0, 'total_net' => 900, 'fee_snapshot' => null, 'amount_paid' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('associates')->insert(['id' => 1, 'tenant_id' => 1, 'user_id' => 1, 'nickname' => 'Produtora', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('products')->insert(['id' => 1, 'tenant_id' => 1, 'name' => 'Banana', 'unit' => 'kg', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('production_deliveries')->insert([
            ['id' => 300, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1, 'customer_id' => null, 'product_id' => 1, 'parent_delivery_id' => null, 'associate_receipt_id' => null, 'billing_receipt_id' => null, 'status' => 'approved', 'quantity' => 20, 'unit_price' => 0, 'gross_value' => 0, 'admin_fee_amount' => 0, 'net_value' => 0, 'delivery_date' => '2026-08-19', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 301, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 1, 'customer_id' => 1, 'product_id' => 1, 'parent_delivery_id' => 300, 'associate_receipt_id' => null, 'billing_receipt_id' => 10, 'status' => 'approved', 'quantity' => 20, 'unit_price' => 10, 'gross_value' => 200, 'admin_fee_amount' => 20, 'net_value' => 180, 'delivery_date' => '2026-08-19', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
