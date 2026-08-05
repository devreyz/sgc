<?php

namespace Tests\Feature;

use App\Models\AssociateReceipt;
use App\Models\DocumentTemplate;
use App\Models\SalesProject;
use App\Models\SalesProjectType;
use App\Models\Tenant;
use App\Services\ReceiptConsentRenderer;
use App\Services\SystemPdfConfigurationResolver;
use App\Services\TemplatedPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
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
            $table->string('associate_term_singular')->default('Associado');
            $table->string('associate_term_plural')->default('Associados');
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
            $table->boolean('show_recipient_signature')->default(true);
            $table->boolean('show_representative_signature')->default(true);
            $table->json('visible_sections')->nullable();
            $table->json('visible_columns')->nullable();
            $table->string('paper_size')->default('a4');
            $table->string('paper_orientation')->default('portrait');
            $table->unsignedTinyInteger('table_scale')->default(100);
            $table->unsignedBigInteger('header_layout_id')->nullable();
            $table->unsignedBigInteger('footer_layout_id')->nullable();
            $table->string('color_theme')->nullable();
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

    public function test_system_pdf_configuration_has_one_tenant_and_project_type_aware_precedence(): void
    {
        $tenantA = $this->tenant('Cooperativa A', 'pdf-coop-a');
        $tenantB = $this->tenant('Cooperativa B', 'pdf-coop-b');
        $generic = $this->template($tenantA, null, 'Padrao');
        $generic->update([
            'visible_columns' => ['unit_price'],
            'paper_orientation' => 'portrait',
            'table_scale' => 90,
        ]);
        $specific = $this->template($tenantA, 'paa', 'PAA');
        $specific->update([
            'visible_columns' => ['gross', 'net'],
            'paper_orientation' => 'landscape',
            'table_scale' => 70,
        ]);
        $this->template($tenantB, 'paa', 'Outro tenant')->update([
            'visible_columns' => ['unit_price'],
            'table_scale' => 80,
        ]);

        $resolver = app(SystemPdfConfigurationResolver::class);
        $paa = $resolver->resolve('pdf.project-associate-receipt', $tenantA->id, 'paa');
        $pnae = $resolver->resolve('pdf.project-associate-receipt', $tenantA->id, 'pnae');

        $this->assertSame($specific->id, $paa['template']->id);
        $this->assertSame(['gross', 'net'], $paa['visible_columns']);
        $this->assertSame('landscape', $paa['orientation']);
        $this->assertSame(70, $paa['table_scale']);
        $this->assertSame($generic->id, $pnae['template']->id);
        $this->assertSame(['unit_price'], $pnae['visible_columns']);
        $this->assertSame(90, $pnae['table_scale']);
    }

    public function test_project_receipt_columns_and_scale_override_generic_template_preferences(): void
    {
        $tenant = $this->tenant('Cooperativa', 'runtime-project-columns');
        $template = $this->template($tenant, 'paa', '');
        $template->update([
            'visible_columns' => ['gross'],
            'visible_sections' => ['associate_info', 'deliveries'],
            'table_scale' => 70,
        ]);

        $project = new SalesProject(['title' => 'PAA 2026', 'type' => 'paa']);
        $project->id = 12;
        $project->tenant_id = $tenant->id;

        $document = Mockery::mock(DomPdfDocument::class);
        $document->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.project-associate-receipt', Mockery::on(function (array $data): bool {
                $this->assertSame(['fee:associate:7', 'net'], $data['visible_columns']);
                $this->assertSame(['fee:associate:7', 'net'], $data['visibleColumns']);
                $this->assertSame(90, $data['table_scale']);
                $this->assertSame(['associate_info', 'deliveries'], $data['visible_sections']);

                return true;
            }))
            ->andReturn($document);

        $result = app(TemplatedPdfService::class)->generateSystemPdf('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'visible_columns' => ['fee:associate:7', 'net'],
            'table_scale' => 90,
        ]);

        $this->assertSame($document, $result);
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

    public function test_recipient_and_representative_signatures_are_independently_configurable(): void
    {
        $tenant = $this->tenant('Cooperativa', 'signature-selection');
        $tenant->update(['legal_representative_name' => 'Presidente da Cooperativa']);
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'paa']);
        $project->tenant_id = $tenant->id;
        $template = $this->template(
            $tenant,
            'paa',
            '<p>Consentimento</p><table><tr><td>{{assinatura.associado}}</td><td>{{assinatura.representante}}</td></tr></table>',
        );

        $template->update([
            'show_recipient_signature' => false,
            'show_representative_signature' => true,
        ]);

        $representativeOnly = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant->refresh(),
            $project,
            null,
            [],
        );

        $this->assertStringContainsString('Presidente da Cooperativa', $representativeOnly);
        $this->assertStringNotContainsString('Associado', $representativeOnly);

        $template->update([
            'show_recipient_signature' => true,
            'show_representative_signature' => false,
        ]);

        $associateOnly = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant->refresh(),
            $project,
            null,
            [],
        );

        $this->assertStringContainsString('Associado', $associateOnly);
        $this->assertStringNotContainsString('Presidente da Cooperativa', $associateOnly);
    }

    public function test_enabled_signatures_are_appended_when_custom_text_has_no_signature_variables(): void
    {
        $tenant = $this->tenant('Cooperativa', 'automatic-signatures');
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'paa']);
        $project->tenant_id = $tenant->id;
        $template = $this->template($tenant, 'paa', '<p>Texto personalizado sem blocos.</p>');
        $template->update([
            'show_recipient_signature' => true,
            'show_representative_signature' => true,
        ]);

        $html = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            [],
        );

        $this->assertStringContainsString('Texto personalizado sem blocos.', $html);
        $this->assertStringContainsString('Associado', $html);
        $this->assertStringContainsString('Representante da organizacao', $html);
        $this->assertSame(2, substr_count($html, 'class="receipt-signature"'));
    }

    public function test_both_signatures_can_be_disabled_without_hiding_consent_text(): void
    {
        $tenant = $this->tenant('Cooperativa', 'no-signatures');
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'paa']);
        $project->tenant_id = $tenant->id;
        $template = $this->template($tenant, 'paa', '<p>Somente o consentimento.</p>');
        $template->update([
            'show_recipient_signature' => false,
            'show_representative_signature' => false,
        ]);

        $html = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            null,
            [],
        );

        $this->assertStringContainsString('Somente o consentimento.', $html);
        $this->assertStringNotContainsString('receipt-signature', $html);
    }

    public function test_associate_terminology_is_resolved_from_the_receipt_tenant(): void
    {
        $tenant = $this->tenant('Cooperativa', 'custom-associate-term');
        $tenant->forceFill([
            'associate_term_singular' => 'Cooperado',
            'associate_term_plural' => 'Cooperados',
        ])->saveQuietly();
        $project = new SalesProject(['title' => 'Projeto', 'type' => 'paa']);
        $project->tenant_id = $tenant->id;
        $this->template(
            $tenant,
            'paa',
            '<p>{{tenant.termo_associado}} / {{tenant.termo_associados}}</p>{{assinatura.associado}}',
        );

        $html = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant->refresh(),
            $project,
            null,
            [],
        );

        $this->assertStringContainsString('Cooperado / Cooperados', $html);
        $this->assertStringContainsString('class="sig-role">Cooperado', $html);
        $this->assertStringNotContainsString('Produtor / Associado', $html);
    }

    public function test_receipt_numeric_variables_have_semantically_typed_extensive_versions(): void
    {
        $tenant = $this->tenant('Cooperativa', 'extensive-values');
        $project = new SalesProject([
            'title' => 'Projeto',
            'type' => 'paa',
            'reference_year' => 2026,
            'total_value' => 2000,
            'admin_fee_percentage' => 7.5,
        ]);
        $project->tenant_id = $tenant->id;
        $receipt = new AssociateReceipt([
            'receipt_number' => 21,
            'receipt_year' => 2026,
            'issued_at' => '2026-07-27',
        ]);
        $this->template(
            $tenant,
            'paa',
            '<p>{{projeto.ano_referencia_extenso}} | {{projeto.valor_total_extenso}} | '
                .'{{projeto.taxa_admin_extenso}} | {{comprovante.numero_extenso}} | '
                .'{{comprovante.ano_extenso}} | {{comprovante.itens_extenso}} | '
                .'{{valor.bruto_extenso}} | {{valor.taxas_extenso}} | {{valor.liquido_extenso}}</p>',
        );

        $html = (string) app(ReceiptConsentRenderer::class)->render(
            ReceiptConsentRenderer::ASSOCIATE,
            $tenant,
            $project,
            $receipt,
            [
                'gross' => 150.50,
                'fees' => 10.25,
                'net' => 140.25,
                'items_count' => 3,
            ],
        );

        $this->assertStringContainsString('dois mil e vinte e seis', $html);
        $this->assertStringContainsString('dois mil reais', $html);
        $this->assertStringContainsString('sete vírgula cinco por cento', $html);
        $this->assertStringContainsString('vinte e um', $html);
        $this->assertStringContainsString('três', $html);
        $this->assertStringContainsString('cento e cinquenta reais e cinquenta centavos', $html);
        $this->assertStringContainsString('dez reais e vinte e cinco centavos', $html);
        $this->assertStringContainsString('cento e quarenta reais e vinte e cinco centavos', $html);
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
