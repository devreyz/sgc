<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\SalesProject;
use App\Models\SalesProjectType;
use App\Models\Tenant;
use App\Services\ReceiptConsentRenderer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceiptConsentCustomizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sales_project_types');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('tenants');

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->string('cnpj')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('legal_representative_name')->nullable();
            $table->string('legal_representative_role')->nullable();
            $table->string('legal_representative_cpf')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('name');
            $table->string('type');
            $table->string('template_category');
            $table->string('system_template_key')->nullable();
            $table->string('project_type')->nullable();
            $table->boolean('consent_enabled')->default(true);
            $table->string('consent_position', 16)->default('after');
            $table->longText('consent_content_before')->nullable();
            $table->longText('consent_content')->nullable();
            $table->boolean('show_representative_signature')->default(true);
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sales_project_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('name');
            $table->string('slug');
            $table->string('color')->default('gray');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function test_project_type_template_is_tenant_scoped_and_sanitized(): void
    {
        $tenantA = $this->tenant('Cooperativa A', 'coop-a');
        $tenantB = $this->tenant('Cooperativa B', 'coop-b');
        $project = new SalesProject(['title' => 'PAA 2026', 'type' => 'paa']);
        $project->tenant_id = $tenantA->id;

        $this->template($tenantA, 'paa', '<p onclick="evil()">Texto PAA {{tenant.nome}} {{valor.liquido}}</p><script>alert(1)</script>');
        $this->template($tenantB, 'paa', '<p>NAO PODE VAZAR</p>');

        $html = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenantA,
            $project,
            null,
            ['net' => 123.45],
        );

        $this->assertStringContainsString('Texto PAA Cooperativa A R$ 123,45', $html);
        $this->assertStringNotContainsString('NAO PODE VAZAR', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_generic_template_is_fallback_and_specific_template_can_hide_section(): void
    {
        $tenant = $this->tenant('Cooperativa', 'coop');
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'pnae']);
        $project->tenant_id = $tenant->id;

        $this->template($tenant, null, '<p>Mensagem geral</p>');

        $renderer = app(ReceiptConsentRenderer::class);
        $this->assertStringContainsString('Mensagem geral', (string) $renderer->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            [],
        ));

        $hidden = $this->template($tenant, 'pnae', '<p>Nao deve aparecer</p>');
        $hidden->update(['consent_enabled' => false]);

        $this->assertSame('', (string) $renderer->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            [],
        ));
    }

    public function test_custom_project_types_are_isolated_by_tenant(): void
    {
        $tenantA = $this->tenant('Tenant A', 'tenant-a');
        $tenantB = $this->tenant('Tenant B', 'tenant-b');

        SalesProjectType::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Compra Local',
            'slug' => 'compra_local',
            'color' => 'success',
        ]);

        $this->assertArrayHasKey('compra_local', SalesProjectType::options($tenantA->id));
        $this->assertArrayNotHasKey('compra_local', SalesProjectType::options($tenantB->id));
    }

    public function test_default_message_omits_cnpj_sentence_when_document_is_missing(): void
    {
        $tenant = $this->tenant('Cooperativa sem CNPJ', 'sem-cnpj');
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'paa']);
        $project->tenant_id = $tenant->id;

        $html = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            ['net' => 10],
        );

        $this->assertStringContainsString('Recebi da <strong>Cooperativa sem CNPJ</strong>, a quantia', $html);
        $this->assertStringNotContainsString('inscrita no CNPJ', $html);
    }

    public function test_template_can_render_distinct_content_before_and_after_the_table(): void
    {
        $tenant = $this->tenant('Cooperativa', 'consent-both');
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'paa']);
        $project->tenant_id = $tenant->id;
        $template = $this->template($tenant, 'paa', '<p>Texto posterior</p>');
        $template->update([
            'consent_position' => 'both',
            'consent_content_before' => '<p>Texto anterior</p>',
        ]);

        $renderer = app(ReceiptConsentRenderer::class);

        $this->assertStringContainsString('Texto anterior', (string) $renderer->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            [],
            position: 'before',
        ));
        $this->assertStringContainsString('Texto posterior', (string) $renderer->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            [],
            position: 'after',
        ));
    }

    public function test_legacy_template_remains_after_the_table_only(): void
    {
        $tenant = $this->tenant('Cooperativa', 'legacy-consent');
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'paa']);
        $project->tenant_id = $tenant->id;
        $this->template($tenant, 'paa', '<p>Texto legado</p>');

        $renderer = app(ReceiptConsentRenderer::class);

        $this->assertSame('', (string) $renderer->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            [],
            position: 'before',
        ));
        $this->assertStringContainsString('Texto legado', (string) $renderer->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            [],
            position: 'after',
        ));
    }

    public function test_representative_signature_can_be_disabled_without_leaving_an_empty_cell(): void
    {
        $tenant = $this->tenant('Cooperativa', 'no-representative');
        $tenant->update(['legal_representative_name' => 'Presidente da Cooperativa']);
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'paa']);
        $project->tenant_id = $tenant->id;
        $template = $this->template(
            $tenant,
            'paa',
            '<table><tr><td>{{assinatura.associado}}</td><td>{{assinatura.representante}}</td></tr></table>',
        );
        $template->update(['show_representative_signature' => false]);

        $html = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant->refresh(),
            $project,
            null,
            [],
        );

        $this->assertStringNotContainsString('Presidente da Cooperativa', $html);
        $this->assertStringNotContainsString('<td></td>', $html);
    }

    private function template(Tenant $tenant, ?string $projectType, string $content): DocumentTemplate
    {
        return DocumentTemplate::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consentimento',
            'type' => 'receipt',
            'template_category' => 'system',
            'system_template_key' => ReceiptConsentRenderer::ASSOCIATE,
            'project_type' => $projectType,
            'consent_enabled' => true,
            'consent_content' => $content,
            'content' => '',
            'is_active' => true,
        ]);
    }

    private function tenant(string $name, string $slug): Tenant
    {
        $id = DB::table('tenants')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Tenant::findOrFail($id);
    }
}
