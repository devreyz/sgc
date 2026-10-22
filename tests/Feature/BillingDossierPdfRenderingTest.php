<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Services\Accounting\BillingDossierReportService;
use App\Services\TemplatedPdfService;
use Tests\TestCase;

class BillingDossierPdfRenderingTest extends TestCase
{
    private function data(string $type, ?DocumentTemplate $template = null): array
    {
        $tenant = new Tenant;
        $tenant->forceFill(['id' => 0, 'name' => 'Associação de demonstração', 'email' => 'contato@example.test', 'phone' => '3833330000']);
        $product = ['name' => 'Feijão Carioca', 'project' => 'PAA 2026', 'unit' => 'kg', 'quantity' => '20.3', 'gross' => '205', 'unit_price' => '10.0985', 'count' => 3, 'from' => '01/10/2026', 'to' => '21/10/2026'];
        $units = [['unit' => 'kg', 'quantity' => '20.3']];
        $product += ['discounts' => '10.25', 'accruals' => '2.00', 'net' => '196.75',
            'fees' => [['name' => 'Taxa administrativa', 'nature' => 'discount', 'amount' => '10.25'], ['name' => 'Acréscimo', 'nature' => 'accrual', 'amount' => '2.00']]];
        $report = ['title' => BillingDossierReportService::TYPES[$type]['title'], 'report_type' => $type,
            'tenant' => $tenant, 'billing' => ['number' => 'COM-0081/2026', 'recipient' => 'Cliente', 'recipient_document' => null, 'projects' => 'PAA 2026'],
            'captured_at' => '21/10/2026 12:00:00', 'period' => '01/10/2026 a 21/10/2026', 'scope_label' => 'Somente esta folha',
            'distribution_count' => 3, 'member_count' => 1, 'gross' => '205', 'units' => $units, 'products' => [$product], 'warnings' => [],
            'members' => [['name' => 'Ana Silva', 'document' => null, 'registration' => '001', 'caf' => null, 'property' => null,
                'location' => 'Miravânia/MG', 'products' => [$product], 'count' => 3, 'units' => $units]],
            'documents' => [['type' => 'Folha de faturamento', 'number' => 'COM-0081/2026', 'owner' => 'Cliente', 'status' => 'Emitida', 'date' => '21/10/2026', 'count' => 3]],
        ];
        $report['customers'] = [['name' => 'Escola Municipal', 'document' => '00.000.000/0001-00', 'projects' => 'PAA 2026',
            'members' => $report['members'], 'units' => $units, 'totals' => ['gross' => '205.00', 'discounts' => '10.25', 'accruals' => '2.00', 'net' => '196.75']]];
        $report['commercial_products'] = [$product];
        foreach ($report['documents'] as &$document) $document['amount'] = '196.75';
        unset($document);
        $report['document_totals'] = BillingDossierReportService::summarizeDocuments($report['documents']);
        return ['report' => $report, 'title' => $report['title'], 'tenant' => $tenant, 'system_pdf_template' => $template];
    }

    public function test_all_three_reports_render_without_blank_footer_pages_or_financial_identity_blocks(): void
    {
        foreach (BillingDossierReportService::TYPES as $type => $definition) {
            $data = $this->data($type);
            $html = view($definition['view'], $data)->render();
            self::assertStringNotContainsString('Documento informativo.', $html);
            self::assertStringNotContainsString('contato@example.test', $html);
            self::assertStringContainsString('3833330000', $html);
            self::assertStringNotContainsString('Total entregue por unidade de medida', $html);
            self::assertStringNotContainsString('Ajustes conforme as taxas atuais', $html);
            self::assertStringNotContainsString('Código de validação', $html);
            $pdf = app(TemplatedPdfService::class)->generateSystemPdf($definition['view'], $data);
            self::assertStringStartsWith('%PDF-', $pdf->output());
            self::assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count(), $type);
        }
    }

    public function test_members_report_starts_each_client_on_a_new_page_and_uses_requested_columns(): void
    {
        $data = $this->data('members');
        $second = $data['report']['customers'][0];
        $second['name'] = 'Outro cliente';
        $data['report']['customers'][] = $second;
        $html = view('pdf.billing-member-deliveries', $data)->render();
        foreach (['>Produto</th>', '>U.C.</th>', 'Qtde entregue', 'Valor unitário', 'Valor total', 'Total ajustado', 'Nome do beneficiário fornecedor (agricultor)', 'Assinatura', '1 kg'] as $text) self::assertStringContainsString($text, $html);
        self::assertStringNotContainsString('Preço médio', $html);
        self::assertStringNotContainsString('<td>Outro cliente', $html);
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf('pdf.billing-member-deliveries', $data);
        self::assertSame(2, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_cover_back_cover_section_order_and_tokens_are_applied_to_the_current_report(): void
    {
        $template = new DocumentTemplate(['report_contents' => ['cover' => '<h1>Capa {{cliente.nome}}</h1>',
            'before_table' => '<p>Introdução {{faturamento.numero}}</p>', 'back_cover' => '<h2>Final {{relatorio.periodo}}</h2>'],
            'section_order' => ['table', 'context']]);
        $data = $this->data('acceptance', $template);
        $html = view('pdf.billing-acceptance-term', $data)->render();
        self::assertStringContainsString('Capa Cliente', $html);
        self::assertStringContainsString('Introdução COM-0081/2026', $html);
        self::assertLessThan(strpos($html, '<strong>Referência:</strong>'), strpos($html, '<h2>Relação de produtos entregues</h2>'));
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf('pdf.billing-acceptance-term', $data);
        self::assertSame(3, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_old_saved_sections_do_not_restore_removed_quantity_totals_or_verbose_default_summary(): void
    {
        $data = $this->data('members');
        $data['visible_sections'] = ['context', 'summary', 'members', 'table', 'totals'];
        $html = view('pdf.billing-member-deliveries', $data)->render();
        self::assertStringNotContainsString('Resumo da captura', $html);
        self::assertStringNotContainsString('Total do associado:', $html);
        self::assertStringNotContainsString('Total entregue por unidade de medida', $html);
        self::assertStringContainsString('Total do cliente:', $html);
        $data['visible_sections'] = ['context', 'summary', 'members', 'table'];
        self::assertStringNotContainsString('Resumo da captura', view('pdf.billing-member-deliveries', $data)->render());
        foreach (['billing_member_deliveries', 'billing_acceptance_term', 'billing_document_register'] as $key) {
            $definition = DocumentTemplate::getSystemTemplateDefinitions()[$key];
            self::assertArrayNotHasKey('totals', $definition['sections']);
            self::assertNotContains('totals', $definition['default_sections']);
        }
    }

    public function test_email_is_still_available_to_other_documents_using_the_shared_header(): void
    {
        $data = $this->data('members');
        self::assertStringContainsString('contato@example.test', view('pdf.partials.header', ['tenant' => $data['tenant']])->render());
    }

    public function test_member_identification_uses_a_highlighted_panel_and_a_larger_signature_area(): void
    {
        $data = $this->data('members');
        $data['report']['customers'][0]['members'][0]['name'] = 'Analice Ferreira de Farias Lopes';
        $html = view('pdf.billing-member-deliveries', $data)->render();
        foreach (['Analice Ferreira de Farias Lopes', 'br-beneficiary-name', 'br-beneficiary-signature-space', 'width:30%', 'height: 4mm', 'border-left: 4px solid', '#f3f6f4'] as $text) self::assertStringContainsString($text, $html);
        self::assertStringNotContainsString('height: 15mm', $html);
        self::assertSame(1, preg_match('/<table class="br-identity-grid"><tr>(.*?)<\/tr>/s', $html, $matches));
        self::assertSame(4, substr_count($matches[1], '<td '));
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf('pdf.billing-member-deliveries', $data);
        self::assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_member_report_does_not_repeat_project_period_or_title_in_client_panels(): void
    {
        $data = $this->data('members');
        $second = $data['report']['customers'][0];
        $second['name'] = 'Outro cliente';
        $data['report']['customers'][] = $second;
        $html = view('pdf.billing-member-deliveries', $data)->render();
        self::assertSame(1, substr_count($html, 'PAA 2026'));
        self::assertSame(1, substr_count($html, $data['report']['period']));
        self::assertStringNotContainsString('RELATÓRIO DE ENTREGAS · CLIENTE', $html);
        self::assertStringContainsString('Escola Municipal', $html);
        self::assertStringContainsString('Outro cliente', $html);
        self::assertStringContainsString('CNPJ: 00.000.000/0001-00', $html);
    }

    public function test_system_pdf_omits_template_generation_dates_but_direct_views_keep_their_timestamp(): void
    {
        $data = $this->data('members');
        $data['generated_at'] = '22/10/2026 06:21:04';
        self::assertStringContainsString('Gerado em: 22/10/2026 06:21:04', view('pdf.billing-member-deliveries', $data)->render());
        $renderData = [];
        \Illuminate\Support\Facades\View::composer('pdf.billing-member-deliveries', function ($view) use (&$renderData): void {
            $renderData = $view->getData();
        });
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf('pdf.billing-member-deliveries', $data);
        self::assertTrue($renderData['suppress_generation_timestamp']);
        $text = view('pdf.billing-member-deliveries', $renderData)->render();
        self::assertStringNotContainsString('Gerado em:', $text);
        self::assertStringNotContainsString('Criado em', $text);
        self::assertStringContainsString('01/10/2026 a 21/10/2026', $text);
    }

    public function test_acceptance_starts_with_a_simple_commercial_table_and_keeps_optional_text_sections_available(): void
    {
        $data = $this->data('acceptance');
        $html = view('pdf.billing-acceptance-term', $data)->render();
        foreach (['Produto', 'U.C.', 'Unidade de comercialização', 'Qtd comercializada', 'Valor unitário', 'Valor total', '1 kg', '10,0985'] as $text) self::assertStringContainsString($text, $html);
        self::assertStringNotContainsString('O responsável poderá conferir', $html);
        self::assertStringNotContainsString('Conferência pelo destinatário', $html);
        $data['visible_sections'] = ['context', 'declaration', 'table', 'signature'];
        $html = view('pdf.billing-acceptance-term', $data)->render();
        self::assertStringContainsString('Recebimento e conferência', $html);
        self::assertStringContainsString('Conferência pelo destinatário', $html);
        $body = file_get_contents(resource_path('views/pdf/partials/billing-dossier-report-body.blade.php'));
        self::assertStringNotContainsString('<style>', $body);
    }

    public function test_document_register_sums_values_by_type_with_decimal_precision_and_does_not_duplicate_unknown_attachments(): void
    {
        $data = $this->data('documents');
        $base = $data['report']['documents'][0];
        $data['report']['documents'] = [
            array_merge($base, ['amount' => '100.00']),
            array_merge($base, ['type' => 'Comprovante do associado', 'owner' => 'Ana Silva', 'number' => 'MEM-0001', 'amount' => '0.10']),
            array_merge($base, ['type' => 'Comprovante do associado', 'owner' => 'José Silva', 'number' => 'MEM-0002', 'amount' => '0.20']),
            array_merge($base, ['type' => 'Comprovante do associado', 'number' => 'MEM-0003', 'amount' => '0.00']),
            array_merge($base, ['type' => 'Arquivo no Drive', 'number' => 'Cópia', 'amount' => null]),
        ];
        $data['report']['document_totals'] = BillingDossierReportService::summarizeDocuments($data['report']['documents']);
        self::assertSame(['100.00', '0.30', null], array_column($data['report']['document_totals'], 'amount'));
        self::assertSame([1, 3, 1], array_column($data['report']['document_totals'], 'count'));
        self::assertSame([0, 0, 1], array_column($data['report']['document_totals'], 'missing'));
        $html = view('pdf.billing-document-register', $data)->render();
        foreach (['Valor líquido', 'Totais por tipo de documento', 'R$ 0,30', 'R$ 0,00', 'Não informado'] as $text) self::assertStringContainsString($text, $html);
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf('pdf.billing-document-register', $data);
        self::assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_compact_member_table_can_continue_across_pages_with_long_product_and_member_names(): void
    {
        $data = $this->data('members');
        $member = &$data['report']['customers'][0]['members'][0];
        $member['name'] = 'Maria Aparecida de Souza e Silva dos Santos';
        $product = $member['products'][0];
        $product['name'] = 'Alface americana selecionada para fornecimento à alimentação escolar';
        $member['products'] = array_fill(0, 45, $product);
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf('pdf.billing-member-deliveries', $data);
        self::assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        self::assertLessThan(6, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }
}
