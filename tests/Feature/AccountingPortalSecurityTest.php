<?php

namespace Tests\Feature;

use App\Enums\BillingAuthorizationStatus;
use App\Models\BillingAuthorization;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\User;
use App\Services\Accounting\BillingAuthorizationNotificationService;
use App\Services\Accounting\BillingAuthorizationSnapshotService;
use App\Services\Accounting\BillingAuthorizationValidityService;
use App\Services\TenantNotificationDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountingPortalSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'activity_log', 'documents', 'customer_receipt_payments', 'associate_receipts',
            'billing_authorizations', 'organization_authorized_emails', 'sales_project_organizations',
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
            ->assertJsonPath('process.integrity.critical_count', 0);
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
            ->assertRedirect('/');
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
            $table->string('status');
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
            ['id' => 1, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $roleId = DB::table('roles')->insertGetId(['name' => 'tesoureiro', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['view_accounting_portal', 'view_accounting_processes', 'review_accounting_processes', 'request_accounting_corrections', 'send_accounting_authorizations', 'cancel_accounting_authorizations'] as $permission) {
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
            ['id' => 1, 'tenant_id' => 1, 'name' => 'Prefeitura A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'tenant_id' => 2, 'name' => 'Prefeitura B', 'created_at' => now(), 'updated_at' => now()],
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
