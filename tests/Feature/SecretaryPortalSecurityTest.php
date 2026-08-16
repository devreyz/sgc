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

        foreach (['generated_documents', 'pdf_layout_templates', 'document_templates', 'model_has_roles', 'roles', 'tenant_user', 'tenants', 'users'] as $table) {
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
            $table->string('tenant_password')->nullable();
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
            $table->string('system_template_key')->nullable();
            $table->string('project_type')->nullable();
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->json('visible_sections')->nullable();
            $table->json('visible_columns')->nullable();
            $table->string('paper_size')->default('a4');
            $table->string('paper_orientation')->default('portrait');
            $table->unsignedInteger('table_scale')->default(100);
            $table->unsignedBigInteger('header_layout_id')->nullable();
            $table->unsignedBigInteger('footer_layout_id')->nullable();
            $table->unsignedBigInteger('cover_layout_id')->nullable();
            $table->unsignedBigInteger('back_cover_layout_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('pdf_layout_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('layout_type');
            $table->longText('content')->nullable();
            $table->unsignedInteger('estimated_height_mm')->default(22);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('generated_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('template_id');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('status')->default('draft');
            $table->json('variables_used')->nullable();
            $table->json('document_settings')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->unsignedBigInteger('last_edited_by')->nullable();
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

    public function test_tenant_member_without_secretary_role_cannot_open_archive(): void
    {
        $user = User::query()->findOrFail(1);
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)->update(['roles' => json_encode([])]);

        $this->actingAs($user)->withSession(['tenant_id' => 1])
            ->getJson('/tenant-a/secretary')
            ->assertForbidden();
    }

    public function test_secretary_cannot_edit_template_from_another_tenant(): void
    {
        $user = User::query()->findOrFail(1);

        $this->actingAs($user)
            ->withSession(['tenant_id' => 1])
            ->get('/tenant-a/secretary/templates/20/edit')
            ->assertNotFound();
    }

    public function test_custom_template_content_is_sanitized_on_the_server(): void
    {
        $user = User::query()->findOrFail(1);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => 1])
            ->postJson('/tenant-a/secretary/templates', [
                'name' => 'Ata segura',
                'description' => 'Modelo de reunião',
                'type' => 'minutes',
                'content' => '<h2 onclick="alert(1)">Reunião</h2><script>alert(2)</script><a href="javascript:alert(3)">Abrir</a>',
                'paper_size' => 'a4',
                'paper_orientation' => 'portrait',
                'header_layout_id' => null,
                'footer_layout_id' => null,
                'is_active' => true,
            ]);

        $response->assertCreated();
        $content = (string) DB::table('document_templates')->where('name', 'Ata segura')->value('content');
        $this->assertStringContainsString('Reunião', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('javascript:', $content);
    }

    public function test_signed_document_cannot_be_changed_or_deleted(): void
    {
        $user = User::query()->findOrFail(1);
        $documentId = DB::table('generated_documents')->where('tenant_id', 1)->value('id');
        DB::table('generated_documents')->where('id', $documentId)->update(['signed_at' => now(), 'status' => 'signed']);

        $payload = [
            'title' => 'Alterado', 'content' => '<p>Alterado</p>', 'template_id' => 10,
            'paper_size' => 'a4', 'paper_orientation' => 'portrait',
            'header_layout_id' => null, 'footer_layout_id' => null,
        ];

        $this->actingAs($user)->withSession(['tenant_id' => 1])
            ->putJson("/tenant-a/secretary/documents/{$documentId}", $payload)
            ->assertStatus(409);
        $this->actingAs($user)->withSession(['tenant_id' => 1])
            ->deleteJson("/tenant-a/secretary/documents/{$documentId}")
            ->assertStatus(409);
    }

    public function test_layout_is_created_inside_current_tenant_and_sanitized(): void
    {
        $user = User::query()->findOrFail(1);

        $response = $this->actingAs($user)->withSession(['tenant_id' => 1])
            ->postJson('/tenant-a/secretary/layouts', [
                'name' => 'Cabeçalho da ata', 'layout_type' => 'header',
                'content' => '<div onmouseover="alert(1)">{{cooperativa.nome}}<script>alert(2)</script></div>',
                'estimated_height_mm' => 24, 'is_active' => true,
            ]);

        $response->assertCreated();
        $layout = DB::table('pdf_layout_templates')->where('name', 'Cabeçalho da ata')->first();
        $this->assertSame(1, (int) $layout->tenant_id);
        $this->assertStringNotContainsString('onmouseover', $layout->content);
        $this->assertStringNotContainsString('<script', $layout->content);
    }
}
