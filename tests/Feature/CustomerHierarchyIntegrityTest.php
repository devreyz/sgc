<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerHierarchyIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['activity_log', 'customer_billing_receipts', 'production_deliveries', 'customers', 'organizations'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('unit_type', 20)->default('independent');
            $table->unsignedBigInteger('parent_customer_id')->nullable();
            $table->unsignedBigInteger('price_table_id')->nullable();
            $table->string('name');
            $table->string('cnpj', 18)->nullable();
            $table->string('type')->default('escola');
            $table->boolean('status')->default(true);
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
        Schema::create('production_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('gross_value', 14, 4)->default(0);
            $table->unsignedBigInteger('billing_receipt_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customer_billing_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->json('delivery_ids')->nullable();
            $table->timestamps();
        });

        DB::table('organizations')->insert([
            ['id' => 10, 'tenant_id' => 1, 'name' => 'Rede Escolar A', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'tenant_id' => 1, 'name' => 'Rede Escolar B', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'tenant_id' => 2, 'name' => 'Outra Tenant', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        session(['tenant_id' => 1]);
    }

    public function test_unrelated_customers_cannot_share_a_document(): void
    {
        $this->customer(['name' => 'Escola A', 'cnpj' => '12.345.678/0001-90']);

        $this->expectException(ValidationException::class);
        $this->customer(['name' => 'Escola B', 'cnpj' => '12345678000190']);
    }

    public function test_customers_in_the_same_organization_can_share_a_document(): void
    {
        $first = $this->customer(['name' => 'Escola A', 'organization_id' => 10, 'cnpj' => '12.345.678/0001-90']);
        $second = $this->customer(['name' => 'Escola B', 'organization_id' => 10, 'cnpj' => '12345678000190']);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(10, (int) $second->organization_id);
    }

    public function test_branch_can_share_the_headquarters_document_without_changing_historical_ids(): void
    {
        $headquarters = $this->customer(['name' => 'Escola Matriz', 'cnpj' => '12.345.678/0001-90']);
        $branch = $this->customer([
            'name' => 'Escola Filial',
            'cnpj' => '12.345.678/0001-90',
            'unit_type' => 'branch',
            'parent_customer_id' => $headquarters->id,
        ]);

        $this->assertSame($headquarters->id, (int) $branch->parent_customer_id);
        $this->assertSame('headquarters', $headquarters->fresh()->unit_type);
        $this->assertDatabaseHas('customers', ['id' => $headquarters->id, 'name' => 'Escola Matriz']);
    }

    public function test_same_document_cannot_cross_customer_organizations_or_tenants(): void
    {
        $this->customer(['name' => 'Escola A', 'organization_id' => 10, 'cnpj' => '12.345.678/0001-90']);

        try {
            $this->customer(['name' => 'Escola B', 'organization_id' => 11, 'cnpj' => '12.345.678/0001-90']);
            $this->fail('O mesmo documento não poderia atravessar organizações sem vínculo de matriz.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cnpj', $exception->errors());
        }

        DB::table('customers')->insert([
            'id' => 99,
            'tenant_id' => 2,
            'name' => 'Matriz Externa',
            'cnpj' => '98.765.432/0001-10',
            'unit_type' => 'headquarters',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->customer([
                'name' => 'Filial Inválida',
                'cnpj' => '98.765.432/0001-10',
                'unit_type' => 'branch',
                'parent_customer_id' => 99,
            ]);
            $this->fail('Uma matriz de outra tenant não poderia ser vinculada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_customer_id', $exception->errors());
        }
    }

    public function test_historical_branch_cannot_be_detached_changed_or_deleted(): void
    {
        $headquarters = $this->customer([
            'name' => 'Escola Matriz',
            'organization_id' => 10,
            'cnpj' => '12.345.678/0001-90',
        ]);
        $branch = $this->customer([
            'name' => 'Escola Filial',
            'organization_id' => 10,
            'cnpj' => '12.345.678/0001-90',
            'unit_type' => 'branch',
            'parent_customer_id' => $headquarters->id,
        ]);

        DB::table('production_deliveries')->insert([
            'tenant_id' => 1,
            'customer_id' => $branch->id,
            'quantity' => 10,
            'unit_price' => 7.5,
            'gross_value' => 75,
            'billing_receipt_id' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('customer_billing_receipts')->insert([
            'id' => 500,
            'tenant_id' => 1,
            'organization_id' => 10,
            'delivery_ids' => json_encode([$branch->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $branch->update(['parent_customer_id' => null, 'unit_type' => 'independent']);
            $this->fail('Uma filial com historico nao poderia ser desvinculada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_customer_id', $exception->errors());
        }

        $branch->refresh();
        try {
            $branch->update(['organization_id' => 11]);
            $this->fail('Uma unidade com historico nao poderia trocar de organizacao.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('organization_id', $exception->errors());
        }

        $branch->refresh();
        try {
            $branch->delete();
            $this->fail('Uma unidade com historico nao poderia ser excluida.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer', $exception->errors());
        }

        $this->assertDatabaseHas('customers', [
            'id' => $branch->id,
            'organization_id' => 10,
            'parent_customer_id' => $headquarters->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('production_deliveries', [
            'customer_id' => $branch->id,
            'gross_value' => 75,
        ]);
    }

    public function test_historical_headquarters_and_branches_can_be_grouped_in_an_organization_later(): void
    {
        $headquarters = $this->customer([
            'name' => 'Escola Matriz Existente',
            'cnpj' => '12.345.678/0001-90',
        ]);
        $branch = $this->customer([
            'name' => 'Escola Filial Existente',
            'cnpj' => '12.345.678/0001-90',
            'unit_type' => 'branch',
            'parent_customer_id' => $headquarters->id,
        ]);

        DB::table('production_deliveries')->insert([
            'tenant_id' => 1,
            'customer_id' => $headquarters->id,
            'quantity' => 10,
            'unit_price' => 7.5,
            'gross_value' => 75,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'tenant_id' => 1,
            'customer_id' => $branch->id,
            'quantity' => 5,
            'unit_price' => 7.5,
            'gross_value' => 37.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headquarters->update(['organization_id' => 10]);

        $this->assertDatabaseHas('customers', [
            'id' => $headquarters->id,
            'organization_id' => 10,
            'unit_type' => 'headquarters',
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $branch->id,
            'organization_id' => 10,
            'parent_customer_id' => $headquarters->id,
        ]);
        $this->assertDatabaseHas('production_deliveries', [
            'customer_id' => $branch->id,
            'gross_value' => 37.5,
        ]);

        $branch->update(['organization_id' => null]);

        $this->assertNull($headquarters->fresh()->organization_id);
        $this->assertNull($branch->fresh()->organization_id);
        $this->assertDatabaseHas('production_deliveries', [
            'customer_id' => $branch->id,
            'gross_value' => 37.5,
        ]);
    }

    public function test_organization_can_be_removed_before_receipts_but_branch_link_remains_protected(): void
    {
        $headquarters = $this->customer([
            'name' => 'Matriz Protegida',
            'organization_id' => 10,
        ]);
        $branch = $this->customer([
            'name' => 'Filial Protegida',
            'organization_id' => 10,
            'unit_type' => 'branch',
            'parent_customer_id' => $headquarters->id,
        ]);

        $branch->update(['organization_id' => null]);

        $this->assertNull($branch->fresh()->organization_id);
        $this->assertNull($headquarters->fresh()->organization_id);

        $headquarters->update(['organization_id' => 11]);

        $this->assertSame(11, (int) $headquarters->fresh()->organization_id);
        $this->assertSame(11, (int) $branch->fresh()->organization_id);

        $branch->refresh();
        try {
            $branch->update(['parent_customer_id' => null, 'unit_type' => 'independent']);
            $this->fail('O vínculo da filial não poderia ser desfeito.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_customer_id', $exception->errors());
        }

        $branch->refresh();
        try {
            $branch->delete();
            $this->fail('Uma filial agrupada não poderia ser excluída.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer', $exception->errors());
        }

        $branch->refresh()->update(['status' => false]);
        Organization::withoutGlobalScopes()->findOrFail(10)->update(['active' => false]);

        $this->assertFalse($branch->fresh()->status);
        $this->assertFalse(Organization::withoutGlobalScopes()->findOrFail(10)->active);
    }

    public function test_organization_cannot_be_removed_after_a_receipt_links_its_deliveries(): void
    {
        $customer = $this->customer([
            'name' => 'Cliente com comprovante da organização',
            'organization_id' => 10,
        ]);
        DB::table('customer_billing_receipts')->insert([
            'id' => 700,
            'tenant_id' => 1,
            'organization_id' => 10,
            'delivery_ids' => json_encode([900]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'id' => 900,
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'quantity' => 3,
            'unit_price' => 10,
            'gross_value' => 30,
            'billing_receipt_id' => 700,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $customer->update(['organization_id' => null]);
            $this->fail('A organização com comprovante vinculado não poderia ser removida.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('organization_id', $exception->errors());
        }

        $this->assertSame(10, (int) $customer->fresh()->organization_id);
    }

    public function test_future_price_table_can_change_without_rewriting_frozen_delivery_values(): void
    {
        $customer = $this->customer(['name' => 'Escola com historico']);
        DB::table('production_deliveries')->insert([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'quantity' => 10,
            'unit_price' => 7.5,
            'gross_value' => 75,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer->update(['price_table_id' => 99]);

        $this->assertSame(99, (int) $customer->fresh()->price_table_id);
        $this->assertDatabaseHas('production_deliveries', [
            'customer_id' => $customer->id,
            'unit_price' => 7.5,
            'gross_value' => 75,
        ]);
    }

    public function test_organization_with_customers_cannot_be_deleted(): void
    {
        $this->customer(['name' => 'Escola vinculada', 'organization_id' => 10]);
        $organization = Organization::withoutGlobalScopes()->findOrFail(10);

        $this->expectException(ValidationException::class);
        $organization->delete();
    }

    public function test_organization_with_direct_financial_history_cannot_be_deleted(): void
    {
        DB::table('customer_billing_receipts')->insert([
            'tenant_id' => 1,
            'organization_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $organization = Organization::withoutGlobalScopes()->findOrFail(10);

        $this->expectException(ValidationException::class);
        $organization->delete();
    }

    private function customer(array $attributes): Customer
    {
        return Customer::create($attributes + [
            'tenant_id' => 1,
            'type' => 'escola',
            'unit_type' => 'independent',
            'status' => true,
        ]);
    }
}
