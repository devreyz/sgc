<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BuildsDeliveryReportSchema
{
    protected function buildDeliveryReportSchema(): void
    {

        foreach (['sales_project_customers', 'sales_project_organizations', 'price_tables', 'production_deliveries', 'customers', 'organizations', 'products', 'associates', 'tenant_user', 'sales_projects', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('associate_term_singular')->default('Associado');
            $table->string('associate_term_plural')->default('Associados');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('sales_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('type')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('delivery_report_preferences')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('tenant_name')->nullable();
            $table->timestamps();
        });
        Schema::create('associates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('cpf_cnpj')->nullable();
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
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('price_table_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('production_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            $table->unsignedBigInteger('associate_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->date('delivery_date');
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('admin_fee_amount', 12, 4)->default(0);
            $table->decimal('net_value', 12, 4)->default(0);
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('price_tables', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('name'); $table->softDeletes();
        });
        Schema::create('sales_project_customers', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('sales_project_id'); $table->unsignedBigInteger('customer_id');
            $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('sales_project_organizations', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('sales_project_id'); $table->unsignedBigInteger('organization_id');
            $table->text('notes')->nullable(); $table->boolean('enforce_request_limits')->default(false); $table->timestamps();
        });

        DB::table('tenants')->insert([
            ['id' => 1, 'name' => 'Cooperativa A', 'associate_term_singular' => 'Cooperado', 'associate_term_plural' => 'Cooperados', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Cooperativa B', 'associate_term_singular' => 'Produtor', 'associate_term_plural' => 'Produtores', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('sales_projects')->insert([
            ['id' => 10, 'tenant_id' => 1, 'title' => 'PAA 2026', 'type' => 'PAA', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'tenant_id' => 2, 'title' => 'Outro projeto', 'type' => 'PAA', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('tenant_user')->insert([
            ['tenant_id' => 1, 'user_id' => 100, 'tenant_name' => 'Maria Local', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => 2, 'user_id' => 100, 'tenant_name' => 'Nome do Outro Tenant', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('associates')->insert([
            ['id' => 30, 'tenant_id' => 1, 'user_id' => 100, 'cpf_cnpj' => '123', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 40, 'tenant_id' => 2, 'user_id' => 100, 'cpf_cnpj' => '456', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('products')->insert([
            ['id' => 50, 'tenant_id' => 1, 'name' => 'Banana', 'unit' => 'kg', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 60, 'tenant_id' => 2, 'name' => 'Produto externo', 'unit' => 'kg', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('organizations')->insert(['id' => 70, 'tenant_id' => 1, 'name' => 'Prefeitura', 'short_name' => 'PM', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customers')->insert([
            ['id' => 80, 'tenant_id' => 1, 'organization_id' => 70, 'name' => 'Escola A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 81, 'tenant_id' => 1, 'organization_id' => 70, 'name' => 'Escola B', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('production_deliveries')->insert([
            ['id' => 90, 'tenant_id' => 1, 'sales_project_id' => 10, 'parent_delivery_id' => null, 'associate_id' => 30, 'customer_id' => null, 'product_id' => 50, 'delivery_date' => '2026-08-01', 'quantity' => 100, 'unit_price' => 0, 'admin_fee_amount' => 0, 'net_value' => 0, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 91, 'tenant_id' => 1, 'sales_project_id' => 10, 'parent_delivery_id' => 90, 'associate_id' => 30, 'customer_id' => 80, 'product_id' => 50, 'delivery_date' => '2026-08-01', 'quantity' => 40, 'unit_price' => 5, 'admin_fee_amount' => 10, 'net_value' => 190, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 92, 'tenant_id' => 1, 'sales_project_id' => 10, 'parent_delivery_id' => 90, 'associate_id' => 30, 'customer_id' => 81, 'product_id' => 50, 'delivery_date' => '2026-08-01', 'quantity' => 20, 'unit_price' => 6, 'admin_fee_amount' => 5, 'net_value' => 115, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 99, 'tenant_id' => 2, 'sales_project_id' => 20, 'parent_delivery_id' => null, 'associate_id' => 40, 'customer_id' => null, 'product_id' => 60, 'delivery_date' => '2026-08-01', 'quantity' => 999, 'unit_price' => 999, 'admin_fee_amount' => 0, 'net_value' => 0, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('sales_projects')->where('id', 10)->update(['customer_id' => 80]);
        DB::table('sales_project_customers')->insert(['sales_project_id' => 10, 'customer_id' => 81]);
    }
}
