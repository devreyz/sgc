<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryViewerSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'model_has_roles',
            'role_has_permissions',
            'permissions',
            'roles',
            'delivery_project_notes',
            'production_deliveries',
            'project_associate_product_limits',
            'project_demands',
            'products',
            'associates',
            'sales_projects',
            'tenant_user',
            'tenants',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->string('locale')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $table) {
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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('sales_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('associates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nickname')->nullable();
            $table->string('registration_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('unit')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('project_demands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('target_quantity', 14, 3);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('project_associate_product_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('max_quantity', 14, 4);
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('production_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            $table->string('status');
            $table->decimal('quantity', 14, 4);
            $table->decimal('gross_value', 14, 2)->default(0);
            $table->date('delivery_date');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Conta global',
            'email' => 'viewer@example.test',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tenants')->insert([
            ['id' => 1, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('tenant_user')->insert([
            'tenant_id' => 1,
            'user_id' => 1,
            'tenant_name' => 'Observador A',
            'roles' => json_encode(['visualizador_entregas']),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sales_projects')->insert([
            [
                'id' => 10,
                'tenant_id' => 1,
                'title' => 'Projeto do Tenant A',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 20,
                'tenant_id' => 2,
                'title' => 'Projeto privado do Tenant B',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('associates')->insert([
            'id' => 100,
            'tenant_id' => 1,
            'user_id' => 1,
            'nickname' => 'Observador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'id' => 200,
            'tenant_id' => 1,
            'name' => 'Banana Prata',
            'unit' => 'kg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_demands')->insert([
            'tenant_id' => 1,
            'sales_project_id' => 10,
            'product_id' => 200,
            'target_quantity' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_associate_product_limits')->insert([
            'tenant_id' => 1,
            'sales_project_id' => 10,
            'associate_id' => 100,
            'product_id' => 200,
            'max_quantity' => 80,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            [
                'id' => 300,
                'tenant_id' => 1,
                'sales_project_id' => 10,
                'associate_id' => 100,
                'product_id' => 200,
                'parent_delivery_id' => null,
                'status' => 'approved',
                'quantity' => 30,
                'gross_value' => 0,
                'delivery_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 301,
                'tenant_id' => 1,
                'sales_project_id' => 10,
                'associate_id' => 100,
                'product_id' => 200,
                'parent_delivery_id' => 300,
                'status' => 'approved',
                'quantity' => 20,
                'gross_value' => 200,
                'delivery_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_viewer_cannot_open_project_from_another_tenant(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->get('/tenant-a/delivery-viewer/projects/20')
            ->assertNotFound();
    }

    public function test_viewer_cannot_use_delivery_registration_endpoint(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->postJson('/tenant-a/delivery/projects/20/register', [
                'associate_id' => 1,
                'product_id' => 1,
                'delivery_date' => now()->toDateString(),
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    public function test_sequential_associate_id_is_not_a_valid_viewer_url(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->get('/tenant-a/delivery-viewer/projects/10/associates/1')
            ->assertNotFound();
    }

    public function test_sequential_product_id_is_not_a_valid_viewer_url(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->get('/tenant-a/delivery-viewer/projects/10/products/1')
            ->assertNotFound();
    }

    public function test_product_token_is_bound_to_its_project(): void
    {
        $user = User::query()->findOrFail(1);
        $payload = json_encode([
            'scope' => 'delivery-viewer-product',
            'project_id' => 20,
            'product_id' => 1,
        ], JSON_THROW_ON_ERROR);
        $token = rtrim(strtr(Crypt::encryptString($payload), '+/', '-_'), '=');

        $this->actingAs($user)
            ->get("/tenant-a/delivery-viewer/projects/10/products/{$token}")
            ->assertNotFound();
    }

    public function test_product_monitoring_returns_bounded_aggregates_and_paginated_associates(): void
    {
        $user = User::query()->findOrFail(1);
        $token = $this->productToken(10, 200);

        $this->actingAs($user)
            ->getJson("/tenant-a/delivery-viewer/projects/10/products/{$token}/data")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('summary.target', 100)
            ->assertJsonPath('summary.planned', 80)
            ->assertJsonPath('summary.received', 30)
            ->assertJsonPath('summary.distributed', 20)
            ->assertJsonPath('summary.associates_count', 1);

        $this->actingAs($user)
            ->getJson("/tenant-a/delivery-viewer/projects/10/products/{$token}/associates")
            ->assertOk()
            ->assertJsonPath('per_page', 12)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Observador A')
            ->assertJsonPath('data.0.maximum', 80)
            ->assertJsonPath('data.0.received', 30)
            ->assertJsonPath('data.0.distributed', 20);
    }

    private function productToken(int $projectId, int $productId): string
    {
        $payload = json_encode([
            'scope' => 'delivery-viewer-product',
            'project_id' => $projectId,
            'product_id' => $productId,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(Crypt::encryptString($payload), '+/', '-_'), '=');
    }
}
