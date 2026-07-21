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
            $table->longText('consent_content')->nullable();
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
