<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Services\Accounting\BillingReportContentRenderer;
use App\Services\SystemPdfConfigurationResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingReportTemplateConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['document_templates', 'sales_projects', 'customers', 'organizations'] as $table) Schema::dropIfExists($table);
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('name')->nullable();
            $table->string('template_category')->default('system'); $table->string('system_template_key');
            $table->string('project_type')->nullable(); $table->boolean('is_active')->default(true);
            $table->json('section_order')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        foreach (['sales_projects', 'customers', 'organizations'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id(); $table->unsignedBigInteger('tenant_id'); $table->softDeletes();
            });
            DB::table($tableName)->insert([['id' => 10, 'tenant_id' => 1], ['id' => 20, 'tenant_id' => 2]]);
        }
        $migration = require base_path('database/migrations/2026_10_21_000001_add_billing_report_template_configuration.php');
        $migration->up();
    }

    private function template(array $data = []): DocumentTemplate
    {
        return DocumentTemplate::create(array_merge(['tenant_id' => 1, 'system_template_key' => 'billing_acceptance_term',
            'template_category' => 'system', 'is_active' => true], $data));
    }

    public function test_specific_report_templates_coexist_with_defaults_and_resolve_only_for_the_correct_context(): void
    {
        $default = $this->template();
        $typed = $this->template(['project_type' => 'paa']);
        $customer = $this->template(['customer_id' => 10]);
        $project = $this->template(['sales_project_id' => 10]);
        $both = $this->template(['sales_project_id' => 10, 'customer_id' => 10]);
        self::assertSame(5, DocumentTemplate::withoutGlobalScopes()->where('is_active', true)->count());
        $resolver = app(SystemPdfConfigurationResolver::class);
        self::assertSame($default->id, $resolver->templateForKey('billing_acceptance_term', 1)?->id);
        self::assertSame($typed->id, $resolver->templateForKey('billing_acceptance_term', 1, 'paa')?->id);
        self::assertSame($customer->id, $resolver->templateForKey('billing_acceptance_term', 1, 'paa', ['customer_id' => 10])?->id);
        self::assertSame($project->id, $resolver->templateForKey('billing_acceptance_term', 1, 'paa', ['sales_project_id' => 10])?->id);
        self::assertSame($both->id, $resolver->templateForKey('billing_acceptance_term', 1, 'paa', ['sales_project_id' => 10, 'customer_id' => 10])?->id);
        $replacement = $this->template(['sales_project_id' => 10, 'customer_id' => 10]);
        self::assertFalse($both->fresh()->is_active);
        self::assertTrue($default->fresh()->is_active);
        self::assertSame($replacement->id, $resolver->templateForKey('billing_acceptance_term', 1, 'paa', ['sales_project_id' => 10, 'customer_id' => 10])?->id);
        self::assertNull($resolver->templateForKey('billing_acceptance_term', 2));
    }

    public function test_report_configuration_rejects_cross_tenant_scopes_and_sanitizes_rich_content(): void
    {
        $template = $this->template(['report_contents' => ['intro' => '<p>Olá {{cliente.nome}}</p><script>bad()</script>', 'invalid' => '<p>ignore</p>'],
            'section_order' => ['table', 'context', 'invalid', 'table']]);
        self::assertStringNotContainsString('<script>', $template->report_contents['intro']);
        self::assertArrayNotHasKey('invalid', $template->report_contents);
        self::assertSame(['table', 'context'], $template->section_order);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->template(['customer_id' => 20]);
    }

    public function test_report_tokens_are_escaped_and_never_evaluated_as_blade(): void
    {
        $template = new DocumentTemplate(['report_contents' => ['intro' => '<p>{{cliente.nome}} · {{relatorio.quantidades}} · {{faturamento.numero}}</p>']]);
        $report = ['tenant' => new \App\Models\Tenant, 'title' => 'Termo', 'captured_at' => '21/10/2026 12:00:00',
            'period' => 'Outubro', 'scope_label' => 'Folha', 'billing' => ['number' => '001/2026', 'recipient' => '<script>bad()</script>', 'recipient_document' => '', 'projects' => 'PAA'],
            'member_count' => 1, 'distribution_count' => 2, 'units' => [['quantity' => '5', 'unit' => 'kg'], ['quantity' => '2', 'unit' => 'un']],
            'gross' => '10', 'documents' => []];
        $html = (string) app(BillingReportContentRenderer::class)->render('intro', $report, $template);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('5,000 kg · 2,000 un', $html);
        self::assertStringContainsString('001/2026', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function test_acceptance_defaults_migration_preserves_custom_content_and_other_report_preferences(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->json('visible_sections')->nullable();
            $table->json('visible_columns')->nullable();
        });
        DB::table('document_templates')->insert([
            ['id' => 1, 'tenant_id' => 1, 'system_template_key' => 'billing_acceptance_term',
                'visible_sections' => json_encode(['context', 'declaration', 'table', 'signature']), 'visible_columns' => json_encode(['quantity']),
                'report_contents' => json_encode(['intro' => '<p>Meu texto personalizado</p>'])],
            ['id' => 2, 'tenant_id' => 2, 'system_template_key' => 'billing_acceptance_term',
                'visible_sections' => json_encode(['table', 'signature']), 'visible_columns' => json_encode(['quantity', 'count']), 'report_contents' => null],
            ['id' => 3, 'tenant_id' => 1, 'system_template_key' => 'billing_member_deliveries',
                'visible_sections' => json_encode(['context', 'table']), 'visible_columns' => json_encode(['quantity']), 'report_contents' => null],
        ]);
        $migration = require base_path('database/migrations/2026_10_22_000001_simplify_acceptance_report_defaults.php');
        $migration->up();
        $migration->up();
        $default = DB::table('document_templates')->find(1);
        self::assertSame(['context', 'table'], json_decode($default->visible_sections, true));
        self::assertSame(['quantity', 'unit_price', 'gross'], json_decode($default->visible_columns, true));
        self::assertSame(['intro' => '<p>Meu texto personalizado</p>'], json_decode($default->report_contents, true));
        self::assertSame(['table', 'signature'], json_decode(DB::table('document_templates')->find(2)->visible_sections, true));
        self::assertSame(['quantity', 'count'], json_decode(DB::table('document_templates')->find(2)->visible_columns, true));
        self::assertSame(['quantity'], json_decode(DB::table('document_templates')->find(3)->visible_columns, true));
    }
}
