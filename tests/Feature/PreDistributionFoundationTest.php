<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\PreDistributionEvent;
use App\Models\PreDistributionItem;
use App\Models\PreDistributionSheet;
use App\Models\ProductionDelivery;
use App\Models\User;
use App\Observers\ProductionDeliveryObserver;
use App\Services\AssociateProjectLimitService;
use App\Services\NotificationService;
use App\Services\PreDistributions\PreDistributionQueryService;
use App\Services\PreDistributions\PreDistributionReconciliationService;
use App\Services\PreDistributions\PreDistributionReportService;
use App\Services\PreDistributions\PreDistributionSheetService;
use App\Services\PricingService;
use App\Services\ProjectFinancialCalculator;
use Filament\Panel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PreDistributionFoundationTest extends TestCase
{
    protected User $manager;

    protected User $operator;

    protected PreDistributionSheetService $service;

    protected function assertSafeDatabase(): void
    {
        self::assertSame('sqlite', DB::getDriverName());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSafeDatabase();
        Schema::disableForeignKeyConstraints();
        Schema::dropAllTables();
        Schema::enableForeignKeyConstraints();
        foreach (['users', 'tenants', 'sales_projects', 'customers', 'products', 'organizations', 'price_tables'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->engine = 'InnoDB';
                $table->id();
                if (! in_array($name, ['tenants', 'users'], true)) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                }
                $table->string('name')->nullable();
                if ($name !== 'sales_projects') {
                    $table->boolean('status')->default(true);
                }
                $table->timestamps();
                $table->softDeletes();
                if ($name === 'users') {
                    $table->string('email')->nullable();
                    $table->string('password')->nullable();
                }
                if ($name === 'tenants') {
                    $table->string('slug')->nullable();
                    $table->boolean('active')->default(true);
                }
                if ($name === 'sales_projects') {
                    $table->string('title');
                    $table->unsignedBigInteger('customer_id')->nullable();
                }
                if ($name === 'customers') {
                    $table->unsignedBigInteger('organization_id')->nullable();
                    $table->unsignedBigInteger('price_table_id')->nullable();
                    $table->string('trade_name')->nullable();
                }
                if ($name === 'products') {
                    $table->string('unit');
                }
                if ($name === 'organizations') {
                    $table->string('short_name')->nullable();
                }
                if ($name === 'price_tables') {
                    $table->boolean('active')->default(true);
                }
            });
        }
        Schema::table('sales_projects', fn (Blueprint $table) => $table->string('status')->default('active'));
        Schema::create('price_table_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('price_table_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('sale_price', 18, 4);
            $table->decimal('cost_price', 18, 4)->nullable();
            $table->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('status')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->json('roles');
            $table->string('tenant_name')->nullable();
            $table->string('tenant_password')->nullable();
            $table->timestamps();
        });
        foreach (['sales_project_customers' => 'customer_id', 'sales_project_organizations' => 'organization_id'] as $name => $fk) {
            Schema::create($name, function (Blueprint $table) use ($fk): void {
                $table->engine = 'InnoDB';
                $table->unsignedBigInteger('sales_project_id');
                $table->unsignedBigInteger($fk);
                $table->text('notes')->nullable();
                $table->boolean('enforce_request_limits')->default(false);
                $table->timestamps();
            });
        }
        foreach (['roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
            });
        }
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $name => $fk) {
            Schema::create($name, function (Blueprint $table) use ($fk): void {
                $table->engine = 'InnoDB';
                $table->unsignedBigInteger($fk);
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
            });
        }
        DB::table('tenants')->insert([['id' => 1, 'name' => 'A', 'slug' => 'tenant-a'], ['id' => 2, 'name' => 'B', 'slug' => 'tenant-b']]);
        DB::table('users')->insert([['id' => 1, 'name' => 'Gestor'], ['id' => 2, 'name' => 'Entregador'], ['id' => 3, 'name' => 'Outro']]);
        foreach ([1 => 'registrador_entregas', 2 => 'entregador', 3 => 'entregador'] as $id => $role) {
            DB::table('tenant_user')->insert(['tenant_id' => 1, 'user_id' => $id, 'roles' => json_encode([$role])]);
        }
        DB::table('sales_projects')->insert([['id' => 1, 'tenant_id' => 1, 'title' => 'Projeto A', 'customer_id' => 1], ['id' => 2, 'tenant_id' => 2, 'title' => 'Projeto B', 'customer_id' => 2]]);
        DB::table('customers')->insert([['id' => 1, 'tenant_id' => 1, 'name' => 'Escola A'], ['id' => 2, 'tenant_id' => 2, 'name' => 'Escola B']]);
        DB::table('products')->insert([['id' => 1, 'tenant_id' => 1, 'name' => 'Banana', 'unit' => 'kg'], ['id' => 2, 'tenant_id' => 2, 'name' => 'Banana', 'unit' => 'kg']]);
        (require base_path('database/migrations/2026_11_05_000001_create_pre_distribution_foundation.php'))->up();
        (require base_path('database/migrations/2026_11_05_000002_register_pre_distribution_permissions.php'))->up();
        (require base_path('database/migrations/2026_11_05_000003_create_pre_distribution_reconciliations.php'))->up();
        (require base_path('database/migrations/2026_11_05_000004_create_pre_distribution_reports.php'))->up();
        (require base_path('database/migrations/2026_11_05_000005_create_pre_distribution_security_events.php'))->up();
        (require base_path('database/migrations/2026_11_05_000006_add_reference_table_to_pre_distribution_items.php'))->up();
        config(['pre_distributions.enabled' => true]);
        session(['tenant_id' => 1]);
        $this->manager = User::findOrFail(1);
        $this->operator = User::findOrFail(2);
        $this->actingAs($this->manager);
        $this->service = app(PreDistributionSheetService::class);
    }

    private function payload(): array
    {
        return ['title' => 'Rota das escolas', 'operational_date' => '2026-11-05', 'assigned_to' => 2, 'project_ids' => [1],
            'items' => [['sales_project_id' => 1, 'customer_id' => 1, 'product_id' => 1, 'planned_quantity' => '100']]];
    }

    private function sheet(): array
    {
        return $this->service->create(1, $this->manager, (string) Str::uuid(), $this->payload());
    }

    public function test_new_delivery_requires_positive_reference_price(): void
    {
        $sheet = $this->sheet();
        $url = '/tenant-a/pre-distributions/'.$sheet['sheet_id'];
        $row = ['sales_project_id' => 1, 'customer_id' => 1, 'product_id' => 1, 'reported_quantity' => '4'];
        foreach ([['price_table_id' => null], ['reference_price' => '0'], []] as $pricing) {
            $this->putJson($url, ['version' => 1, 'operation_key' => (string) Str::uuid(), 'items' => [$row + $pricing]])
                ->assertUnprocessable()->assertJsonValidationErrors('reference_price');
        }
        self::assertSame(1, PreDistributionItem::count());
        $this->putJson($url, ['version' => 1, 'operation_key' => (string) Str::uuid(), 'items' => [array_replace($row, ['product_id' => 999, 'reference_price' => '2.50'])]])
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->putJson($url, ['version' => 1, 'operation_key' => (string) Str::uuid(), 'items' => [$row + ['reference_price' => '2.50']]])
            ->assertOk()->assertJsonPath('reference_totals.reported', '10.00000000');
    }

    public function test_reference_requires_the_customers_linked_table_and_preserves_the_saved_price(): void
    {
        foreach ([1 => 1, 2 => 1, 3 => 2] as $id => $tenant) {
            DB::table('price_tables')->insert(['id' => $id, 'tenant_id' => $tenant, 'name' => 'Tabela '.$id]);
            DB::table('price_table_items')->insert(['price_table_id' => $id, 'product_id' => 1, 'sale_price' => $id + 1.25]);
        }
        DB::table('customers')->where('id', 1)->update(['price_table_id' => 1]);
        $sheet = $this->sheet();
        $item = PreDistributionItem::firstOrFail();
        self::assertSame('2.2500', $item->reference_price);
        self::assertEquals(1, $item->reference_price_table_id);
        $url = '/tenant-a/pre-distributions/'.$sheet['sheet_id'];
        $row = ['id' => $item->id, 'reported_quantity' => '4', 'price_table_id' => 2];
        $this->putJson($url, ['version' => 1, 'operation_key' => (string) Str::uuid(), 'items' => [$row]])
            ->assertUnprocessable()->assertJsonValidationErrors('price_table_id');
        $quoteUrl = '/tenant-a/pre-distributions/reference-price?project_id=1&customer_id=1&product_id=1';
        $this->getJson($quoteUrl.'&price_table_id=2')->assertUnprocessable()->assertJsonValidationErrors('price_table_id');
        DB::table('customers')->where('id', 1)->update(['price_table_id' => 2]);
        $this->getJson($quoteUrl)->assertOk()->assertJsonPath('table_id', 2)->assertJsonPath('price', '3.2500');
        $this->putJson($url, ['version' => 1, 'operation_key' => (string) Str::uuid(), 'items' => [$row]])
            ->assertOk()->assertJsonPath('reference_totals.reported', '13.00000000');
        self::assertEquals(2, $item->fresh()->reference_price_table_id);
        DB::table('price_table_items')->where('price_table_id', 2)->update(['sale_price' => 99]);
        $this->putJson($url, ['version' => 2, 'operation_key' => (string) Str::uuid(), 'items' => [['id' => $item->id, 'reported_quantity' => '5']]])
            ->assertOk()->assertJsonPath('reference_totals.reported', '16.25000000');
        $row['price_table_id'] = 3;
        $this->putJson($url, ['version' => 3, 'operation_key' => (string) Str::uuid(), 'items' => [$row]])
            ->assertUnprocessable()->assertJsonValidationErrors('price_table_id');
    }

    public function test_entry_search_only_lists_products_priced_for_the_selected_customer(): void
    {
        DB::table('price_tables')->insert([
            ['id' => 1, 'tenant_id' => 1, 'name' => 'Tabela do cliente'],
            ['id' => 2, 'tenant_id' => 1, 'name' => 'Outra tabela'],
        ]);
        DB::table('customers')->where('id', 1)->update(['price_table_id' => 1]);
        DB::table('products')->insert([
            ['id' => 3, 'tenant_id' => 1, 'name' => 'Abacaxi', 'unit' => 'kg'],
            ['id' => 4, 'tenant_id' => 1, 'name' => 'Alface', 'unit' => 'kg'],
        ]);
        DB::table('price_table_items')->insert([
            ['price_table_id' => 1, 'product_id' => 1, 'sale_price' => 5],
            ['price_table_id' => 1, 'product_id' => 3, 'sale_price' => 0],
            ['price_table_id' => 2, 'product_id' => 4, 'sale_price' => 8],
        ]);
        $url = '/tenant-a/pre-distributions/catalog?project=1&products_only=1&customer_id=1';
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', 1);
        $this->getJson($url.'&q=Alface')->assertOk()->assertJsonCount(0, 'products');
        $this->getJson($url.'&unpriced_only=1')->assertOk()->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.id', 3)->assertJsonPath('products.1.id', 4);
        DB::table('price_tables')->where('id', 1)->update(['active' => false]);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'products');
    }

    public function test_project_catalog_identifies_organizations_and_rejects_a_mismatched_recipient(): void
    {
        DB::table('organizations')->insert(['id' => 1, 'tenant_id' => 1, 'name' => 'Rede escolar']);
        DB::table('customers')->where('id', 1)->update(['organization_id' => 1]);
        $this->getJson('/tenant-a/pre-distributions/catalog?project=1')->assertOk()
            ->assertJsonPath('organizations.0.name', 'Rede escolar')->assertJsonPath('customers.0.organization_id', 1);

        $payload = $this->payload();
        $payload['items'][0]['organization_id'] = 2;
        $this->postJson('/tenant-a/pre-distributions', $payload + ['operation_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        self::assertSame(0, PreDistributionSheet::count());

        $sheet = $this->sheet();
        $this->getJson('/tenant-a/pre-distributions?q='.$sheet['code'])->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/tenant-a/pre-distributions/'.$sheet['sheet_id'].'?q=Banana')->assertOk()->assertJsonPath('items.total', 1);
        $this->getJson('/tenant-a/pre-distributions/'.$sheet['sheet_id'].'?q=Inexistente')->assertOk()->assertJsonPath('items.total', 0);
    }

    public function test_plan_can_exist_without_any_official_delivery_or_financial_table_and_retries_are_idempotent(): void
    {
        $key = (string) Str::uuid();
        $payload = $this->payload();
        $first = $this->service->create(1, $this->manager, $key, $payload);
        self::assertEquals($first, $this->service->create(1, $this->manager, $key, $payload));
        self::assertSame(1, PreDistributionSheet::count());
        self::assertSame(1, PreDistributionItem::count());
        self::assertSame(1, PreDistributionEvent::count());
        self::assertNull(PreDistributionItem::first()->reported_quantity);
        foreach (['production_deliveries', 'associate_ledgers', 'revenues', 'stock_movements', 'customer_billing_receipts'] as $table) {
            self::assertFalse(Schema::hasTable($table));
        }
    }

    public function test_operator_records_zero_as_confirmed_and_can_complete_without_creating_official_records(): void
    {
        $sheet = $this->sheet();
        $id = PreDistributionItem::first()->id;
        $this->actingAs($this->operator);
        $data = ['version' => 1, 'items' => [['id' => $id, 'reported_quantity' => '0', 'notes' => 'Não entregue']]];
        $key = (string) Str::uuid();
        $saved = $this->service->save(1, $this->operator, $sheet['sheet_id'], $key, $data);
        self::assertEquals($saved, $this->service->save(1, $this->operator, $sheet['sheet_id'], $key, $data));
        self::assertSame('0.0000', PreDistributionItem::first()->reported_quantity);
        $result = $this->service->transition(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 2, 'action' => 'complete']);
        self::assertSame('completed', $result['status']);
        self::assertSame(3, $result['version']);
        self::assertSame(2, PreDistributionSheet::first()->completed_by);
        self::assertSame(3, PreDistributionEvent::count());
    }

    public function test_pending_quantity_cannot_be_completed_and_failed_operation_is_rolled_back(): void
    {
        $sheet = $this->sheet();
        try {
            $this->service->transition(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1, 'action' => 'complete']);
            self::fail('Deveria bloquear pendência.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('items', $e->errors());
        }
        self::assertSame('draft', PreDistributionSheet::first()->status);
        self::assertSame(1, DB::table('pre_distribution_operations')->count());
    }

    public function test_stale_version_is_rejected_without_overwriting_quantities(): void
    {
        $sheet = $this->sheet();
        $id = PreDistributionItem::first()->id;
        $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1, 'items' => [['id' => $id, 'reported_quantity' => '30']]]);
        try {
            $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1, 'items' => [['id' => $id, 'reported_quantity' => '50']]]);
            self::fail('Deveria rejeitar versão.');
        } catch (HttpException $e) {
            self::assertSame(409, $e->getStatusCode());
        }
        self::assertSame('30.0000', PreDistributionItem::first()->reported_quantity);
    }

    public function test_operator_cannot_create_sheets_change_price_or_use_others_records(): void
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::first();
        foreach (['create', 'price', 'other'] as $action) {
            try {
                if ($action === 'create') {
                    $this->service->create(1, $this->operator, (string) Str::uuid(), $this->payload());
                } elseif ($action === 'price') {
                    $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1, 'items' => [['id' => $item->id, 'reference_price' => '500']]]);
                } else {
                    $this->service->save(1, User::findOrFail(3), $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1, 'items' => [['id' => $item->id, 'reported_quantity' => '500']]]);
                }
                self::fail('Deveria rejeitar '.$action);
            } catch (ModelNotFoundException $e) {
                self::assertSame('other', $action);
            } catch (HttpException $e) {
                self::assertSame(403, $e->getStatusCode());
            }
        }
        self::assertNull($item->fresh()->reference_price);
        self::assertSame(1, DB::table('pre_distribution_operations')->count());
        self::assertFalse($this->operator->can('create_production::delivery'));
    }

    public function test_cross_tenant_references_and_negative_or_overprecision_quantities_are_rejected_atomically(): void
    {
        foreach (['product', 'customer', 'project', 'negative', 'precision'] as $case) {
            $data = $this->payload();
            if ($case === 'product') {
                $data['items'][0]['product_id'] = 2;
            }
            if ($case === 'customer') {
                $data['items'][0]['customer_id'] = 2;
            }
            if ($case === 'project') {
                $data['project_ids'] = [2];
            }
            if ($case === 'negative') {
                $data['items'][0]['reported_quantity'] = '-1';
            }
            if ($case === 'precision') {
                $data['items'][0]['reported_quantity'] = '1.12345';
            }
            try {
                $this->service->create(1, $this->manager, (string) Str::uuid(), $data);
                self::fail('Deveria rejeitar '.$case);
            } catch (ValidationException $e) {
                self::assertNotEmpty($e->errors());
            }
            self::assertSame(0, PreDistributionSheet::count());
            self::assertSame(0, PreDistributionEvent::count());
        }
    }

    public function test_idempotency_key_cannot_be_reused_with_different_payload(): void
    {
        $key = (string) Str::uuid();
        $data = $this->payload();
        $this->service->create(1, $this->manager, $key, $data);
        $data['title'] = 'Outra rota';
        $this->expectException(HttpException::class);
        $this->service->create(1, $this->manager, $key, $data);
    }

    public function test_completed_sheet_requires_authorized_reasoned_reopening_and_preserves_previous_versions(): void
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::first();
        $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1, 'items' => [['id' => $item->id, 'reported_quantity' => '50']]]);
        $this->service->transition(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 2, 'action' => 'complete']);
        try {
            $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 3, 'items' => [['id' => $item->id, 'reported_quantity' => '80']]]);
            self::fail('Deveria bloquear finalizada.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('status', $e->errors());
        }
        $this->service->transition(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 3, 'action' => 'request_correction', 'reason' => 'Quantidade incorreta']);
        $opened = $this->service->transition(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 4, 'action' => 'reopen', 'reason' => 'Correção autorizada']);
        self::assertSame('draft', $opened['status']);
        self::assertSame(5, $opened['version']);
        $event = PreDistributionEvent::where('event', 'reopen')->first();
        self::assertSame('completed', $event->before['sheet']['status']);
        self::assertSame('draft', $event->after['sheet']['status']);
        self::assertSame('50.0000', $event->before['items'][0]['reported_quantity']);
    }

    public function test_missing_context_and_disabled_feature_fail_closed(): void
    {
        session()->forget('tenant_id');
        try {
            $this->sheet();
            self::fail('Sem tenant deveria bloquear.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
        session(['tenant_id' => 1]);
        config(['pre_distributions.enabled' => false]);
        $this->expectException(HttpException::class);
        $this->sheet();
    }

    public function test_rollback_refuses_to_destroy_audit_history(): void
    {
        $this->sheet();
        $this->expectException(\RuntimeException::class);
        (require base_path('database/migrations/2026_11_05_000001_create_pre_distribution_foundation.php'))->down();
    }

    public function test_consolidation_preserves_origins_and_warns_about_possible_duplicates_without_creating_official_rows(): void
    {
        $first = $this->payload();
        $first['items'][0]['reported_quantity'] = '40';
        $first['items'][0]['reference_price'] = '2.50';
        $second = $this->payload();
        $second['assigned_to'] = 3;
        $second['items'][0]['reported_quantity'] = '20';
        $second['items'][0]['reference_price'] = '2.50';
        $a = $this->service->create(1, $this->manager, (string) Str::uuid(), $first);
        $b = $this->service->create(1, $this->manager, (string) Str::uuid(), $second);
        $before = PreDistributionItem::orderBy('id')->get()->toJson();
        $queries = app(PreDistributionQueryService::class);
        $groups = $queries->consolidate(1, $this->manager, [$a['sheet_id'], $b['sheet_id']]);
        self::assertCount(1, $groups);
        self::assertEquals(60, $groups->items()[0]->reported_quantity);
        self::assertTrue($groups->items()[0]->possible_duplicate);
        $origins = $queries->origins(1, $this->manager, [$a['sheet_id'], $b['sheet_id']]);
        self::assertCount(2, $origins);
        self::assertSame($before, PreDistributionItem::orderBy('id')->get()->toJson());
    }

    public function test_cancelled_rows_are_preserved_in_history_but_not_counted_in_consolidation(): void
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::first();
        $this->service->cancelItem(1, $this->manager, $sheet['sheet_id'], $item->id, (string) Str::uuid(), ['version' => 1, 'reason' => 'Produto não seguirá nesta rota']);
        self::assertNotNull($item->fresh()->cancelled_at);
        $query = app(PreDistributionQueryService::class);
        self::assertCount(0, $query->consolidate(1, $this->manager, [$sheet['sheet_id']]));
        self::assertSame(2, PreDistributionEvent::count());
        self::assertSame(1, PreDistributionItem::count());
    }

    public function test_http_api_does_not_expose_reference_prices_or_other_operators_history(): void
    {
        $data = $this->payload();
        $data['items'][0]['reference_price'] = '5.1234';
        $sheet = $this->service->create(1, $this->manager, (string) Str::uuid(), $data);
        $this->actingAs($this->operator)->getJson('/tenant-a/pre-distributions/'.$sheet['sheet_id'])
            ->assertOk()->assertJsonMissingPath('items.data.0.reference_price')->assertJsonPath('items.data.0.reported_quantity', null)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/tenant-a/pre-distributions/'.$sheet['sheet_id'].'/history')->assertOk()->assertJsonMissingPath('data.0.after');
        $this->actingAs(User::findOrFail(3))->getJson('/tenant-a/pre-distributions/'.$sheet['sheet_id'])->assertNotFound();
        $this->actingAs($this->operator)->postJson('/tenant-a/pre-distributions', $data + ['operation_key' => (string) Str::uuid()])->assertForbidden();
        $this->postJson('/tenant-a/delivery/deliveries/999/distribute', ['distributions' => []])->assertForbidden();
    }

    public function test_duplicate_copies_only_planning_and_audits_its_source(): void
    {
        $source = $this->sheet();
        $item = PreDistributionItem::first();
        $this->service->save(1, $this->manager, $source['sheet_id'], (string) Str::uuid(), ['version' => 1, 'items' => [['id' => $item->id, 'reported_quantity' => '45', 'notes' => 'Já entregue']]]);
        $key = (string) Str::uuid();
        $data = ['version' => 2, 'title' => 'Próxima rota', 'assigned_to' => 3, 'operational_date' => '2026-11-06'];
        $copy = $this->service->duplicate(1, $this->manager, $source['sheet_id'], $key, $data);
        self::assertEquals($copy, $this->service->duplicate(1, $this->manager, $source['sheet_id'], $key, $data));
        $copied = PreDistributionItem::where('sheet_id', $copy['sheet_id'])->firstOrFail();
        self::assertNull($copied->reported_quantity);
        self::assertNull($copied->notes);
        self::assertNull($copied->delivered_at);
        self::assertSame('100.0000', $copied->planned_quantity);
        self::assertSame('duplicate', $copied->origin);
        self::assertSame(3, PreDistributionSheet::findOrFail($copy['sheet_id'])->assigned_to);
        self::assertSame('45.0000', $item->fresh()->reported_quantity);
    }

    protected function officialSchema(): void
    {
        Schema::create('production_deliveries', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            foreach (['sales_project_id', 'associate_id', 'customer_id', 'product_id'] as $field) {
                $table->unsignedBigInteger($field)->nullable();
            }
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4)->default(5);
            $table->string('status')->default('approved');
            $table->unsignedBigInteger('project_demand_id')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('quality_grade')->nullable();
            foreach (['cost_price_used', 'admin_fee_percentage', 'admin_fee_amount', 'net_value'] as $field) {
                $table->decimal($field, 18, 4)->nullable();
            }
            foreach (['price_table_id', 'received_by', 'approved_by'] as $field) {
                $table->unsignedBigInteger($field)->nullable();
            }
            $table->string('price_source')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('paid')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('production_deliveries')->insert(['id' => 100, 'tenant_id' => 1, 'sales_project_id' => 1, 'associate_id' => 7, 'product_id' => 1, 'quantity' => 500]);
        Schema::create('associates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('associates')->insert(['id' => 7, 'tenant_id' => 1, 'user_id' => 2]);
    }

    protected function completedSheet(): array
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::firstOrFail();
        $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1, 'items' => [['id' => $item->id, 'reported_quantity' => '100']]]);

        return $this->service->transition(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 2, 'action' => 'complete']);
    }

    protected function official(int $id, string $quantity = '40'): void
    {
        DB::table('production_deliveries')->insert(['id' => $id, 'tenant_id' => 1, 'parent_delivery_id' => 100, 'sales_project_id' => 1, 'associate_id' => 7, 'customer_id' => 1, 'product_id' => 1, 'quantity' => $quantity]);
    }

    public function test_partial_reconciliation_has_explicit_balances_and_cannot_reuse_quantity(): void
    {
        $this->officialSchema();
        $sheet = $this->completedSheet();
        $item = PreDistributionItem::firstOrFail();
        $this->official(101);
        $this->official(102, '60');
        $service = app(PreDistributionReconciliationService::class);
        $rows = [['pre_distribution_item_id' => $item->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '40']];
        $key = (string) Str::uuid();
        $first = $service->linkExisting(1, $this->manager, $key, ['rows' => $rows]);
        self::assertEquals($first, $service->linkExisting(1, $this->manager, $key, ['rows' => $rows]));
        $progress = $service->progress(1, $item->id);
        self::assertSame('partial', $progress['state']);
        self::assertSame('60.0000', $progress['remaining']);
        $rows[0]['pre_distribution_version'] = 4;
        $rows[0]['distribution_id'] = 102;
        $rows[0]['quantity'] = '60';
        $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => $rows]);
        self::assertSame('reconciled', $service->progress(1, $item->id)['state']);
        self::assertSame('0.0000', $service->progress(1, $item->id)['remaining']);
        self::assertSame(2, DB::table('pre_distribution_links')->count());
        self::assertSame(3, DB::table('production_deliveries')->count());
        $rows[0]['pre_distribution_version'] = 5;
        $rows[0]['quantity'] = '1';
        try {
            $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => $rows]);
            self::fail('Saldo esgotado deve impedir reutilização.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('saldo preliminar', $e->getMessage());
        }
        self::assertSame(2, DB::table('pre_distribution_links')->count());
        self::assertSame(5, PreDistributionSheet::first()->version);
    }

    public function test_reversal_requires_official_correction_and_keeps_history_and_price_warning(): void
    {
        $this->officialSchema();
        $sheet = $this->completedSheet();
        $this->official(101);
        $item = PreDistributionItem::firstOrFail();
        DB::table('pre_distribution_items')->where('id', $item->id)->update(['reference_price' => 9]);
        $service = app(PreDistributionReconciliationService::class);
        $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => [['pre_distribution_item_id' => $item->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '40']]]);
        $progress = $service->progress(1, $item->id);
        self::assertSame('price_difference', $progress['warnings'][0]['code']);
        self::assertSame('40.0000', $progress['reconciled']);
        $id = $progress['links'][0]->id;
        try {
            $service->reverse(1, $this->manager, $id, (string) Str::uuid(), ['version' => 4, 'reason' => 'Corrigir origem']);
            self::fail('Uma distribuição ativa não pode liberar novamente a quantidade.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('fluxo oficial', $e->getMessage());
        }
        DB::table('production_deliveries')->where('id', 101)->update(['deleted_at' => now()]);
        $progress = $service->progress(1, $item->id);
        self::assertSame('divergent', $progress['state']);
        self::assertSame('60.0000', $progress['remaining']);
        $key = (string) Str::uuid();
        $data = ['version' => 4, 'reason' => 'Distribuição removida pelo fluxo oficial'];
        $result = $service->reverse(1, $this->manager, $id, $key, $data);
        self::assertEquals($result, $service->reverse(1, $this->manager, $id, $key, $data));
        self::assertSame('100.0000', $service->progress(1, $item->id)['remaining']);
        self::assertNotNull(DB::table('pre_distribution_links')->find($id)->reversed_at);
        self::assertSame('reconciliation_reversed', PreDistributionEvent::latest('id')->first()->event);
    }

    public function test_reconciliation_failure_rolls_back_the_entire_batch_and_operator_cannot_link(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->official(101);
        $this->official(102, '60');
        DB::table('production_deliveries')->where('id', 102)->update(['customer_id' => 2]);
        $item = PreDistributionItem::firstOrFail();
        $service = app(PreDistributionReconciliationService::class);
        $rows = array_map(fn ($id) => ['pre_distribution_item_id' => $item->id, 'pre_distribution_version' => 3, 'distribution_id' => $id, 'quantity' => '40'], [101, 102]);
        $before = DB::table('pre_distribution_operations')->count();
        try {
            $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => $rows]);
            self::fail('Cliente inválido deveria abortar todas as linhas.');
        } catch (ValidationException $e) {
            self::assertNotEmpty($e->errors());
        }
        self::assertSame(0, DB::table('pre_distribution_links')->count());
        self::assertSame($before, DB::table('pre_distribution_operations')->count());
        self::assertSame(3, PreDistributionSheet::first()->version);
        $this->actingAs($this->operator)->getJson('/tenant-a/pre-distributions/snippets?reception=100')->assertForbidden();
        $this->postJson('/tenant-a/pre-distributions/links', ['rows' => $rows, 'operation_key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_linked_items_cannot_be_rewritten_or_cancelled_after_reopening(): void
    {
        $this->officialSchema();
        $sheet = $this->completedSheet();
        $this->official(101);
        $item = PreDistributionItem::firstOrFail();
        app(PreDistributionReconciliationService::class)->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => [['pre_distribution_item_id' => $item->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '40']]]);
        $this->service->transition(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 4, 'action' => 'reopen', 'reason' => 'Corrigir observações']);
        foreach ([['reported_quantity' => '20'], ['customer_id' => 2], ['product_id' => 2], ['reported_quantity' => '']] as $change) {
            try {
                $this->service->save(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 5, 'items' => [['id' => $item->id] + $change]]);
                self::fail('Conciliação não pode ser invalidada silenciosamente.');
            } catch (ValidationException $e) {
                self::assertStringContainsString('conciliação', $e->getMessage());
            }
        }
        $this->service->save(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 5, 'items' => [['id' => $item->id, 'reported_quantity' => '100', 'notes' => 'Observação retificada']]]);
        self::assertSame('Observação retificada', $item->fresh()->notes);
        try {
            $this->service->transition(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 6, 'action' => 'cancel', 'reason' => 'Cancelar toda a rota']);
            self::fail('Planilha conciliada não pode ser cancelada.');
        } catch (ValidationException $e) {
            self::assertNotEmpty($e->errors());
        }
    }

    public function test_partial_batch_failure_rolls_back_previous_line_changes_and_audit(): void
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::first();
        try {
            $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1,
                'items' => [['id' => $item->id, 'reported_quantity' => '40'], ['id' => $item->id + 999, 'reported_quantity' => '60']]]);
            self::fail('A segunda linha inválida deveria abortar tudo.');
        } catch (ModelNotFoundException $e) {
            self::assertNotEmpty($e->getMessage());
        }
        self::assertNull($item->fresh()->reported_quantity);
        self::assertSame(1, PreDistributionEvent::count());
        self::assertSame(1, PreDistributionSheet::first()->version);
    }

    public function test_preliminary_pdf_is_immutable_and_reissues_preserve_the_previous_revision(): void
    {
        Storage::fake('local');
        DB::table('customers')->where('id', 1)->update(['trade_name' => 'Escola do Centro']);
        $sheet = $this->sheet();
        $item = PreDistributionItem::firstOrFail();
        $this->service->save(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1,
            'items' => [['id' => $item->id, 'reported_quantity' => '45.1234', 'reference_price' => '5']]]);
        $reports = app(PreDistributionReportService::class);
        $key = (string) Str::uuid();
        $data = ['sheet_ids' => [$sheet['sheet_id']], 'include_prices' => true];
        $first = $reports->create(1, $this->manager, $key, $data);
        self::assertEquals($first, $reports->create(1, $this->manager, $key, $data));
        $oldBytes = $reports->bytes(1, $this->manager, $first['report_id'])['bytes'];
        self::assertStringStartsWith('%PDF-', $oldBytes);
        $snapshot = json_decode(DB::table('pre_distribution_reports')->find($first['report_id'])->snapshot, true);
        self::assertSame('Escola do Centro', $snapshot['groups'][0]['customer']);
        self::assertSame('45.1234', $snapshot['groups'][0]['quantity']);
        self::assertSame('5.0000', $snapshot['groups'][0]['reference_price']);
        $this->service->save(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 2, 'items' => [['id' => $item->id, 'reported_quantity' => '60']]]);
        self::assertSame($oldBytes, $reports->bytes(1, $this->manager, $first['report_id'])['bytes']);
        $second = $reports->create(1, $this->manager, (string) Str::uuid(), $data + ['previous_report_id' => $first['report_id']]);
        self::assertSame(2, $second['revision']);
        self::assertSame($first['code'], $second['code']);
        self::assertNotSame($oldBytes, $reports->bytes(1, $this->manager, $second['report_id'])['bytes']);
        self::assertSame(2, DB::table('pre_distribution_reports')->count());
        $this->actingAs($this->operator)->get('/tenant-a/pre-distributions/reports/'.$first['report_id'].'/pdf')->assertForbidden();
        $this->actingAs($this->manager)->get('/tenant-a/pre-distributions/reports/'.$first['report_id'].'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        foreach (['associate_ledgers', 'revenues', 'stock_movements', 'customer_billing_receipts'] as $table) {
            self::assertFalse(Schema::hasTable($table));
        }
        if (getenv('PRE_DISTRIBUTIONS_PDF_PREVIEW') === '1') {
            File::ensureDirectoryExists(base_path('tmp/pdfs'));
            file_put_contents(base_path('tmp/pdfs/pre-distributions-preview.pdf'), $oldBytes);
        }
    }

    public function test_assigned_operator_can_print_the_saved_sheet_and_other_operators_cannot(): void
    {
        DB::table('organizations')->insert(['id' => 1, 'tenant_id' => 1, 'name' => 'Rede escolar', 'short_name' => 'Rede Municipal']);
        DB::table('customers')->where('id', 1)->update(['organization_id' => 1]);
        DB::table('customers')->insert(['id' => 3, 'tenant_id' => 1, 'name' => 'Creche Municipal', 'trade_name' => 'Creche Central', 'organization_id' => 1]);
        DB::table('sales_project_customers')->insert(['sales_project_id' => 1, 'customer_id' => 3]);
        $sheet = $this->sheet();
        $this->service->save(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), [
            'version' => 1,
            'items' => [
                ['id' => PreDistributionItem::firstOrFail()->id, 'reported_quantity' => '12.3456', 'reference_price' => '5.23'],
                ['sales_project_id' => 1, 'customer_id' => 3, 'product_id' => 1, 'reported_quantity' => '7.6544', 'reference_price' => '5.23'],
            ],
        ]);
        $url = '/tenant-a/pre-distributions/'.$sheet['sheet_id'].'/pdf';
        $this->actingAs(User::findOrFail(3))->get($url)->assertNotFound();
        $response = $this->actingAs($this->operator)->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename='.$sheet['code'].'.pdf')
            ->assertHeader('Cache-Control', 'no-store, private');
        self::assertStringStartsWith('%PDF-', $response->getContent());
        self::assertSame(0, DB::table('pre_distribution_reports')->count());
        $managerPdf = $this->actingAs($this->manager)->get($url)->assertOk();
        $tableUrl = '/tenant-a/pre-distributions/'.$sheet['sheet_id'].'/table';
        $this->getJson($tableUrl)->assertOk()->assertJsonPath('show_prices', true)
            ->assertJsonPath('groups.0.tables.0.customers.0.name', 'Creche Central')
            ->assertJsonPath('groups.0.tables.0.rows.0.by_customer.3', '7.6544')
            ->assertJsonPath('groups.0.tables.0.rows.0.quantity', '20.0000')
            ->assertJsonPath('groups.0.tables.0.total', '104.60');
        $this->actingAs($this->operator)->getJson($tableUrl)->assertOk()->assertJsonPath('show_prices', false)
            ->assertJsonPath('groups.0.tables.0.rows.0.price', null);
        $this->actingAs(User::findOrFail(3))->getJson($tableUrl)->assertNotFound();

        if (getenv('PRE_DISTRIBUTIONS_PDF_PREVIEW') === '1') {
            File::ensureDirectoryExists(base_path('tmp/pdfs'));
            file_put_contents(base_path('tmp/pdfs/pre-distribution-sheet-print.pdf'), $managerPdf->getContent());
        }

        $reports = app(PreDistributionReportService::class);
        $savedSheet = PreDistributionSheet::withoutGlobalScopes()->findOrFail($sheet['sheet_id']);
        $tenant = \App\Models\Tenant::findOrFail(1);
        session(['tenant_id' => $tenant->id]);
        $render = fn (bool $showPrices) => view('pdf.pre-distribution-sheet', [
            'tenant' => $tenant, 'sheet' => $savedSheet, 'showPrices' => $showPrices,
            'groups' => $reports->sheetGroups($tenant, $savedSheet, $showPrices),
        ])->render();
        $document = $render(true);
        self::assertStringNotContainsString('U.C.', $document);
        self::assertStringContainsString('20,0000 kg', $document);
        self::assertStringContainsString('Creche Central', $document);
        self::assertStringContainsString('Rede Municipal', $document);
        self::assertStringNotContainsString('Creche Municipal', $document);
        self::assertStringContainsString('Vlr. Unit. Ref.', $document);
        self::assertStringContainsString('Total R$', $document);

        $operatorDocument = $render(false);
        self::assertStringContainsString('Qtd. Total', $operatorDocument);
        self::assertStringNotContainsString('Vlr. Unit. Ref.', $operatorDocument);
        self::assertStringNotContainsString('Total R$', $operatorDocument);
    }

    public function test_pdf_integrity_failure_is_explicit_and_does_not_regenerate_over_the_snapshot(): void
    {
        Storage::fake('local');
        $sheet = $this->sheet();
        $reports = app(PreDistributionReportService::class);
        $report = $reports->create(1, $this->manager, (string) Str::uuid(), ['sheet_ids' => [$sheet['sheet_id']]]);
        $record = DB::table('pre_distribution_reports')->find($report['report_id']);
        Storage::disk('local')->put($record->path, 'invalid');
        try {
            $reports->bytes(1, $this->manager, $report['report_id']);
            self::fail('PDF corrompido deveria ser recusado.');
        } catch (HttpException $e) {
            self::assertSame(409, $e->getStatusCode());
        }
        self::assertSame($record->sha256, DB::table('pre_distribution_reports')->find($report['report_id'])->sha256);
    }

    private function officialEngines(int $notifications = 1): void
    {
        activity()->disableLogging();
        $pricing = \Mockery::mock(PricingService::class);
        $pricing->shouldReceive('resolvePrice')->andReturn(['sale_price' => '7.0000', 'cost_price' => '6', 'source' => 'price_table', 'price_table_id' => null]);
        $this->app->instance(PricingService::class, $pricing);
        $calculator = \Mockery::mock(ProjectFinancialCalculator::class);
        $calculator->shouldReceive('calculate')->andReturnUsing(fn ($project, $gross) => ['admin_fee_percentage_eff' => '1', 'total_fee' => bcmul($gross, '0.01', 8), 'net' => bcmul($gross, '0.99', 8)]);
        $this->app->instance(ProjectFinancialCalculator::class, $calculator);
        $limits = \Mockery::mock(AssociateProjectLimitService::class);
        $limits->shouldReceive('validateDistribution')->andReturnNull();
        $this->app->instance(AssociateProjectLimitService::class, $limits);
        $notice = \Mockery::mock(NotificationService::class);
        $notice->shouldReceive('notifyDistributionCompleted')->times($notifications)->andReturnNull();
        $this->app->instance(NotificationService::class, $notice);
    }

    public function test_assisted_official_creation_uses_existing_engines_and_idempotent_retry_after_lost_response(): void
    {
        $this->officialSchema();
        $sheet = $this->completedSheet();
        $this->officialEngines();
        $item = PreDistributionItem::firstOrFail();
        DB::table('pre_distribution_items')->where('id', $item->id)->update(['reference_price' => 99]);
        $key = (string) Str::uuid();
        $payload = ['operation_key' => $key, 'distributions' => [['customer_id' => 1, 'quantity' => '100', 'pre_distribution_item_id' => $item->id, 'pre_distribution_version' => 3]]];
        $response = $this->postJson('/tenant-a/delivery/deliveries/100/distribute', $payload)->assertOk()->assertJsonPath('success', true);
        $official = DB::table('production_deliveries')->whereNotNull('parent_delivery_id')->first();
        self::assertEquals(7, $official->unit_price);
        self::assertEquals(7, $official->admin_fee_amount);
        self::assertEquals(693, $official->net_value);
        self::assertSame(1, DB::table('pre_distribution_links')->count());
        DB::table('production_deliveries')->where('id', 100)->update(['quantity' => 50]);
        $this->postJson('/tenant-a/delivery/deliveries/100/distribute', $payload)->assertOk()->assertExactJson($response->json());
        self::assertSame(2, DB::table('production_deliveries')->count());
        self::assertSame(1, DB::table('pre_distribution_links')->count());
        $payload['distributions'][0]['quantity'] = '99';
        $this->postJson('/tenant-a/delivery/deliveries/100/distribute', $payload)->assertConflict();
    }

    public function test_official_creation_and_links_are_rolled_back_together_on_second_line_failure(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->officialEngines(0);
        $item = PreDistributionItem::firstOrFail();
        $rows = array_map(fn ($quantity) => ['customer_id' => 1, 'quantity' => $quantity, 'pre_distribution_item_id' => $item->id, 'pre_distribution_version' => 3], ['50', '60']);
        $this->postJson('/tenant-a/delivery/deliveries/100/distribute', ['operation_key' => (string) Str::uuid(), 'distributions' => $rows])->assertUnprocessable();
        self::assertSame(1, DB::table('production_deliveries')->count());
        self::assertSame(0, DB::table('pre_distribution_links')->count());
        self::assertSame(3, PreDistributionSheet::first()->version);
    }

    public function test_manual_official_creation_still_works_with_the_feature_disabled(): void
    {
        $this->officialSchema();
        $this->officialEngines();
        config(['pre_distributions.enabled' => false]);
        $this->postJson('/tenant-a/delivery/deliveries/100/distribute', ['distributions' => [['customer_id' => 1, 'quantity' => '20']]])->assertOk();
        self::assertSame(2, DB::table('production_deliveries')->count());
        self::assertSame(0, DB::table('pre_distribution_links')->count());
        self::assertSame(0, DB::table('pre_distribution_operations')->count());
    }

    public static function invalidQuantities(): array
    {
        return ['negative' => ['-1'], 'precision' => ['1.00001'], 'comma' => ['1,5'], 'exponent' => ['1e3'],
            'text' => ['banana'], 'overflow' => ['100000000000000'], 'array' => [[]], 'object' => [(object) ['quantity' => 1]],
            'boolean' => [true], 'infinity' => [INF], 'nan' => [NAN], 'whitespace' => [' 1']];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_operational_quantities_never_write_partial_data(mixed $quantity): void
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::firstOrFail();
        try {
            $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1, 'items' => [['id' => $item->id, 'reported_quantity' => $quantity]]]);
            self::fail('Quantidade inválida aceita.');
        } catch (ValidationException|\JsonException $e) {
            self::assertNotEmpty($e->getMessage());
        }
        self::assertNull($item->fresh()->reported_quantity);
        self::assertSame(1, PreDistributionSheet::first()->version);
        self::assertSame(1, DB::table('pre_distribution_operations')->count());
    }

    public static function incompatibleOfficialContexts(): array
    {
        return ['project' => ['sales_project_id', 2], 'customer' => ['customer_id', 2], 'product' => ['product_id', 2],
            'producer' => ['associate_id', 999], 'rejected' => ['status', 'rejected'], 'cancelled' => ['status', 'cancelled'],
            'not-approved' => ['status', 'pending'], 'other-tenant' => ['tenant_id', 2]];
    }

    #[DataProvider('incompatibleOfficialContexts')]
    public function test_incompatible_official_context_is_never_linked(string $field, mixed $value): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->official(101);
        DB::table('production_deliveries')->where('id', 101)->update([$field => $value]);
        try {
            app(PreDistributionReconciliationService::class)->linkExisting(1, $this->manager, (string) Str::uuid(),
                ['rows' => [['pre_distribution_item_id' => PreDistributionItem::first()->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '1']]]);
            self::fail('Contexto incompatível aceito.');
        } catch (ValidationException $e) {
            self::assertNotEmpty($e->errors());
        }
        self::assertSame(0, DB::table('pre_distribution_links')->count());
        self::assertSame(3, PreDistributionSheet::first()->version);
    }

    public function test_one_official_distribution_can_cover_multiple_preliminary_items_without_overuse(): void
    {
        $this->officialSchema();
        $payload = $this->payload();
        $payload['items'][] = $payload['items'][0];
        $sheet = $this->service->create(1, $this->manager, (string) Str::uuid(), $payload);
        $items = PreDistributionItem::orderBy('id')->get();
        $this->service->save(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 1,
            'items' => [['id' => $items[0]->id, 'reported_quantity' => '70.1234'], ['id' => $items[1]->id, 'reported_quantity' => '29.8766']]]);
        $this->service->transition(1, $this->operator, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 2, 'action' => 'complete']);
        $this->official(101, '100');
        $rows = [['pre_distribution_item_id' => $items[0]->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '70.1234'],
            ['pre_distribution_item_id' => $items[1]->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '29.8766']];
        $service = app(PreDistributionReconciliationService::class);
        $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => $rows]);
        self::assertSame('70.1234', $service->progress(1, $items[0]->id)['reconciled']);
        self::assertSame('29.8766', $service->progress(1, $items[1]->id)['reconciled']);
        self::assertEquals(100, DB::table('pre_distribution_links')->sum('quantity'));
        self::assertSame(4, PreDistributionSheet::first()->version);
    }

    public function test_consolidation_keeps_historical_units_separate_and_origins_filter_matches_the_group(): void
    {
        $a = $this->sheet();
        DB::table('products')->where('id', 1)->update(['unit' => 'caixa']);
        $b = $this->sheet();
        $queries = app(PreDistributionQueryService::class);
        $groups = $queries->consolidate(1, $this->manager, [$a['sheet_id'], $b['sheet_id']]);
        self::assertCount(2, $groups);
        $origins = $queries->origins(1, $this->manager, [$a['sheet_id'], $b['sheet_id']], ['unit' => 'kg']);
        self::assertCount(1, $origins);
        self::assertSame($a['sheet_id'], $origins->items()[0]->sheet_id);
        DB::table('products')->where('id', 1)->update(['unit' => 'KG']);
        $c = $this->sheet();
        self::assertCount(3, $queries->consolidate(1, $this->manager, [$a['sheet_id'], $b['sheet_id'], $c['sheet_id']]));
        self::assertCount(1, $queries->origins(1, $this->manager, [$a['sheet_id'], $b['sheet_id'], $c['sheet_id']], ['unit' => 'kg']));
    }

    public function test_inactive_membership_revokes_access_even_with_an_existing_operation_key(): void
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::firstOrFail();
        $key = (string) Str::uuid();
        $data = ['version' => 1, 'items' => [['id' => $item->id, 'reported_quantity' => '10']]];
        $this->service->save(1, $this->operator, $sheet['sheet_id'], $key, $data);
        DB::table('tenant_user')->where('user_id', $this->operator->id)->update(['status' => false]);
        try {
            $this->service->save(1, $this->operator, $sheet['sheet_id'], $key, $data);
            self::fail('Vínculo desativado ainda tem acesso.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }

    public function test_reassignment_revokes_previous_operators_replay_and_preserves_history(): void
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::firstOrFail();
        $key = (string) Str::uuid();
        $data = ['version' => 1, 'items' => [['id' => $item->id, 'reported_quantity' => '10']]];
        $this->service->save(1, $this->operator, $sheet['sheet_id'], $key, $data);
        $this->service->updateDetails(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 2, 'title' => 'Rota transferida', 'operational_date' => '2026-11-05', 'assigned_to' => 3]);
        $this->actingAs($this->operator)->putJson('/tenant-a/pre-distributions/'.$sheet['sheet_id'], $data + ['operation_key' => $key])->assertNotFound();
        self::assertSame(3, PreDistributionEvent::count());
    }

    public function test_invalid_attempt_audit_survives_business_rollback_without_saving_payload(): void
    {
        $sheet = $this->sheet();
        $item = PreDistributionItem::firstOrFail();
        $this->actingAs($this->operator)->putJson('/tenant-a/pre-distributions/'.$sheet['sheet_id'], ['operation_key' => (string) Str::uuid(), 'version' => 1,
            'items' => [['id' => $item->id, 'reported_quantity' => '-1', 'notes' => 'NÃO ARMAZENAR NO LOG DE SEGURANÇA']]])->assertUnprocessable();
        self::assertNull($item->fresh()->reported_quantity);
        self::assertSame(1, DB::table('pre_distribution_operations')->count());
        $event = DB::table('pre_distribution_security_events')->first();
        self::assertSame(422, $event->status_code);
        self::assertStringNotContainsString('NÃO ARMAZENAR', json_encode($event));
    }

    public function test_reversed_official_distribution_cannot_be_restored_or_reactivated(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->official(101);
        $service = app(PreDistributionReconciliationService::class);
        $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => [['pre_distribution_item_id' => PreDistributionItem::first()->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '40']]]);
        DB::table('production_deliveries')->where('id', 101)->update(['deleted_at' => now(), 'status' => 'cancelled']);
        $service->reverse(1, $this->manager, DB::table('pre_distribution_links')->value('id'), (string) Str::uuid(), ['version' => 4, 'reason' => 'Distribuição cancelada e removida']);
        $official = ProductionDelivery::withTrashed()->findOrFail(101);
        try {
            $official->restore();
            self::fail('Restauração poderia duplicar a quantidade liberada.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('revertida', $e->getMessage());
        }
        $observer = app(ProductionDeliveryObserver::class);
        $official->status = DeliveryStatus::APPROVED;
        try {
            $observer->updating($official);
            self::fail('Reativação indevida.');
        } catch (ValidationException $e) {
            self::assertNotEmpty($e->errors());
        }
    }

    public function test_assisted_distribution_still_enforces_the_official_reception_balance(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->official(101, '450');
        $this->officialEngines(0);
        $this->postJson('/tenant-a/delivery/deliveries/100/distribute', ['operation_key' => (string) Str::uuid(),
            'distributions' => [['customer_id' => 1, 'quantity' => '100', 'pre_distribution_item_id' => PreDistributionItem::first()->id, 'pre_distribution_version' => 3]]])->assertUnprocessable();
        self::assertSame(2, DB::table('production_deliveries')->count());
        self::assertSame(0, DB::table('pre_distribution_links')->count());
    }

    public function test_unit_change_is_reported_and_cannot_be_reconciled_implicitly(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->official(101);
        DB::table('products')->where('id', 1)->update(['unit' => 'caixa']);
        $itemId = PreDistributionItem::first()->id;
        $service = app(PreDistributionReconciliationService::class);
        self::assertSame('divergent', $service->progress(1, $itemId)['state']);
        $this->getJson('/tenant-a/pre-distributions/snippets?reception=100')->assertOk()->assertJsonCount(0, 'data');
        try {
            $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => [['pre_distribution_item_id' => $itemId, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '1']]]);
            self::fail('Unidades diferentes não podem ser convertidas.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('unidade', $e->getMessage());
        }
    }

    public function test_operator_can_open_the_portal_without_gaining_admin_or_official_permissions(): void
    {
        $this->sheet();
        $this->withoutVite();
        $this->actingAs($this->operator)->get('/tenant-a/pre-distributions')->assertOk()->assertSee('data-pre-distributions', false)->assertDontSee('data-new-sheet', false);
        self::assertFalse($this->operator->canAccessPanel(Panel::make()->id('admin')));
        self::assertTrue($this->operator->checkPermissionTo('record_pre_distributions'));
        self::assertFalse($this->operator->checkPermissionTo('manage_pre_distributions'));
        self::assertFalse($this->operator->checkPermissionTo('create_production::delivery'));
    }

    public function test_long_preliminary_report_has_multiple_pages_and_keeps_every_product(): void
    {
        Storage::fake('local');
        $data = $this->payload();
        $data['items'] = [];
        for ($index = 3; $index <= 77; $index++) {
            DB::table('products')->insert(['id' => $index, 'tenant_id' => 1, 'name' => 'Produto '.str_pad((string) $index, 3, '0', STR_PAD_LEFT).' para conferência de produtos da agricultura familiar', 'unit' => 'kg']);
            $data['items'][] = ['sales_project_id' => 1, 'customer_id' => 1, 'product_id' => $index, 'reported_quantity' => '1.2345', 'reference_price' => '3.4567'];
        }
        $sheet = $this->service->create(1, $this->manager, (string) Str::uuid(), $data);
        $reports = app(PreDistributionReportService::class);
        $report = $reports->create(1, $this->manager, (string) Str::uuid(), ['sheet_ids' => [$sheet['sheet_id']], 'include_prices' => true]);
        $bytes = $reports->bytes(1, $this->manager, $report['report_id'])['bytes'];
        self::assertGreaterThanOrEqual(2, preg_match_all('/\/Type\s*\/Page\b/', $bytes));
        $snapshot = json_decode(DB::table('pre_distribution_reports')->find($report['report_id'])->snapshot, true);
        self::assertCount(75, $snapshot['sources']);
        self::assertCount(75, $snapshot['groups']);
        if (getenv('PRE_DISTRIBUTIONS_PDF_PREVIEW') === '1') {
            File::ensureDirectoryExists(base_path('tmp/pdfs'));
            file_put_contents(base_path('tmp/pdfs/pre-distributions-multipage.pdf'), $bytes);
        }
    }

    public function test_authorized_correction_can_increase_operational_quantity_without_rewriting_official_values(): void
    {
        $this->officialSchema();
        $sheet = $this->completedSheet();
        $this->official(101);
        $itemId = PreDistributionItem::first()->id;
        $service = app(PreDistributionReconciliationService::class);
        $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => [['pre_distribution_item_id' => $itemId, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '40']]]);
        $before = DB::table('production_deliveries')->orderBy('id')->get()->toJson();
        $this->service->transition(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 4, 'action' => 'reopen', 'reason' => 'Retificar quantidade informada']);
        $this->service->save(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 5,
            'items' => [['id' => $itemId, 'reported_quantity' => '120', 'planned_quantity' => '140', 'reference_price' => '9']]]);
        $this->service->transition(1, $this->manager, $sheet['sheet_id'], (string) Str::uuid(), ['version' => 6, 'action' => 'complete']);
        self::assertSame($before, DB::table('production_deliveries')->orderBy('id')->get()->toJson());
        $progress = $service->progress(1, $itemId);
        self::assertSame('80.0000', $progress['remaining']);
        self::assertSame('partial', $progress['state']);
        self::assertSame('price_difference', $progress['warnings'][0]['code']);
    }

    public function test_ghost_official_quantity_is_detected_without_changing_financial_records(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->official(101, '600');
        $service = app(PreDistributionReconciliationService::class);
        try {
            $service->linkExisting(1, $this->manager, (string) Str::uuid(), ['rows' => [['pre_distribution_item_id' => PreDistributionItem::first()->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '40']]]);
            self::fail('Distribuição excede sua origem oficial.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('acima', $e->getMessage());
        }
        self::assertSame(0, DB::table('pre_distribution_links')->count());
        self::assertEquals(600, DB::table('production_deliveries')->where('id', 101)->value('quantity'));
    }

    public function test_engine_error_rolls_back_official_records_and_does_not_expose_internal_details(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->officialEngines(0);
        $calls = 0;
        $calculator = \Mockery::mock(ProjectFinancialCalculator::class);
        $calculator->shouldReceive('calculate')->andReturnUsing(function ($project, $gross) use (&$calls) {
            if (++$calls === 3) {
                throw new \Error('INTERNAL_ENGINE_DETAIL');
            }

            return ['admin_fee_percentage_eff' => '1', 'total_fee' => bcmul($gross, '0.01', 8), 'net' => bcmul($gross, '0.99', 8)];
        });
        $this->app->instance(ProjectFinancialCalculator::class, $calculator);
        $row = ['customer_id' => 1, 'quantity' => '40', 'pre_distribution_item_id' => PreDistributionItem::first()->id, 'pre_distribution_version' => 3];
        $this->postJson('/tenant-a/delivery/deliveries/100/distribute', ['operation_key' => (string) Str::uuid(), 'distributions' => [$row, $row]])
            ->assertStatus(500)->assertDontSee('INTERNAL_ENGINE_DETAIL');
        self::assertSame(1, DB::table('production_deliveries')->count());
        self::assertSame(0, DB::table('pre_distribution_links')->count());
        self::assertSame(0, DB::transactionLevel());
    }

    public function test_duplicate_manual_pair_is_a_validation_error_and_leaves_no_link(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->official(101);
        $row = ['pre_distribution_item_id' => PreDistributionItem::first()->id, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '10'];
        $this->postJson('/tenant-a/pre-distributions/links', ['operation_key' => (string) Str::uuid(), 'rows' => [$row, $row]])->assertUnprocessable()->assertJsonValidationErrors('pre_distributions');
        self::assertSame(0, DB::table('pre_distribution_links')->count());
    }
}
