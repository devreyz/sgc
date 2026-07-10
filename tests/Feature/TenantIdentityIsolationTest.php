<?php

namespace Tests\Feature;

use App\Services\TenantIdentityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantIdentityIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('user_id');
            $table->boolean('is_admin')->default(false);
            $table->boolean('status')->default(true);
            $table->string('tenant_name')->nullable();
            $table->timestamps();
        });
    }

    public function test_same_global_user_resolves_different_names_per_tenant(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Jose da Silva',
            'email' => 'jose@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantAId = DB::table('tenants')->insertGetId(['name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()]);
        $tenantBId = DB::table('tenants')->insertGetId(['name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('tenant_user')->insert([
            'tenant_id' => $tenantAId,
            'user_id' => $userId,
            'tenant_name' => 'Jose Reis',
            'status' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_user')->insert([
            'tenant_id' => $tenantBId,
            'user_id' => $userId,
            'tenant_name' => 'Dev Reyz',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolver = app(TenantIdentityService::class);

        $this->assertSame('Jose Reis', $resolver->displayName($tenantAId, $userId));
        $this->assertSame('Dev Reyz', $resolver->displayName($tenantBId, $userId));
        $this->assertNotSame('Jose da Silva', $resolver->displayName($tenantAId, $userId));
    }

    public function test_missing_or_empty_tenant_member_never_falls_back_to_global_name(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Global Name',
            'email' => 'global@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantId = DB::table('tenants')->insertGetId(['name' => 'Tenant', 'slug' => 'tenant', 'created_at' => now(), 'updated_at' => now()]);
        $otherTenantId = DB::table('tenants')->insertGetId(['name' => 'Other Tenant', 'slug' => 'other-tenant', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('tenant_user')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tenant_name' => '',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolver = app(TenantIdentityService::class);

        $this->assertSame('Membro sem nome cadastrado', $resolver->displayName($tenantId, $userId));
        $this->assertSame('Membro nao identificado', $resolver->displayName($otherTenantId, $userId));
    }
}
