<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
}
