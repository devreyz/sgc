<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecretaryPortalSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['generated_documents', 'document_templates', 'model_has_roles', 'roles', 'tenant_user', 'tenants', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
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
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('status')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->json('roles')->nullable();
            $table->string('tenant_name')->nullable();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('type')->default('other');
            $table->string('template_category')->default('custom');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('generated_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('template_id');
            $table->string('title');
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('users')->insert(['id' => 1, 'name' => 'Conta', 'email' => 'admin@example.test', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenants')->insert([
            ['id' => 1, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('tenant_user')->insert(['tenant_id' => 1, 'user_id' => 1, 'status' => true, 'is_admin' => true, 'roles' => json_encode(['secretario']), 'tenant_name' => 'Secretária A', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => User::class, 'model_id' => 1]);
        DB::table('document_templates')->insert([
            ['id' => 10, 'tenant_id' => 1, 'name' => 'Ata Tenant A', 'type' => 'minutes', 'template_category' => 'custom', 'content' => '', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'tenant_id' => 2, 'name' => 'Ata Privada Tenant B', 'type' => 'minutes', 'template_category' => 'custom', 'content' => '', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('generated_documents')->insert([
            ['tenant_id' => 1, 'template_id' => 10, 'title' => 'Reunião A', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => 2, 'template_id' => 20, 'title' => 'Reunião Privada B', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_secretary_data_never_returns_another_tenant_documents(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->withSession(['tenant_id' => 1])
            ->getJson('/tenant-a/secretary/data')
            ->assertOk()
            ->assertJsonPath('summary.documents', 1)
            ->assertJsonFragment(['title' => 'Reunião A'])
            ->assertJsonMissing(['title' => 'Reunião Privada B'])
            ->assertJsonMissing(['name' => 'Ata Privada Tenant B']);
    }

    public function test_secretary_cannot_change_tenant_in_url(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->withSession(['tenant_id' => 1])
            ->getJson('/tenant-b/secretary/data')
            ->assertForbidden();
    }
}
