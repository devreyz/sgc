<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\Organization;
use App\Models\SalesProject;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PdfRenderingCompatibilityTest extends TestCase
{
    public function test_updated_pdf_engine_generates_a_valid_pdf(): void
    {
        $contents = Pdf::loadHTML('<html><body><h1>Comprovante SGC</h1></body></html>')
            ->setPaper('a4', 'portrait')
            ->output();

        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertGreaterThan(500, strlen($contents));
    }

    public function test_operational_report_renders_with_shared_theme_and_concise_columns(): void
    {
        $html = view('pdf.deliveries-report-v2', [
            'tenant' => null,
            'title' => 'Relatório de Entregas',
            'generated_at' => '21/07/2026 12:00',
            'filters' => [],
            'deliveries' => collect(),
            'totals' => ['quantity' => 0, 'gross' => 0, 'admin_fee' => 0, 'net' => 0],
        ])->render();

        $this->assertStringContainsString('#374151', $html);
        $this->assertStringContainsString('margin: 16mm 15mm 18mm 15mm', $html);
        $this->assertStringContainsString('background: #f7f7f7', $html);
        $this->assertStringContainsString('font-size: 9.4px', $html);
        $this->assertStringContainsString('Projeto', $html);
        $this->assertStringNotContainsString('Taxa Admin</th>', $html);

        $contents = Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF-', $contents);
    }

    public function test_five_distribution_receipts_remain_on_one_page(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();

        $data = compact('tenant', 'project', 'associate', 'receipt', 'summary');
        $data += [
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
        ];

        foreach (['pdf.project-associate-receipt', 'pdf.associate-portal-receipt'] as $view) {
            $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
            $pdf->render();

            $this->assertSame(
                1,
                $pdf->getDomPDF()->get_canvas()->get_page_count(),
                $view.' should fit five simple distributions on one page.',
            );
        }
    }

    public function test_multi_page_receipt_keeps_complete_columns_after_page_break(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $distribution = $products[0]['distributions'][0];
        $products[0]['distributions'] = collect(range(1, 34))->map(fn (int $index): array => array_merge($distribution, [
            'customer_name' => 'Destino '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]))->all();
        $products[0]['total_quantity'] = 340;
        $products[0]['total_gross'] = 3400;
        $products[0]['total_admin_fee'] = 170;
        $products[0]['total_net'] = 3230;
        $products = [$products[0]];
        $summary = array_merge($summary, [
            'gross_value' => 3400,
            'admin_fee' => 170,
            'net_value' => 3230,
            'deliveries_count' => 34,
            'total_quantity' => 340,
        ]);
        $data = compact('tenant', 'project', 'associate', 'receipt', 'summary') + [
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
        ];

        $html = view('pdf.project-associate-receipt', $data)->render();
        $this->assertStringContainsString('class="receipt-product-group"', $html);
        $this->assertStringContainsString('rowspan="4"', $html);
        $this->assertStringContainsString('continuação', $html);

        $pdf = Pdf::loadView('pdf.project-associate-receipt', $data)->setPaper('a4', 'portrait');
        $contents = $pdf->output();
        $this->assertGreaterThan(1, $pdf->getDomPDF()->get_canvas()->get_page_count());

        if ($output = env('SGC_PDF_QA_OUTPUT')) {
            File::ensureDirectoryExists(dirname($output));
            file_put_contents($output, $contents);
        }
    }

    public function test_associate_receipt_delivery_date_column_can_be_hidden(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();

        $html = view('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'visible_columns' => ['unit_price', 'gross'],
        ])->render();

        $this->assertStringNotContainsString('<th style="width:11%;">Data</th>', $html);
    }

    public function test_two_copy_receipt_reuses_the_same_layout_on_two_pages(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        $data = [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'copyLabels' => ['1ª VIA — MEMBRO', '2ª VIA — ORGANIZAÇÃO'],
        ];

        $html = view('pdf.project-associate-receipt', $data)->render();
        $this->assertStringContainsString('1ª VIA — MEMBRO', $html);
        $this->assertStringContainsString('2ª VIA — ORGANIZAÇÃO', $html);

        $pdf = Pdf::loadView('pdf.project-associate-receipt', $data)->setPaper('a4', 'portrait');
        $pdf->render();

        $this->assertSame(2, $pdf->getDomPDF()->get_canvas()->get_page_count());
    }

    public function test_organization_receipt_respects_sections_and_places_total_quantity_before_price(): void
    {
        $tenant = new Tenant(['name' => 'Cooperativa Teste']);
        $project = new SalesProject(['title' => 'PAA 2026']);
        $organization = new Organization(['name' => 'Prefeitura Municipal']);
        $customer = new Customer(['name' => 'Escola Central']);
        $customer->id = 10;
        $receipt = new CustomerBillingReceipt([
            'receipt_year' => 2026,
            'receipt_number' => 12,
            'issued_at' => '2026-08-05',
        ]);
        $priceGroups = [[
            'price_table_name' => 'Tabela 2026',
            'customers' => collect([$customer]),
            'table' => [[
                'product' => 'Banana',
                'unit' => 'kg',
                'unit_price' => 5.5,
                'by_customer' => [10 => 20],
                'total_qty' => 20,
                'total_gross' => 110,
                'fee_values' => [],
            ]],
            'subtotal_gross' => 110,
            'subtotal_net' => 110,
            'fee_totals' => [],
        ]];

        $html = view('pdf.customer-organization-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'organization' => $organization,
            'receipt' => $receipt,
            'customers' => collect([$customer]),
            'priceGroups' => $priceGroups,
            'multiplePriceTables' => false,
            'totalGross' => 110,
            'totalFees' => 0,
            'totalNet' => 110,
            'periodLabel' => '05/08/2026',
            'feeColumns' => [],
            'visibleColumns' => ['unit_price', 'gross'],
            'visible_sections' => ['document_info', 'organization_info', 'project_info', 'deliveries'],
        ])->render();

        $this->assertStringContainsString('Nº Documento:', $html);
        $this->assertStringNotContainsString('<div class="sec-label">Resumo financeiro</div>', $html);
        $this->assertStringNotContainsString('<div class="fin-summary">', $html);
        $this->assertStringNotContainsString('Valor a Receber', $html);
        $this->assertLessThan(strpos($html, 'Vlr. Unit.'), strpos($html, 'Qtd. Total'));
        $this->assertSame(1, substr_count($html, 'Total Geral'));
    }

    public function test_customer_receipt_hides_disabled_financial_and_signature_sections(): void
    {
        $tenant = new Tenant(['name' => 'Cooperativa Teste']);
        $project = new SalesProject(['title' => 'PNAE 2026']);
        $customer = new Customer(['name' => 'Escola Central']);
        $receipt = new CustomerBillingReceipt([
            'receipt_year' => 2026,
            'receipt_number' => 3,
            'issued_at' => '2026-08-05',
        ]);

        $html = view('pdf.customer-billing-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'customer' => $customer,
            'receipt' => $receipt,
            'productRows' => [[
                'product' => 'Banana',
                'unit' => 'kg',
                'quantity' => 10,
                'unit_price' => 5,
                'gross' => 50,
                'net' => 50,
                'fee_values' => ['fee:customer:7' => 2.5],
            ]],
            'totalGross' => 50,
            'totalFees' => 0,
            'totalNet' => 50,
            'feeBreakdown' => [],
            'feeColumns' => [[
                'key' => 'fee:customer:7',
                'name' => 'Gestão',
                'nature' => 'discount',
            ]],
            'visibleColumns' => ['unit_price', 'gross', 'fee:customer:7'],
            'visible_sections' => ['document_info', 'customer_info', 'project_info', 'deliveries'],
        ])->render();

        $this->assertStringContainsString('Nº Documento:', $html);
        $this->assertStringContainsString('Entregas por Produto', $html);
        $this->assertStringNotContainsString('<div class="sec-label">Resumo financeiro</div>', $html);
        $this->assertStringNotContainsString('<div class="fin-summary">', $html);
        $this->assertStringNotContainsString('Valor Líquido</div>', $html);
        $this->assertStringContainsString('Gestão', $html);
        $this->assertStringContainsString('- R$ 2,50', $html);
    }

    public function test_associate_receipt_hides_disabled_financial_section(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();

        $html = view('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [],
            'visible_sections' => ['associate_info', 'project_info', 'deliveries'],
        ])->render();

        $this->assertStringContainsString('TOTAL GERAL', $html);
        $this->assertStringNotContainsString('<div class="fin-summary">', $html);
        $this->assertStringNotContainsString('Valor Líquido a Receber', $html);
    }

    public function test_associate_receipt_renders_selected_project_fee_column(): void
    {
        [$tenant, $project, $associate, $receipt, $summary, $products] = $this->associateReceiptFixtures();
        foreach ($products as &$product) {
            $product['fee_totals']['fee:associate:7'] = 3.5;
            foreach ($product['distributions'] as &$distribution) {
                $distribution['fee_values']['fee:associate:7'] = 3.5;
            }
        }

        $html = view('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $summary,
            'productsSummary' => $products,
            'feeBreakdown' => ['fees' => [], 'has_detail' => false],
            'feeColumns' => [[
                'key' => 'fee:associate:7',
                'name' => 'Frete',
                'nature' => 'discount',
            ]],
            'visible_columns' => ['gross', 'fee:associate:7'],
        ])->render();

        $this->assertStringContainsString('<th class="r fee-col">Frete</th>', $html);
        $this->assertStringContainsString('-&nbsp;R$&nbsp;3,50', $html);
    }

    public function test_multi_project_customer_receipt_renders_one_table_per_project(): void
    {
        $project = function (int $id, string $title): SalesProject {
            $model = new class extends SalesProject
            {
                public function getTypeLabelAttribute(): string
                {
                    return 'PNAE';
                }
            };
            $model->id = $id;
            $model->tenant_id = 1;
            $model->title = $title;
            $model->type = 'pnae';

            return $model;
        };
        $january = $project(10, 'PNAE Janeiro');
        $february = $project(20, 'PNAE Fevereiro');
        $receipt = new CustomerBillingReceipt([
            'receipt_year' => 2026,
            'receipt_number' => 17,
            'issued_at' => '2026-03-01',
            'document_number' => 'NF-que-nao-deve-aparecer-no-resumo',
            'notes' => 'Cobrança consolidada dos períodos de janeiro e fevereiro.',
        ]);
        $receipt->setRelation('project', $january);
        $rows = [
            ['project_id' => 10, 'project' => $january->title, 'product' => 'Alface', 'unit' => 'kg', 'quantity' => 10,
                'unit_price' => 5, 'gross' => 50, 'net' => 45, 'fee_values' => ['fee:customer:1' => 5]],
            ['project_id' => 20, 'project' => $february->title, 'product' => 'Cenoura', 'unit' => 'kg', 'quantity' => 10,
                'unit_price' => 6, 'gross' => 60, 'net' => 48, 'fee_values' => ['fee:customer:2' => 12]],
        ];
        $projectGroups = [
            ['project' => $january, 'period' => '01/01/2026 a 31/01/2026', 'rows' => [$rows[0]],
                'fee_columns' => [['key' => 'fee:customer:1', 'name' => 'Taxa Jan.', 'nature' => 'discount']],
                'fee_totals' => ['fee:customer:1' => 5], 'subtotal_gross' => 50, 'subtotal_net' => 45],
            ['project' => $february, 'period' => '01/02/2026 a 28/02/2026', 'rows' => [$rows[1]],
                'fee_columns' => [['key' => 'fee:customer:2', 'name' => 'Taxa Fev.', 'nature' => 'discount']],
                'fee_totals' => ['fee:customer:2' => 12], 'subtotal_gross' => 60, 'subtotal_net' => 48],
        ];

        $html = view('pdf.customer-billing-receipt', [
            'tenant' => new Tenant(['name' => 'Cooperativa Teste']),
            'project' => $january,
            'projects' => collect([$january, $february]),
            'projectPeriods' => [
                ['project' => $january, 'period' => '01/01/2026 a 31/01/2026'],
                ['project' => $february, 'period' => '01/02/2026 a 28/02/2026'],
            ],
            'customer' => new Customer(['name' => 'Escola Central']),
            'receipt' => $receipt,
            'productRows' => $rows,
            'projectGroups' => $projectGroups,
            'isMultiProject' => true,
            'totalGross' => 110,
            'totalFees' => 17,
            'totalNet' => 93,
            'feeBreakdown' => [],
            'feeColumns' => array_merge($projectGroups[0]['fee_columns'], $projectGroups[1]['fee_columns']),
            'visibleColumns' => ['unit_price', 'gross', 'net', 'fee:customer:1', 'fee:customer:2'],
            'visible_sections' => ['document_info', 'customer_info', 'project_info', 'deliveries', 'financial'],
            'periodLabel' => '01/01/2026 a 28/02/2026',
        ])->render();

        $this->assertSame(2, substr_count($html, '<table class="tbl receipt-data-table">'));
        $this->assertStringContainsString('PNAE Janeiro', $html);
        $this->assertStringContainsString('PNAE Fevereiro', $html);
        $this->assertStringContainsString('Taxa Jan.', $html);
        $this->assertStringContainsString('Taxa Fev.', $html);
        $this->assertStringNotContainsString('<th>Projeto</th>', $html);
        $this->assertStringContainsString('Observações', $html);
        $this->assertStringContainsString('Cobrança consolidada dos períodos de janeiro e fevereiro.', $html);
        $this->assertStringNotContainsString('NF-que-nao-deve-aparecer-no-resumo', $html);
    }

    private function associateReceiptFixtures(): array
    {
        if (! Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('template_category');
                $table->string('system_template_key')->nullable();
                $table->string('project_type')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('consent_enabled')->default(true);
                $table->string('consent_position')->default('after');
                $table->longText('consent_content_before')->nullable();
                $table->longText('consent_content')->nullable();
                $table->boolean('show_recipient_signature')->default(true);
                $table->boolean('show_representative_signature')->default(true);
            });
        }
        if (! Schema::hasTable('sales_project_types')) {
            Schema::create('sales_project_types', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('slug');
            });
        }

        $tenant = new Tenant([
            'name' => 'Cooperativa de Teste',
            'city' => 'Miravania',
            'state' => 'MG',
            'legal_representative_name' => 'Representante Legal',
        ]);
        $tenant->id = 999999;

        $project = new SalesProject([
            'title' => 'PAA 2026',
            'type' => 'paa',
            'total_value' => 10000,
            'admin_fee_percentage' => 5,
        ]);
        $project->id = 999999;
        $project->tenant_id = $tenant->id;

        $associate = new Associate(['user_id' => null]);
        $associate->id = 999999;
        $associate->tenant_id = $tenant->id;

        $receipt = new AssociateReceipt([
            'receipt_number' => 21,
            'receipt_year' => 2026,
            'issued_at' => '2026-07-27',
        ]);

        $products = collect(range(1, 5))->map(fn (int $index): array => [
            'product_name' => 'Produto '.$index,
            'unit' => 'kg',
            'delivery_date' => now()->subDays($index),
            'total_quantity' => 10,
            'total_gross' => 100,
            'total_admin_fee' => 5,
            'total_net' => 95,
            'fee_totals' => [],
            'distributions' => [[
                'customer_name' => 'Cliente Padrao',
                'quantity' => 10,
                'unit_price' => 10,
                'gross' => 100,
                'admin_fee' => 5,
                'net' => 95,
                'fee_values' => [],
            ]],
        ])->all();
        $summary = [
            'gross_value' => 500,
            'admin_fee' => 25,
            'net_value' => 475,
            'deliveries_count' => 5,
            'total_quantity' => 50,
            'fee_totals' => [],
            'customer_ids' => [1],
        ];

        return [$tenant, $project, $associate, $receipt, $summary, $products];
    }
}
