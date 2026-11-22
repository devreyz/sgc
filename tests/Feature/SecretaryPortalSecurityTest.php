<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecretaryPortalSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['generated_document_revisions', 'generated_documents', 'documents', 'pdf_layout_templates', 'document_templates', 'model_has_permissions', 'role_has_permissions', 'model_has_roles', 'permissions', 'roles', 'tenant_user', 'tenants', 'users'] as $table) {
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
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('guard_name'); $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id'); $table->unsignedBigInteger('role_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id'); $table->unsignedBigInteger('model_id'); $table->string('model_type');
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
            $table->string('color_theme')->nullable();
            $table->boolean('consent_enabled')->default(false);
            $table->string('consent_position')->nullable();
            $table->text('consent_content_before')->nullable();
            $table->text('consent_content')->nullable();
            $table->boolean('show_recipient_signature')->default(true);
            $table->boolean('show_representative_signature')->default(true);
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
            $table->string('documentable_type')->nullable();
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
        (require database_path('migrations/2024_01_01_000023_create_documents_table.php'))->up();
        Schema::table('documents', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id'));
        \Spatie\Permission\Models\Role::findOrCreate('secretario', 'web');
        (require database_path('migrations/2026_11_22_090000_add_institutional_documents_to_secretary.php'))->up();

        DB::table('users')->insert(['id' => 1, 'name' => 'Conta', 'email' => 'admin@example.test', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenants')->insert([
            ['id' => 1, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('tenant_user')->insert(['tenant_id' => 1, 'user_id' => 1, 'status' => true, 'is_admin' => true, 'roles' => json_encode(['secretario']), 'tenant_name' => 'Secretária A', 'created_at' => now(), 'updated_at' => now()]);
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        DB::table('document_templates')->insert([
            ['id' => 10, 'tenant_id' => 1, 'name' => 'Ata Tenant A', 'type' => 'minutes', 'template_category' => 'custom', 'content' => '', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'tenant_id' => 2, 'name' => 'Ata Privada Tenant B', 'type' => 'minutes', 'template_category' => 'custom', 'content' => '', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('generated_documents')->insert([
            ['tenant_id' => 1, 'template_id' => 10, 'title' => 'Reunião A', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => 2, 'template_id' => 20, 'title' => 'Reunião Privada B', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_system_pdf_configuration_has_its_own_catalog_and_preview(): void
    {
        $this->actingAs(User::query()->findOrFail(1))->withSession(['tenant_id' => 1]);
        $payload = [
            'name' => 'Recibo específico', 'paper_size' => 'a4', 'paper_orientation' => 'portrait',
            'is_active' => true, 'visible_sections' => ['items', 'invalid'], 'visible_columns' => ['description'],
            'table_scale' => 90, 'consent_enabled' => false, 'consent_position' => 'after',
            'show_recipient_signature' => true, 'show_representative_signature' => false, 'color_theme' => 'org',
        ];
        $base = '/tenant-a/secretary/system-pdfs';
        $key = '/financial_receipt';
        $this->get($base)->assertOk()->assertSee('Comprovantes e recibos')->assertDontSee('Ata Tenant A');
        $this->get($base.$key.'/edit')->assertOk()->assertSee('Abrir amostra em PDF')->assertSee('id="system-pdf-form"', false);
        $this->postJson($base.$key.'/preview', $payload)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        foreach (['deliveries_associate', 'deliveries_product'] as $report) {
            $this->postJson($base.'/'.$report.'/preview', array_replace($payload, [
                'visible_sections' => ['filters', 'summary_cards', 'deliveries', 'totals'],
                'visible_columns' => \App\Models\DocumentTemplate::getSystemTemplateDefinitions()[$report]['default_columns'],
            ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $this->assertDatabaseMissing('document_templates', ['name' => $payload['name']]);
        $this->postJson($base.$key, $payload)->assertCreated();
        $template = \App\Models\DocumentTemplate::query()->where('name', $payload['name'])->firstOrFail();
        $this->assertSame(['items'], $template->visible_sections);
        $this->get('/tenant-a/secretary/templates/'.$template->id.'/edit')->assertOk()->assertSee('id="system-pdf-form"', false);
        $this->getJson('/tenant-a/secretary/data?kind=templates')->assertOk()->assertJsonMissing(['name' => $payload['name']]);
        $this->postJson($base.$key, $payload)->assertConflict();
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
        DB::table('tenant_user')->where('tenant_id', 1)->where('user_id', 1)->update(['roles' => json_encode([]), 'is_admin' => false]);

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
        $this->getJson('/tenant-a/secretary/layouts/'.$layout->id.'/fragment')->assertOk()
            ->assertJsonPath('html', '<div>{{cooperativa.nome}}</div>');
    }

    public function test_tenant_admin_without_secretary_role_has_access_but_inactive_membership_does_not(): void
    {
        DB::table('tenant_user')->where('tenant_id', 1)->update(['roles' => json_encode([])]);
        $this->actingAs(User::findOrFail(1))->getJson('/tenant-a/secretary/data')->assertOk();
        DB::table('tenant_user')->where('tenant_id', 1)->update(['status' => false]);
        $this->getJson('/tenant-a/secretary/data')->assertForbidden();
    }

    public function test_secretary_without_an_issue_grant_cannot_issue(): void
    {
        DB::table('tenant_user')->where('tenant_id', 1)->update(['is_admin' => false]);
        \Spatie\Permission\Models\Role::findByName('secretario', 'web')->revokePermissionTo('issue_secretary_documents');
        $this->actingAs(User::findOrFail(1))->getJson('/tenant-a/secretary/data')->assertOk();
        $this->postJson('/tenant-a/secretary/documents/1/issue', ['revision' => 1])->assertForbidden();
    }

    public function test_official_letter_draft_autosave_issue_cancel_duplicate_and_history(): void
    {
        Storage::fake('local');
        $this->actingAs(User::findOrFail(1))->withSession(['tenant_id' => 1]);
        $root = '/tenant-a/secretary';
        $draft = $this->postJson($root.'/documents', $this->letterPayload())->assertCreated()->assertJsonPath('revision', 1)->json('id');
        $url = $root.'/documents/'.$draft;
        $this->get($url.'/edit')->assertOk()->assertSee('Dados do ofício')
            ->assertSee('id="se-paper-header"', false)->assertSee('id="se-paper-footer"', false);
        $this->putJson($url, array_replace($this->letterPayload(), [
            'revision' => 1, 'autosave' => true, 'content' => '<p>Solicitação atualizada para {{recipient.institution}}</p><div style="page-break-after:always"><br></div><p>Página seguinte</p>',
        ]))->assertOk()->assertJsonPath('revision', 2);
        $this->putJson($url, $this->letterPayload() + ['revision' => 1])->assertConflict();
        $this->postJson($url.'/issue', ['revision' => 1])->assertConflict();
        $preview = $this->get($url.'/preview')->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $preview);
        preg_match_all('/\/Type\s*\/Page\b/', $preview, $pages);
        $this->assertGreaterThanOrEqual(2, count($pages[0]));

        $this->postJson($url.'/issue', ['revision' => 2])->assertOk()->assertJsonPath('number', '001/2026/SEC');
        $this->postJson($url.'/issue', ['revision' => 2])->assertOk()->assertJsonPath('number', '001/2026/SEC');
        $archived = $this->get($url.'/preview?download=1')->assertOk()->streamedContent();
        $this->assertStringStartsWith('%PDF-', $archived);
        $this->assertSame('CODEVASF', \App\Models\GeneratedDocument::findOrFail($draft)->variables_used['recipient.institution']);
        $this->putJson($url, $this->letterPayload() + ['revision' => 3])->assertConflict();
        $this->postJson($url.'/cancel', ['reason' => ''])->assertUnprocessable();
        $this->postJson($url.'/cancel', ['reason' => 'Substituído por novo ofício.'])->assertOk();
        $this->assertSame($archived, $this->get($url.'/preview')->assertOk()->streamedContent());
        $this->get($url.'/revisions/1')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($root.'/documents/create?from_document='.$draft)->assertOk()->assertSee('CODEVASF')->assertDontSee('001/2026/SEC');
        $this->getJson($root.'/data?kind=documents&status=cancelled&search=CODEVASF')->assertOk()->assertJsonPath('documents.data.0.number', '001/2026/SEC');
        $this->assertDatabaseHas('generated_document_revisions', ['generated_document_id' => $draft, 'event' => 'cancelled', 'revision' => 4]);

        $next = $this->postJson($root.'/documents', $this->letterPayload())->assertCreated()->json('id');
        $this->postJson($root.'/documents/'.$next.'/issue', ['revision' => 1])->assertOk()->assertJsonPath('number', '002/2026/SEC');
    }

    public function test_private_images_and_markdown_are_scoped_and_sanitized(): void
    {
        Storage::fake('local');
        $this->actingAs(User::findOrFail(1))->withSession(['tenant_id' => 1]);
        $root = '/tenant-a/secretary';
        $image = $this->postJson($root.'/images', ['image' => UploadedFile::fake()->image('campo.png', 20, 20)])->assertCreated()->json('url');
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(str_replace('/tenant-a/', '/tenant-b/', $image))->assertForbidden();
        DB::table('tenant_user')->insert(['tenant_id' => 2, 'user_id' => 1, 'status' => true, 'is_admin' => true]);
        $this->get(str_replace('/tenant-a/', '/tenant-b/', $image))->assertNotFound();
        $this->getJson($root.'/images')->assertJsonFragment(['url' => $image]);
        $draft = $this->postJson($root.'/documents', array_replace($this->letterPayload(), [
            'content' => '<figure><img src="'.$image.'" onerror="alert(1)" alt="Máquina"><figcaption>Máquina</figcaption></figure>'
                .'<img src="https://example.test/tracker.png"><script>danger</script><table><tr><td>Medição</td></tr></table>',
        ]))->assertCreated()->json('id');
        $content = \App\Models\GeneratedDocument::findOrFail($draft)->content;
        $this->assertStringContainsString($image, $content);
        $this->assertStringContainsString('<table>', $content);
        $this->assertStringNotContainsString('tracker.png', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->get($root.'/documents/'.$draft.'/preview')->assertOk();
        $markdown = $this->postJson($root.'/markdown', ['markdown' => '**Assunto** <script>perigo</script>'])->assertOk()->json('html');
        $this->assertStringContainsString('<strong>Assunto</strong>', $markdown);
        $this->assertStringNotContainsString('<script>', $markdown);
    }

    public function test_default_template_and_pdf_storage_failure_can_be_retried(): void
    {
        $disk = Storage::fake('local');
        $this->actingAs(User::findOrFail(1))->withSession(['tenant_id' => 1]);
        $root = '/tenant-a/secretary';
        $template = [
            'name' => 'Correspondência da Diretoria', 'description' => 'Modelo padrão', 'type' => 'official_letter',
            'content' => '<p>Modelo da Diretoria</p>', 'paper_size' => 'a4', 'paper_orientation' => 'portrait',
            'is_active' => true, 'layout' => array_replace(\App\Services\OfficialLetterService::LAYOUT, ['default' => true, 'department' => 'Diretoria']),
        ];
        $created = $this->postJson($root.'/templates', $template)->assertCreated()->json('id');
        $this->get($root.'/documents/create')->assertOk()->assertSee('Modelo da Diretoria');
        $this->get($root.'/templates/'.$created.'/preview')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $draft = $this->postJson($root.'/documents', $this->letterPayload() + ['template_id' => $created])->assertCreated()->json('id');
        $url = $root.'/documents/'.$draft.'/issue';

        $failingDisk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $failingDisk->shouldReceive('put')->once()->andReturn(false);
        $failingDisk->shouldReceive('delete')->once()->andReturn(true);
        app('filesystem')->set('local', $failingDisk);
        $this->postJson($url, ['revision' => 1])->assertStatus(507);
        $this->assertDatabaseHas('generated_documents', ['id' => $draft, 'status' => 'draft', 'institutional_sequence' => null]);
        $this->assertDatabaseMissing('generated_document_revisions', ['generated_document_id' => $draft, 'event' => 'issued']);

        app('filesystem')->set('local', $disk);
        $this->postJson($url, ['revision' => 1])->assertOk()->assertJsonPath('number', '001/2026/SEC');
        $preview = $root.'/documents/'.$draft.'/preview';
        $original = $this->get($preview)->assertOk()->streamedContent();
        $this->putJson($root.'/templates/'.$created, array_replace($template, ['content' => '<p>Novo modelo</p>']))->assertOk();
        $this->assertSame($original, $this->get($preview)->assertOk()->streamedContent());
    }

    private function letterPayload(): array
    {
        return [
            'title' => 'Manutenção de equipamentos', 'content' => '<p>Solicitamos a manutenção dos equipamentos.</p>',
            'paper_size' => 'a4', 'paper_orientation' => 'portrait',
            'letter' => ['institution' => 'CODEVASF', 'subject' => 'Manutenção', 'city' => 'Miravânia',
                'date' => '2026-11-22', 'salutation' => 'Prezados Senhores,', 'closing' => 'Atenciosamente,',
                'signatory' => 'Ana de Souza', 'signatory_position' => 'Presidente'],
            'layout' => array_replace(\App\Services\OfficialLetterService::LAYOUT, [
                'sector' => 'SEC', 'header_name' => 'Associação de Teste', 'footer' => 'Secretaria institucional',
            ]),
        ];
    }
}
